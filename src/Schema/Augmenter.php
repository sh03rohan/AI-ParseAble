<?php
/**
 * Graph augmentation.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Schema;

/**
 * Pure: takes a graph (list of nodes) and a context of facts, fills only what is genuinely absent.
 * Never emits a second node of a type that already exists.
 */
final class Augmenter {

	/**
	 * Fill missing properties on existing nodes; add a Product node only when the graph has none and
	 * the page is a product; add an Article node only when the graph has none and the page is a post.
	 *
	 * @param array<mixed> $graph   Nodes.
	 * @param array<mixed> $context Facts from Context::for_queried_object().
	 * @return array{graph: array<int, array<string, mixed>>, changes: string[]}
	 */
	public static function augment( array $graph, array $context ): array {
		$changes = array();
		$nodes   = array_values( $graph );

		$has = array(
			'Product' => false,
			'Article' => false,
		);
		foreach ( $nodes as $node ) {
			$type = self::types( $node );
			if ( array_intersect( $type, array( 'Product', 'ProductGroup' ) ) ) {
				$has['Product'] = true;
			}
			if ( array_intersect( $type, array( 'Article', 'BlogPosting', 'NewsArticle', 'TechArticle' ) ) ) {
				$has['Article'] = true;
			}
		}

		foreach ( $nodes as $i => $node ) {
			$types = self::types( $node );
			if ( ! empty( $context['product'] ) && array_intersect( $types, array( 'Product' ) ) ) {
				$nodes[ $i ] = self::fill_product( $node, $context['product'], $changes );
			}
			if ( ! empty( $context['article'] ) && array_intersect( $types, array( 'Article', 'BlogPosting', 'NewsArticle', 'TechArticle' ) ) ) {
				$nodes[ $i ] = self::fill_article( $node, $context['article'], $changes );
			}
			if ( ! empty( $context['page'] ) && array_intersect( $types, array( 'WebPage', 'ItemPage', 'CollectionPage', 'AboutPage', 'ContactPage' ) ) ) {
				$nodes[ $i ] = self::fill_page( $node, $context['page'], $changes );
			}
		}

		if ( ! $has['Product'] && ! empty( $context['product'] ) ) {
			$nodes[]   = self::fill_product( array( '@type' => 'Product' ), $context['product'], $changes );
			$changes[] = 'Added Product node';
		}
		if ( ! $has['Article'] && ! empty( $context['article'] ) && empty( $context['product'] ) ) {
			$nodes[]   = self::fill_article( array( '@type' => 'Article' ), $context['article'], $changes );
			$changes[] = 'Added Article node';
		}

		return array(
			'graph'   => $nodes,
			'changes' => array_values( array_unique( $changes ) ),
		);
	}

	/**
	 * Normalised @type list.
	 *
	 * @param array<mixed> $node Node.
	 * @return string[]
	 */
	private static function types( array $node ): array {
		if ( empty( $node['@type'] ) ) {
			return array();
		}
		return array_map( 'strval', (array) $node['@type'] );
	}

	/**
	 * Set when absent.
	 *
	 * @param array<mixed> $node    Node.
	 * @param string       $key     Property.
	 * @param mixed        $value   Value.
	 * @param string[]     $changes Change log.
	 * @param string       $label   Label for the log.
	 * @return array<mixed>
	 */
	private static function set_if_absent( array $node, string $key, $value, array &$changes, string $label ): array {
		if ( null === $value || '' === $value || array() === $value ) {
			return $node;
		}
		if ( ! array_key_exists( $key, $node ) || null === $node[ $key ] || '' === $node[ $key ] ) {
			$node[ $key ] = $value;
			$changes[]    = $label . '.' . $key;
		}
		return $node;
	}

