<?php
/**
 * Schema augmentation with Rank Math / WooCommerce style graphs.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Tests\Integration;

use AiParseAble\Schema\Augmenter;
use WP_UnitTestCase;

/**
 * Exactly one Product node, with a price in it.
 */
final class SchemaTest extends WP_UnitTestCase {

	public function test_rank_math_style_graph_gets_exactly_one_product_with_price(): void {
		$graph   = array(
			'WebSite' => array( '@type' => 'WebSite', 'name' => 'Shop' ),
			'Product' => array( '@type' => 'Product', 'name' => 'Widget' ),
		);
		$context = array(
			'product' => array( 'name' => 'Widget', 'price' => '19.99', 'currency' => 'USD', 'availability' => 'https://schema.org/InStock', 'url' => 'https://x/widget/' ),
		);
		$result  = Augmenter::augment( array_values( $graph ), $context );
		$products = array_filter( $result['graph'], static function ( $n ) { return 'Product' === $n['@type']; } );
		$this->assertCount( 1, $products );
		$product = array_values( $products )[0];
		$this->assertSame( '19.99', $product['offers']['price'] );
		$this->assertSame( 'USD', $product['offers']['priceCurrency'] );
		$this->assertContains( 'Product.offers', $result['changes'] );
	}

	public function test_existing_price_is_never_overwritten(): void {
		$graph   = array( array( '@type' => 'Product', 'offers' => array( '@type' => 'Offer', 'price' => '5.00' ) ) );
		$context = array( 'product' => array( 'price' => '19.99', 'currency' => 'USD' ) );
		$result  = Augmenter::augment( $graph, $context );
		$this->assertSame( '5.00', $result['graph'][0]['offers']['price'] );
		$this->assertSame( 'USD', $result['graph'][0]['offers']['priceCurrency'] );
	}

	public function test_no_product_node_added_on_non_product_page(): void {
		$result = Augmenter::augment( array( array( '@type' => 'WebPage' ) ), array( 'page' => array( 'name' => 'About' ) ) );
		$this->assertCount( 1, $result['graph'] );
	}
}
