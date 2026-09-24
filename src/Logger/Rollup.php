<?php
/**
 * Nightly retention job.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Logger;

use CrawlLedger\Module;
use CrawlLedger\Support\Cron;
use CrawlLedger\Support\Lock;
use CrawlLedger\Support\Options;

/**
 * Daily counts are maintained at ingest time, so the nightly job only has to delete raw rows past
 * the retention window — in chunks, with a pause between them. Never one giant DELETE on shared hosting.
 */
final class Rollup implements Module {

	const LOCK = 'crawlledger_rollup_lock';

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Options    $options    Settings.
	 * @param Repository $repository Repository.
	 */
	public function __construct( Options $options, Repository $repository ) {
		$this->options    = $options;
		$this->repository = $repository;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( Cron::ROLLUP, array( $this, 'handle' ) );
	}

	/**
	 * Cron entry point.
	 *
	 * @return void
	 */
	public function handle(): void {
		$this->run();
	}

	/**
	 * Run for the current site, or for every site when on a network's main site.
	 *
	 * @return int Rows deleted.
	 */
	public function run(): int {
		if ( ! Lock::acquire( self::LOCK, 900 ) ) {
			return 0;
		}
		$deleted = 0;
		try {
			if ( is_multisite() && is_main_site() ) {
				$sites = get_sites(
					array(
						'number' => 10000,
						'fields' => 'ids',
					)
				);
				foreach ( (array) $sites as $blog_id ) {
					switch_to_blog( (int) $blog_id );
					$this->options->reset();
					try {
						$deleted += $this->purge_current_site();
					} finally {
						restore_current_blog();
						$this->options->reset();
					}
				}
			} else {
				$deleted = $this->purge_current_site();
			}
		} finally {
			Lock::release( self::LOCK );
		}
		return $deleted;
	}

	/**
	 * Purge raw rows older than the retention window for the current site.
	 *
	 * @return int
	 */
	public function purge_current_site(): int {
		if ( ! $this->repository->tables_exist() ) {
			return 0;
		}
		$days   = max( 1, (int) $this->options->get( 'retention_days', 7 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return $this->repository->purge_before( $cutoff );
	}

	/**
	 * Aggregate a set of raw rows into daily buckets. Pure, so it can be unit tested.
	 *
	 * @param array<mixed> $rows Each with hit_at, bot_id, status, verified.
	 * @return array<mixed> List of day/bot_id/status_bucket/verified/hits.
	 */
	public static function aggregate( array $rows ): array {
		$daily = array();
		foreach ( $rows as $row ) {
			$day    = substr( (string) $row['hit_at'], 0, 10 );
			$bucket = Repository::bucket( (int) $row['status'] );
			$v      = ! empty( $row['verified'] ) ? 1 : 0;
			$key    = $day . '|' . (int) $row['bot_id'] . '|' . $bucket . '|' . $v;
			if ( ! isset( $daily[ $key ] ) ) {
				$daily[ $key ] = array(
					'day'           => $day,
					'bot_id'        => (int) $row['bot_id'],
					'status_bucket' => $bucket,
					'verified'      => $v,
					'hits'          => 0,
				);
			}
			++$daily[ $key ]['hits'];
		}
		return array_values( $daily );
	}
}
