<?php
/**
 * Schema augmentation module.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Schema;

use CrawlLedger\Module;
use CrawlLedger\Support\Options;

/**
 * Detects the active provider and hooks its graph filter. Emits its own graph only when nobody else does.
 */
final class Schema implements Module {

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Hooks. Registered on wp so provider detection can see the active plugins.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->options->get( 'schema_enabled', true ) ) {
			return;
		}
		add_action( 'wp', array( $this, 'hook_provider' ) );
	}

	/**
	 * Whether the preview loopback asked for the unaugmented graph.
	 *
	 * @return bool
	 */
	private function disabled_for_preview(): bool {
		return isset( $_GET['crawlledger_schema'] ) && 'off' === $_GET['crawlledger_schema']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview flag.
	}

	/**
	 * Active provider name.
	 *
	 * @return string yoast | rankmath | woocommerce | none
	 */
	public static function provider(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		if ( function_exists( 'WC' ) ) {
			return 'woocommerce';
		}
		return 'none';
	}

	/**
	 * Hook whichever provider is present.
	 *
	 * @return void
	 */
	public function hook_provider(): void {
		if ( ! is_singular() || $this->disabled_for_preview() ) {
			return;
		}
		$provider = self::provider();
		if ( 'yoast' === $provider ) {
			add_filter( 'wpseo_schema_graph', array( $this, 'filter_graph' ), 20, 1 );
			return;
		}
		if ( 'rankmath' === $provider ) {
			add_filter( 'rank_math/json_ld', array( $this, 'filter_rankmath' ), 20, 1 );
			return;
		}
		if ( 'woocommerce' === $provider ) {
			add_filter( 'woocommerce_structured_data_product', array( $this, 'filter_wc_product' ), 20, 2 );
			if ( is_singular( 'product' ) ) {
				// Page-builder product templates often skip the hooks WooCommerce generates its data from.
				// WooCommerce prints in wp_footer at priority 10; check afterwards and fill the gap if it was silent.
				add_action( 'wp_footer', array( $this, 'output_own_if_wc_silent' ), 20 );
			} else {
				add_action( 'wp_head', array( $this, 'output_own' ), 20 );
			}
			return;
		}
		add_action( 'wp_head', array( $this, 'output_own' ), 20 );
	}

	/**
	 * Yoast: a list of nodes.
	 *
	 * @param array<mixed> $graph Nodes.
	 * @return array<mixed>
	 */
	public function filter_graph( $graph ): array {
		if ( ! is_array( $graph ) ) {
			return (array) $graph;
		}
		return Augmenter::augment( $graph, Context::for_queried_object() )['graph'];
	}

	/**
	 * Rank Math: an associative map of key => node.
	 *
	 * @param array<mixed> $data Nodes keyed by name.
	 * @return array<mixed>
	 */
	public function filter_rankmath( $data ): array {
		if ( ! is_array( $data ) ) {
			return (array) $data;
		}
		$keys   = array_keys( $data );
		$result = Augmenter::augment( array_values( $data ), Context::for_queried_object() );
		$out    = array();
		foreach ( $result['graph'] as $i => $node ) {
			$out[ isset( $keys[ $i ] ) ? $keys[ $i ] : 'crawlledger_' . $i ] = $node;
		}
		return $out;
	}

	/**
	 * WooCommerce: a single Product node.
	 *
	 * @param array<mixed> $markup  Node.
	 * @param object       $product Product.
	 * @return array<mixed>
	 */
	public function filter_wc_product( $markup, $product ): array {
		if ( ! is_array( $markup ) ) {
			return (array) $markup;
		}
		$context = Context::for_queried_object();
		$result  = Augmenter::augment( array( $markup ), $context );
		return $result['graph'][0];
	}

	/**
	 * On a product page where WooCommerce produced no structured data, emit our own graph.
	 *
	 * @return void
	 */
	public function output_own_if_wc_silent(): void {
		if ( ! function_exists( 'WC' ) || empty( WC()->structured_data ) || ! method_exists( WC()->structured_data, 'get_data' ) ) {
			return;
		}
		$data = WC()->structured_data->get_data();
		foreach ( (array) $data as $node ) {
			if ( isset( $node['@type'] ) && 'Product' === $node['@type'] ) {
				return; // WooCommerce handled it (and our filter merged the gaps).
			}
		}
		$this->output_own();
	}

	/**
	 * Minimal graph of our own when no provider is active.
	 *
	 * @return void
	 */
	public function output_own(): void {
		$context = Context::for_queried_object();
		if ( ! $context ) {
			return;
		}
		$graph  = array(
			array(
				'@type' => 'WebSite',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			array( '@type' => 'WebPage' ),
		);
		$result = Augmenter::augment( $graph, $context );
		$json   = array(
			'@context' => 'https://schema.org',
			'@graph'   => $result['graph'],
		);
		// JSON_HEX_TAG turns < and > into \u003C and \u003E, so a value containing "</script>" cannot close the block.
		echo "\n<script type=\"application/ld+json\" class=\"crawlledger-schema\">" . wp_json_encode( $json, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}

	/**
	 * Before/after preview for a real page, by fetching it twice over loopback.
	 *
	 * @param int $post_id Post id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function preview( int $post_id ) {
		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return new \WP_Error( 'no_post', __( 'That post does not exist.', 'crawlledger-ai-crawler-log' ), array( 'status' => 404 ) );
		}
		$before = $this->fetch_ld(
			add_query_arg(
				array(
					'crawlledger_schema'  => 'off',
					'crawlledger_nocache' => time(),
				),
				$url
			)
		);
		$after  = $this->fetch_ld( add_query_arg( array( 'crawlledger_nocache' => time() + 1 ), $url ) );
		if ( is_wp_error( $before ) ) {
			return $before;
		}
		if ( is_wp_error( $after ) ) {
			return $after;
		}
		return array(
			'url'      => $url,
			'provider' => self::provider(),
			'before'   => $before,
			'after'    => $after,
		);
	}

	/**
	 * Fetch a URL and extract JSON-LD blocks.
	 *
	 * @param string $url URL.
	 * @return array<int, mixed>|\WP_Error
	 */
	private function fetch_ld( string $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'cookies'   => array(),
				'headers'   => array( 'Cache-Control' => 'no-cache' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( ! preg_match_all( '~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $body, $m ) ) {
			return array();
		}
		$blocks = array();
		foreach ( $m[1] as $raw ) {
			$decoded = json_decode( trim( $raw ), true );
			if ( null !== $decoded ) {
				$blocks[] = $decoded;
			}
		}
		return $blocks;
	}
}
