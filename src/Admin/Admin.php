<?php
/**
 * Admin screen.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Admin;

use CrawlLedger\Activation;
use CrawlLedger\Module;
use CrawlLedger\Support\Assets;
use CrawlLedger\Support\Options;

/**
 * One admin page, one React app. Assets load on that page only.
 */
final class Admin implements Module {

	const SLUG = 'crawlledger';

	/**
	 * Monochrome menu mark, base64 SVG so WordPress recolours it for the active admin scheme.
	 */
	const MENU_ICON = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZD0iTTE0LjYgNC40YTcgNyAwIDEgMCAwIDExLjIiIGZpbGw9Im5vbmUiIHN0cm9rZT0iYmxhY2siIHN0cm9rZS13aWR0aD0iMi4xIiBzdHJva2UtbGluZWNhcD0icm91bmQiLz48cGF0aCBkPSJNOC4xIDcuMmgzLjRsMS4zIDEuM3YzLjFhLjguOCAwIDAgMS0uOC44SDguMWEuOC44IDAgMCAxLS44LS44VjhhLjguOCAwIDAgMSAuOC0uOHoiIGZpbGw9ImJsYWNrIi8+PHBhdGggZD0iTTguOSA5aDIuNk04LjkgMTAuNWgyLjYiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSIuOSIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIi8+PHBhdGggZD0iTTEzLjQgMTIuNHYtMS4yTTE1IDEyLjRWOS45TTE2LjYgMTIuNFY4LjYiIHN0cm9rZT0iYmxhY2siIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiLz48L3N2Zz4=';

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CRAWLLEDGER_FILE ), array( $this, 'action_links' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Menu entry, gated on the custom capability rather than manage_options.
	 *
	 * @return void
	 */
	public function menu(): void {
		$this->hook = (string) add_menu_page(
			__( 'CrawlLedger', 'crawlledger-ai-crawler-log' ),
			__( 'CrawlLedger', 'crawlledger-ai-crawler-log' ),
			Activation::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'data:image/svg+xml;base64,' . self::MENU_ICON,
			81
		);
	}

	/**
	 * Mark our screen so the stylesheet can theme the content area, and nothing else.
	 *
	 * @param string $classes Space-separated classes.
	 * @return string
	 */
	public function body_class( $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $this->is_our_screen( (string) $screen->id ) ) {
			$classes .= ' crawlledger-screen';
		}
		return $classes;
	}

	/**
	 * Settings link on the plugins list.
	 *
	 * @param array<mixed> $links Links.
	 * @return array<mixed>
	 */
	public function action_links( $links ): array {
		$url = admin_url( 'admin.php?page=' . self::SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dashboard', 'crawlledger-ai-crawler-log' ) . '</a>' );
		return $links;
	}

	/**
	 * Whether the current screen is ours.
	 *
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	public function is_our_screen( string $hook ): bool {
		return '' !== $this->hook && $hook === $this->hook;
	}

	/**
	 * Enqueue the app on our page only.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function assets( $hook ): void {
		if ( ! $this->is_our_screen( (string) $hook ) ) {
			return;
		}
		$asset_file = CRAWLLEDGER_DIR . 'assets/admin.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => CRAWLLEDGER_VERSION,
		);
		Assets::ensure_jsx_runtime();
		wp_enqueue_script( 'crawlledger-admin', CRAWLLEDGER_URL . 'assets/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( CRAWLLEDGER_DIR . 'assets/admin.css' ) ) {
			wp_enqueue_style( 'crawlledger-admin', CRAWLLEDGER_URL . 'assets/admin.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_set_script_translations( 'crawlledger-admin', 'crawlledger-ai-crawler-log', CRAWLLEDGER_DIR . 'languages' );

		$config = array(
			'restNamespace' => 'crawlledger/v1',
			'version'       => CRAWLLEDGER_VERSION,
			'siteUrl'       => home_url( '/' ),
			'adminUrl'      => admin_url(),
			'isMultisite'   => is_multisite(),
			'dismissed'     => $this->dismissed_notices(),
			'historyDays'   => $this->options->history_days(),
			'installedAt'   => (int) $this->options->get( 'installed_at', 0 ),
			'logo'          => CRAWLLEDGER_URL . 'images/logo-64.png',
		);
		wp_add_inline_script( 'crawlledger-admin', 'window.crawlLedger = ' . wp_json_encode( $config ) . ';', 'before' );

		// Preload what the first paint needs so it renders without a single REST round trip.
		$preload = array_reduce(
			array( '/crawlledger/v1/stats?range=7d&verified=1', '/crawlledger/v1/urls?verified=1', '/crawlledger/v1/coverage', '/crawlledger/v1/settings' ),
			'rest_preload_api_request',
			array()
		);
		wp_add_inline_script( 'wp-api-fetch', 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( ' . wp_json_encode( $preload ) . ' ) );', 'after' );
	}

	/**
	 * Notice ids the current user dismissed.
	 *
	 * @return string[]
	 */
	private function dismissed_notices(): array {
		$out = array();
		foreach ( array( 'ingest', 'coverage', 'robots-physical', 'queue-readable' ) as $id ) {
			if ( get_user_meta( get_current_user_id(), 'crawlledger_dismissed_' . $id, true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Mount point.
	 *
	 * @return void
	 */
	public function render(): void {
		// The heading and wp-header-end marker keep WordPress's notice relocation above the app, not inside it.
		echo '<div class="wrap crawlledger-wrap">';
		echo '<h1 class="screen-reader-text">' . esc_html__( 'CrawlLedger', 'crawlledger-ai-crawler-log' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		echo '<div id="crawlledger-app"><p class="clg-boot">' . esc_html__( 'Loading CrawlLedger…', 'crawlledger-ai-crawler-log' ) . '</p></div>';
		echo '</div>';
	}
}
