<?php
/**
 * Facts for the current page, read live from the source of truth.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Schema;

/**
 * Never caches a price into an option: values come from wc_get_product() and the post at render time.
 */
final class Context {

	/**
	 * Facts for the queried object, or an empty array off singular views.
	 *
	 * @return array<mixed>
	 */
	public static function for_queried_object(): array {
		if ( ! is_singular() ) {
			return array();
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}
		return self::for_post( $post );
	}

	/**
	 * Facts for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<mixed>
	 */
	public static function for_post( \WP_Post $post ): array {
		$url     = get_permalink( $post );
		$lang    = get_bloginfo( 'language' );
		$image   = get_the_post_thumbnail_url( $post, 'full' );
		$context = array(
			'page' => array(
				'name'          => get_the_title( $post ),
				'url'           => $url,
				'datePublished' => get_the_date( 'c', $post ),
				'dateModified'  => get_the_modified_date( 'c', $post ),
				'inLanguage'    => $lang,
			),
		);

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$availability = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
				if ( $product->is_on_backorder() ) {
					$availability = 'https://schema.org/BackOrder';
				}
				$context['product'] = array(
					'name'         => $product->get_name(),
					'description'  => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
					'sku'          => $product->get_sku(),
					'image'        => $image ? $image : null,
					'url'          => $url,
					'price'        => '' !== $product->get_price() ? wc_format_decimal( $product->get_price(), wc_get_price_decimals() ) : '',
					'currency'     => get_woocommerce_currency(),
					'availability' => $availability,
				);
			}
			return $context;
		}

		if ( 'post' === $post->post_type || in_array( $post->post_type, (array) apply_filters( 'crawlledger_article_post_types', array( 'post' ) ), true ) ) {
			$author             = get_userdata( (int) $post->post_author );
			$context['article'] = array(
				'headline'      => get_the_title( $post ),
				'datePublished' => get_the_date( 'c', $post ),
				'dateModified'  => get_the_modified_date( 'c', $post ),
				'author'        => $author ? $author->display_name : null,
				'image'         => $image ? $image : null,
				'wordCount'     => str_word_count( wp_strip_all_tags( $post->post_content ) ),
				'inLanguage'    => $lang,
				'url'           => $url,
			);
		}
		return $context;
	}
}
