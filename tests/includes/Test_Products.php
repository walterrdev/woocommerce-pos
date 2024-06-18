<?php

namespace WCPOS\WooCommercePOS\Tests;

use Automattic\WooCommerce\RestApi\UnitTests\Helpers\ProductHelper;
use WP_UnitTestCase;
use WP_Query;
use WCPOS\WooCommercePOS\Products;
use WC_Product_Variation;
use WC_Install;

/**
 * @internal
 *
 * @coversNothing
 */
class Test_Products extends WP_UnitTestCase {
	public function setup(): void {
		parent::setup();

		WC_Install::create_pages();
		$shop_page_id = wc_get_page_id( 'shop' );
		$this->assertTrue(
			$shop_page_id && get_post_status( $shop_page_id ) == 'publish',
			'The WooCommerce "shop" page is not set up.'
		);
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 *
	 */
	public function test_pos_only_products() {
		add_filter(
			'woocommerce_pos_general_settings',
			function () {
				return array(
					'pos_only_products' => true,
				);
			}
		);
		new Products(); // reinstantiate the class to apply the filter

		// Create a visible product
		$visible_product = ProductHelper::create_simple_product();

		// Create a product with _pos_visibility set to 'pos_only'
		$hidden_product = ProductHelper::create_simple_product();

		update_option(
			'woocommerce_pos_settings_visibility',
			array(
				'products' => array(
					'default' => array(
						'pos_only' => array(
							'ids' => array( $hidden_product->get_id() ),
						),
						'online_only' => array(
							'ids' => array(),
						),
					),
				),
				'variations' => array(
					'default' => array(
						'pos_only' => array(
							'ids' => array(),
						),
						'online_only' => array(
							'ids' => array(),
						),
					),
				),
			)
		);

		// Mimic the main WooCommerce query
		$query_args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1, // Get all products for testing
		);

		$this->go_to( wc_get_page_id( 'shop' ) );

		$query = new WP_Query( $query_args );
		WC()->query->product_query( $query );
		$queried_ids = wp_list_pluck( $query->get_posts(), 'ID' );

		// Assert that the visible product is in the query
		$this->assertContains( $visible_product->get_id(), $queried_ids );

		// Assert that the hidden product is not in the query
		$this->assertNotContains( $hidden_product->get_id(), $queried_ids );
	}

	/**
	 *
	 */
	public function test_pos_only_variations() {
		add_filter(
			'woocommerce_pos_general_settings',
			function () {
				return array(
					'pos_only_products' => true,
				);
			}
		);
		new Products(); // reinstantiate the class to apply the filter

		// create variations
		$product = ProductHelper::create_variation_product();
		$variation_3 = new WC_Product_Variation();
		$variation_3->set_props(
			array(
				'parent_id'     => $product->get_id(),
				'sku'           => 'DUMMY SKU VARIABLE MEDIUM',
				'regular_price' => 10,
			)
		);
		$variation_3->set_attributes( array( 'pa_size' => 'medium' ) );
		$variation_3->save();

		$variation_ids = $product->get_children();

		update_option(
			'woocommerce_pos_settings_visibility',
			array(
				'products' => array(
					'default' => array(
						'pos_only' => array(
							'ids' => array(),
						),
						'online_only' => array(
							'ids' => array(),
						),
					),
				),
				'variations' => array(
					'default' => array(
						'pos_only' => array(
							'ids' => array( $variation_ids[0] ),
						),
						'online_only' => array(
							'ids' => array( $variation_ids[1] ),
						),
					),
				),
			)
		);

		// Mimic the main WooCommerce query for product variations
		$query_args = array(
			'post_type'     => 'product_variation',
			'post_status'   => 'publish',
			'posts_per_page' => -1, // Get all variations for testing
			'post_parent'   => $product->get_id(), // Ensure variations of the specific product are fetched
		);

		$this->go_to( wc_get_page_id( 'shop' ) );

		$query = new WP_Query( $query_args );
		WC()->query->product_query( $query );
		$queried_variation_ids = wp_list_pluck( $query->get_posts(), 'ID' );

		// Assert that the variation with '_pos_visibility' set to 'pos_only' is NOT in the query
		$this->assertNotContains( $variation_ids[0], $queried_variation_ids );

		// Assert that the variation with '_pos_visibility' set to 'online_only' IS in the query
		$this->assertContains( $variation_ids[1], $queried_variation_ids );

		// Assert that the variation without '_pos_visibility' set is in the query
		$this->assertContains( $variation_ids[2], $queried_variation_ids );
	}
}
