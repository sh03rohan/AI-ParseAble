<?php
/**
 * Admin screen.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Admin;

use AiParseAble\Activation;
use AiParseAble\Module;
use AiParseAble\Support\Options;

/**
 * One admin page, one React app. Assets load on that page only.
 */
final class Admin implements Module {

	const SLUG = 'ai-parseable';

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
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PARSEABLE_FILE ), array( $this, 'action_links' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Menu entry, gated on the custom capability rather than manage_options.
	 *
	 * @return void
	 */
	public function menu(): void {
		$this->hook = (string) add_menu_page(
			__( 'AI ParseAble', 'ai-parseable' ),
			__( 'AI ParseAble', 'ai-parseable' ),
			Activation::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			AI_PARSEABLE_URL . 'images/menu-icon.png',
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
			$classes .= ' ai-parseable-screen';
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
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dashboard', 'ai-parseable' ) . '</a>' );
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
		$asset_file = AI_PARSEABLE_DIR . 'assets/admin.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => AI_PARSEABLE_VERSION,
		);
		wp_enqueue_script( 'ai-parseable-admin', AI_PARSEABLE_URL . 'assets/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( AI_PARSEABLE_DIR . 'assets/admin.css' ) ) {
			wp_enqueue_style( 'ai-parseable-admin', AI_PARSEABLE_URL . 'assets/admin.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_set_script_translations( 'ai-parseable-admin', 'ai-parseable', AI_PARSEABLE_DIR . 'languages' );

		$config = array(
			'restNamespace' => 'ai-parseable/v1',
			'version'       => AI_PARSEABLE_VERSION,
			'siteUrl'       => home_url( '/' ),
			'adminUrl'      => admin_url(),
			'isMultisite'   => is_multisite(),
			'dismissed'     => $this->dismissed_notices(),
			'historyDays'   => $this->options->history_days(),
			'installedAt'   => (int) $this->options->get( 'installed_at', 0 ),
			'logo'          => AI_PARSEABLE_URL . 'images/logo-64.png',
		);
		wp_add_inline_script( 'ai-parseable-admin', 'window.aiParseAble = ' . wp_json_encode( $config ) . ';', 'before' );

		// Preload what the first paint needs so it renders without a single REST round trip.
		$preload = array_reduce(
			array( '/ai-parseable/v1/stats?range=7d&verified=1', '/ai-parseable/v1/urls?verified=1', '/ai-parseable/v1/coverage', '/ai-parseable/v1/settings' ),
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
			if ( get_user_meta( get_current_user_id(), 'ai_parseable_dismissed_' . $id, true ) ) {
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
		echo '<div class="wrap ai-parseable-wrap">';
		echo '<h1 class="screen-reader-text">' . esc_html__( 'AI ParseAble', 'ai-parseable' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		echo '<div id="ai-parseable-app"><p class="aip-boot">' . esc_html__( 'Loading AI ParseAble…', 'ai-parseable' ) . '</p></div>';
		echo '</div>';
	}
}
