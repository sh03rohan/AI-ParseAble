<?php
/**
 * Admin-side health checks.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Admin;

use AiParseAble\Activation;
use AiParseAble\Logger\Ingest;
use AiParseAble\Module;
use AiParseAble\Support\Options;

/**
 * Runs the drop-in check on every admin load (transient-cached for an hour) and self-heals.
 * Notices are shown on our own screen only, one at a time, dismissible per user.
 */
final class Health implements Module {

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
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * At most one notice, on our screen only.
	 *
	 * @return void
	 */
	public function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, Admin::SLUG ) ) {
			return;
		}
		if ( ! current_user_can( Activation::CAPABILITY ) ) {
			return;
		}
		$notice = $this->first_notice();
		if ( null === $notice ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible" data-ai-parseable-notice="%2$s"><p>%3$s</p></div>',
			esc_attr( $notice['level'] ),
			esc_attr( $notice['id'] ),
			wp_kses(
				$notice['text'],
				array(
					'code' => array(),
					'a'    => array( 'href' => array() ),
				)
			)
		);
	}

	/**
	 * Highest-priority undismissed notice.
	 *
	 * @return array<mixed>|null
	 */
	private function first_notice(): ?array {
		$user = get_current_user_id();
		$list = array();

		$last = (int) get_option( Ingest::OPTION_LAST, 0 );
		if ( $last > 0 && ( time() - $last ) > HOUR_IN_SECONDS ) {
			$list[] = array(
				'id'    => 'ingest',
				'level' => 'warning',
				'text'  => sprintf(
					/* translators: %s: human-readable time difference, e.g. "3 hours" */
					__( 'The last crawler-log ingest ran %s ago. WP-Cron may not be firing on this site; consider a real cron job or Action Scheduler.', 'ai-parseable' ),
					human_time_diff( $last )
				),
			);
		}

		if ( $this->options->get( 'physical_robots', false ) ) {
			$list[] = array(
				'id'    => 'robots-physical',
				'level' => 'info',
				'text'  => __( 'A physical robots.txt exists at the web root, so WordPress does not serve the virtual one this plugin writes to. Open the Crawlers tab to manage a block inside the physical file.', 'ai-parseable' ),
			);
		}

		foreach ( $list as $n ) {
			if ( ! get_user_meta( $user, 'ai_parseable_dismissed_' . $n['id'], true ) ) {
				return $n;
			}
		}
		return null;
	}
}
