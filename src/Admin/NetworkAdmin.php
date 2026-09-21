<?php
/**
 * Network overview.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Admin;

use AiParseAble\Logger\Repository;
use AiParseAble\Module;

/**
 * Network admins get a read-only overview across sites. Configuration stays per site.
 * Rendered in PHP with the dashboard's stylesheet so it matches, without loading the React bundle.
 */
final class NetworkAdmin implements Module {

	const SLUG = 'ai-parseable-network';

	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'network_admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public function menu(): void {
		$this->hook = (string) add_menu_page(
			__( 'AI ParseAble', 'ai-parseable' ),
			__( 'AI ParseAble', 'ai-parseable' ),
			'manage_network',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-visibility',
			81
		);
	}

	/**
	 * Whether the given hook is our page.
	 *
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	private function is_ours( string $hook ): bool {
		// Screen ids carry a "-network" suffix in the network admin; hook suffixes do not.
		return '' !== $this->hook && ( $hook === $this->hook || $hook === $this->hook . '-network' );
	}

	/**
	 * Stylesheet only.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function assets( $hook ): void {
		if ( ! $this->is_ours( (string) $hook ) || ! is_readable( AI_PARSEABLE_DIR . 'assets/admin.css' ) ) {
			return;
		}
		wp_enqueue_style( 'ai-parseable-admin', AI_PARSEABLE_URL . 'assets/admin.css', array(), AI_PARSEABLE_VERSION );
	}

	/**
	 * Same canvas as the site dashboard.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $this->is_ours( (string) $screen->id ) ) {
			$classes .= ' ai-parseable-screen';
		}
		return $classes;
	}

	/**
	 * Per-site totals for the last 7 days.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-parseable' ) );
		}
		$sites = get_sites( array( 'number' => 500 ) );
		$rows  = array();
		$sum   = array(
			'total'    => 0,
			'verified' => 0,
		);
		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );
			try {
				$totals = $this->repository->tables_exist() ? $this->repository->totals( 7 ) : array(
					'total'    => 0,
					'verified' => 0,
				);
				$rows[] = array(
					'name'     => get_bloginfo( 'name' ),
					'host'     => $site->domain . $site->path,
					'url'      => admin_url( 'admin.php?page=' . Admin::SLUG ),
					'total'    => (int) $totals['total'],
					'verified' => (int) $totals['verified'],
				);
			} finally {
				restore_current_blog();
			}
			$sum['total']    += (int) $totals['total'];
			$sum['verified'] += (int) $totals['verified'];
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['total'] - $a['total'];
			}
		);
		?>
		<div class="wrap">
			<div class="aip-app aip-app--static">
				<div class="aip-static-head">
					<img class="aip-brand-mark" src="<?php echo esc_url( AI_PARSEABLE_URL . 'images/logo-64.png' ); ?>" alt="" width="28" height="28" />
					<h1><?php esc_html_e( 'AI ParseAble', 'ai-parseable' ); ?></h1>
					<span class="aip-version">v<?php echo esc_html( AI_PARSEABLE_VERSION ); ?></span>
					<span class="aip-muted"><?php esc_html_e( 'Network overview', 'ai-parseable' ); ?></span>
				</div>
				<div class="aip-main">
					<div class="aip-stats aip-stats--3">
						<div class="aip-stat"><span class="aip-stat-label"><?php esc_html_e( 'Sites', 'ai-parseable' ); ?></span><span class="aip-stat-row"><span class="aip-stat-value"><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></span></span><span class="aip-stat-sub"><?php esc_html_e( 'in this network', 'ai-parseable' ); ?></span></div>
						<div class="aip-stat"><span class="aip-stat-label"><?php esc_html_e( 'Crawler visits', 'ai-parseable' ); ?></span><span class="aip-stat-row"><span class="aip-stat-value"><?php echo esc_html( number_format_i18n( $sum['total'] ) ); ?></span></span><span class="aip-stat-sub"><?php esc_html_e( 'last 7 days, all sites', 'ai-parseable' ); ?></span></div>
						<div class="aip-stat"><span class="aip-stat-label"><?php esc_html_e( 'Verified', 'ai-parseable' ); ?></span><span class="aip-stat-row"><span class="aip-stat-value"><?php echo esc_html( number_format_i18n( $sum['verified'] ) ); ?></span></span><span class="aip-stat-sub"><?php esc_html_e( 'confirmed by IP range or reverse DNS', 'ai-parseable' ); ?></span></div>
					</div>
					<section class="aip-panel">
						<header class="aip-panel-head"><h2><?php esc_html_e( 'Sites', 'ai-parseable' ); ?></h2><span class="aip-muted"><?php esc_html_e( 'Settings are configured on each site.', 'ai-parseable' ); ?></span></header>
						<table class="aip-table">
							<thead><tr><th><?php esc_html_e( 'Site', 'ai-parseable' ); ?></th><th class="aip-num"><?php esc_html_e( 'Visits', 'ai-parseable' ); ?></th><th class="aip-num"><?php esc_html_e( 'Verified', 'ai-parseable' ); ?></th><th></th></tr></thead>
							<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['name'] ); ?></strong> <span class="aip-muted"><?php echo esc_html( $row['host'] ); ?></span></td>
									<td class="aip-num"><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td>
									<td class="aip-num"><?php echo esc_html( number_format_i18n( $row['verified'] ) ); ?></td>
									<td class="aip-num"><a href="<?php echo esc_url( $row['url'] ); ?>"><?php esc_html_e( 'Open dashboard →', 'ai-parseable' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</section>
				</div>
			</div>
		</div>
		<?php
	}
}
