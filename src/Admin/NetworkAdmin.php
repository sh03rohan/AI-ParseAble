<?php
/**
 * Network overview.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Admin;

use CrawlLedger\Logger\Repository;
use CrawlLedger\Module;

/**
 * Network admins get a read-only overview across sites. Configuration stays per site.
 * Rendered in PHP with the dashboard's stylesheet so it matches, without loading the React bundle.
 */
final class NetworkAdmin implements Module {

	const SLUG = 'crawlledger-network';

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
			__( 'CrawlLedger', 'crawlledger-ai-crawler-log' ),
			__( 'CrawlLedger', 'crawlledger-ai-crawler-log' ),
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
		if ( ! $this->is_ours( (string) $hook ) || ! is_readable( CRAWLLEDGER_DIR . 'assets/admin.css' ) ) {
			return;
		}
		wp_enqueue_style( 'crawlledger-admin', CRAWLLEDGER_URL . 'assets/admin.css', array(), CRAWLLEDGER_VERSION );
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
			$classes .= ' crawlledger-screen';
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
			wp_die( esc_html__( 'You do not have permission to view this page.', 'crawlledger-ai-crawler-log' ) );
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
			<div class="clg-app clg-app--static">
				<div class="clg-static-head">
					<img class="clg-brand-mark" src="<?php echo esc_url( CRAWLLEDGER_URL . 'images/logo-64.png' ); ?>" alt="" width="28" height="28" />
					<h1><?php esc_html_e( 'CrawlLedger', 'crawlledger-ai-crawler-log' ); ?></h1>
					<span class="clg-version">v<?php echo esc_html( CRAWLLEDGER_VERSION ); ?></span>
					<span class="clg-muted"><?php esc_html_e( 'Network overview', 'crawlledger-ai-crawler-log' ); ?></span>
				</div>
				<div class="clg-main">
					<div class="clg-stats clg-stats--3">
						<div class="clg-stat"><span class="clg-stat-label"><?php esc_html_e( 'Sites', 'crawlledger-ai-crawler-log' ); ?></span><span class="clg-stat-row"><span class="clg-stat-value"><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></span></span><span class="clg-stat-sub"><?php esc_html_e( 'in this network', 'crawlledger-ai-crawler-log' ); ?></span></div>
						<div class="clg-stat"><span class="clg-stat-label"><?php esc_html_e( 'Crawler visits', 'crawlledger-ai-crawler-log' ); ?></span><span class="clg-stat-row"><span class="clg-stat-value"><?php echo esc_html( number_format_i18n( $sum['total'] ) ); ?></span></span><span class="clg-stat-sub"><?php esc_html_e( 'last 7 days, all sites', 'crawlledger-ai-crawler-log' ); ?></span></div>
						<div class="clg-stat"><span class="clg-stat-label"><?php esc_html_e( 'Verified', 'crawlledger-ai-crawler-log' ); ?></span><span class="clg-stat-row"><span class="clg-stat-value"><?php echo esc_html( number_format_i18n( $sum['verified'] ) ); ?></span></span><span class="clg-stat-sub"><?php esc_html_e( 'confirmed by IP range or reverse DNS', 'crawlledger-ai-crawler-log' ); ?></span></div>
					</div>
					<section class="clg-panel">
						<header class="clg-panel-head"><h2><?php esc_html_e( 'Sites', 'crawlledger-ai-crawler-log' ); ?></h2><span class="clg-muted"><?php esc_html_e( 'Settings are configured on each site.', 'crawlledger-ai-crawler-log' ); ?></span></header>
						<table class="clg-table">
							<thead><tr><th><?php esc_html_e( 'Site', 'crawlledger-ai-crawler-log' ); ?></th><th class="clg-num"><?php esc_html_e( 'Visits', 'crawlledger-ai-crawler-log' ); ?></th><th class="clg-num"><?php esc_html_e( 'Verified', 'crawlledger-ai-crawler-log' ); ?></th><th></th></tr></thead>
							<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['name'] ); ?></strong> <span class="clg-muted"><?php echo esc_html( $row['host'] ); ?></span></td>
									<td class="clg-num"><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td>
									<td class="clg-num"><?php echo esc_html( number_format_i18n( $row['verified'] ) ); ?></td>
									<td class="clg-num"><a href="<?php echo esc_url( $row['url'] ); ?>"><?php esc_html_e( 'Open dashboard →', 'crawlledger-ai-crawler-log' ); ?></a></td>
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
