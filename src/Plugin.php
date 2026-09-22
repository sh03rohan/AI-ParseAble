<?php
/**
 * Container and module registration.
 *
 * @package AiParseAble
 */

namespace AiParseAble;

use AiParseAble\Admin\Admin;
use AiParseAble\Admin\Health;
use AiParseAble\Admin\NetworkAdmin;
use AiParseAble\Editor\Editor;
use AiParseAble\LlmsTxt\LlmsTxt;
use AiParseAble\Logger\Buffer;
use AiParseAble\Logger\Collector;
use AiParseAble\Logger\Coverage;
use AiParseAble\Logger\Ingest;
use AiParseAble\Logger\Queue;
use AiParseAble\Logger\Ranges;
use AiParseAble\Logger\Repository;
use AiParseAble\Logger\Rollup;
use AiParseAble\Logger\Verifier;
use AiParseAble\Rest\Rest;
use AiParseAble\Robots\Robots;
use AiParseAble\Schema\Schema;
use AiParseAble\Support\Cron;
use AiParseAble\Support\Options;
use AiParseAble\Support\Privacy;

/**
 * Hand-wired container. Small enough that a DI library would cost more than it saves.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Options accessor.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Repository (all SQL).
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Queue file helper.
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * Coverage report.
	 *
	 * @var Coverage
	 */
	private $coverage;

	/**
	 * Cron scheduler.
	 *
	 * @var Cron
	 */
	private $cron;

	/**
	 * Boot the plugin. Called once from the bootstrap file.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = new self();
		self::$instance->run();
	}

	/**
	 * Access the container. Used by Activation and uninstall only.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire the shared services. No hooks here.
	 */
	private function __construct() {
		$this->options    = new Options();
		$this->repository = new Repository();
		$this->queue      = new Queue( $this->options );
		$this->coverage   = new Coverage();
		$this->cron       = new Cron();
	}

	/**
	 * Options service.
	 *
	 * @return Options
	 */
	public function options(): Options {
		return $this->options;
	}

	/**
	 * Repository service.
	 *
	 * @return Repository
	 */
	public function repository(): Repository {
		return $this->repository;
	}

	/**
	 * Queue service.
	 *
	 * @return Queue
	 */
	public function queue(): Queue {
		return $this->queue;
	}

	/**
	 * Coverage report.
	 *
	 * @return Coverage
	 */
	public function coverage(): Coverage {
		return $this->coverage;
	}

	/**
	 * Cron service.
	 *
	 * @return Cron
	 */
	public function cron(): Cron {
		return $this->cron;
	}

	/**
	 * Register modules according to the conditional load map.
	 *
	 * On a front-end request only the collector (plus three negligible-cost hooks) is registered.
	 *
	 * @return void
	 */
	private function run(): void {
		// Upgrades never rely on the activation hook: it does not fire on background updates.
		add_action( 'plugins_loaded', array( Migrations::class, 'maybe_run' ), 1 );

		// Multisite lifecycle.
		add_action( 'wp_initialize_site', array( Activation::class, 'on_initialize_site' ), 10, 1 );
		add_action( 'wp_uninitialize_site', array( Activation::class, 'on_uninitialize_site' ), 10, 1 );

		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		// The REST routes are hooked in every context: rest_do_request() can be called internally from
		// admin screens, WP-CLI, tests or other plugins, and rest_api_init never fires on an ordinary page
		// load, so the only cost on the front end is this one add_action(). The module itself is built lazily.
		add_action(
			'rest_api_init',
			function () {
				( new Rest( $this->options, $this->repository, $this->queue, $this->coverage, $this->cron ) )->routes();
			}
		);

		if ( is_admin() && ! wp_doing_ajax() ) {
			$this->register_admin_modules();
			$this->register_background_modules();
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$this->register_background_modules();
			return;
		}

		// REST requests are front-end in WordPress' eyes but they carry the dashboard traffic.
		// The background set includes the REST module and the cron handlers Action Scheduler may run.
		if ( $is_rest || $this->looks_like_rest_request() ) {
			$this->register_background_modules();
		}

		$this->register_frontend_modules();
	}

	/**
	 * Front-end: collector and nothing more.
	 *
	 * @return void
	 */
	private function register_frontend_modules(): void {
		( new Collector( $this->options, new Buffer( $this->queue ) ) )->register();
		( new Robots( $this->options ) )->register();
		( new Schema( $this->options ) )->register();
		( new LlmsTxt( $this->options ) )->register();
		( new Privacy( $this->options ) )->register();
	}

	/**
	 * Background: cron handlers, ingest, rollup, range fetching.
	 *
	 * @return void
	 */
	private function register_background_modules(): void {
		$verifier = new Verifier( new Ranges() );
		( new Ingest( $this->options, $this->repository, $this->queue, $verifier ) )->register();
		( new Rollup( $this->options, $this->repository ) )->register();
		( new Ranges() )->register();
		$this->cron->register();
		( new LlmsTxt( $this->options ) )->register(); // Hook-only; any context that can flush rewrite rules must know ours.
		( new Privacy( $this->options ) )->register();
	}

	/**
	 * Admin: screens, notices, health checks, editor panel.
	 *
	 * @return void
	 */
	private function register_admin_modules(): void {
		( new Admin( $this->options ) )->register();
		( new Health( $this->options ) )->register();
		( new Editor() )->register();
		( new Robots( $this->options ) )->register();
		( new LlmsTxt( $this->options ) )->register();
		if ( is_multisite() ) {
			( new NetworkAdmin( $this->repository ) )->register();
		}
	}

	/**
	 * REST_REQUEST is defined late (in rest_api_loaded), so detect by path at boot time.
	 *
	 * @return bool
	 */
	private function looks_like_rest_request(): bool {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read-only path check, not output.
		if ( '' === $uri ) {
			return false;
		}
		$prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
		return false !== strpos( $uri, '/' . $prefix . '/' ) || false !== strpos( $uri, 'rest_route=' );
	}
}