	/**
	 * Product facts.
	 *
	 * @param array<mixed> $node    Node.
	 * @param array<mixed> $p       Product context.
	 * @param string[]     $changes Change log.
	 * @return array<mixed>
	 */
	private static function fill_product( array $node, array $p, array &$changes ): array {
		$node = self::set_if_absent( $node, 'name', $p['name'] ?? null, $changes, 'Product' );
		$node = self::set_if_absent( $node, 'description', $p['description'] ?? null, $changes, 'Product' );
		$node = self::set_if_absent( $node, 'sku', $p['sku'] ?? null, $changes, 'Product' );
		$node = self::set_if_absent( $node, 'image', $p['image'] ?? null, $changes, 'Product' );
		$node = self::set_if_absent( $node, 'url', $p['url'] ?? null, $changes, 'Product' );
		$node = self::set_if_absent(
			$node,
			'brand',
			isset( $p['brand'] ) ? array(
				'@type' => 'Brand',
				'name'  => $p['brand'],
			) : null,
			$changes,
			'Product'
		);

		if ( isset( $p['price'] ) && '' !== $p['price'] ) {
			if ( empty( $node['offers'] ) ) {
				$node['offers'] = array(
					'@type'         => 'Offer',
					'price'         => $p['price'],
					'priceCurrency' => $p['currency'] ?? '',
					'availability'  => $p['availability'] ?? '',
					'url'           => $p['url'] ?? '',
				);
				$changes[]      = 'Product.offers';
			} elseif ( is_array( $node['offers'] ) && isset( $node['offers']['@type'] ) ) {
				$node['offers'] = self::set_if_absent( $node['offers'], 'price', $p['price'], $changes, 'Offer' );
				$node['offers'] = self::set_if_absent( $node['offers'], 'priceCurrency', $p['currency'] ?? null, $changes, 'Offer' );
				$node['offers'] = self::set_if_absent( $node['offers'], 'availability', $p['availability'] ?? null, $changes, 'Offer' );
			}
		}
		return $node;
	}

	/**
	 * Article facts.
	 *
	 * @param array<mixed> $node    Node.
	 * @param array<mixed> $a       Article context.
	 * @param string[]     $changes Change log.
	 * @return array<mixed>
	 */
	private static function fill_article( array $node, array $a, array &$changes ): array {
		$node = self::set_if_absent( $node, 'headline', $a['headline'] ?? null, $changes, 'Article' );
		$node = self::set_if_absent( $node, 'datePublished', $a['datePublished'] ?? null, $changes, 'Article' );
		$node = self::set_if_absent( $node, 'dateModified', $a['dateModified'] ?? null, $changes, 'Article' );
		$node = self::set_if_absent(
			$node,
			'author',
			isset( $a['author'] ) ? array(
				'@type' => 'Person',
				'name'  => $a['author'],
			) : null,
			$changes,
			'Article'
		);
		$node = self::set_if_absent( $node, 'image', $a['image'] ?? null, $changes, 'Article' );
		$node = self::set_if_absent( $node, 'wordCount', $a['wordCount'] ?? null, $changes, 'Article' );
		$node = self::set_if_absent( $node, 'inLanguage', $a['inLanguage'] ?? null, $changes, 'Article' );
		$node = self::set_if_absent( $node, 'mainEntityOfPage', $a['url'] ?? null, $changes, 'Article' );
		return $node;
	}

	/**
	 * WebPage facts.
	 *
	 * @param array<mixed> $node    Node.
	 * @param array<mixed> $p       Page context.
	 * @param string[]     $changes Change log.
	 * @return array<mixed>
	 */
	private static function fill_page( array $node, array $p, array &$changes ): array {
		$node = self::set_if_absent( $node, 'name', $p['name'] ?? null, $changes, 'WebPage' );
		$node = self::set_if_absent( $node, 'url', $p['url'] ?? null, $changes, 'WebPage' );
		$node = self::set_if_absent( $node, 'datePublished', $p['datePublished'] ?? null, $changes, 'WebPage' );
		$node = self::set_if_absent( $node, 'dateModified', $p['dateModified'] ?? null, $changes, 'WebPage' );
		$node = self::set_if_absent( $node, 'inLanguage', $p['inLanguage'] ?? null, $changes, 'WebPage' );
		return $node;
	}
}
