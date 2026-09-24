<?php
/**
 * All SQL lives here, nowhere else.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Logger;

/**
 * Two-tier storage: raw hits (short retention) and daily aggregates (kept forever).
 */
final class Repository {

	const BATCH = 500;

	/**
	 * Raw hits table name for the current site.
	 *
	 * @return string
	 */
	public function hits_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'crawlledger_hits';
	}

	/**
	 * Daily aggregates table name for the current site.
	 *
	 * @return string
	 */
	public function daily_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'crawlledger_daily';
	}

	/**
	 * Schema for dbDelta(). Two spaces after PRIMARY KEY, KEY not INDEX, lowercase types — dbDelta is particular.
	 *
	 * @return string[]
	 */
	public function schema(): array {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$hits    = $this->hits_table();
		$daily   = $this->daily_table();

		return array(
			"CREATE TABLE {$hits} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  hit_at datetime NOT NULL,
  bot_id smallint(5) unsigned NOT NULL,
  url_hash binary(8) NOT NULL,
  url varchar(512) NOT NULL,
  status smallint(5) unsigned NOT NULL,
  ip varbinary(16) NOT NULL,
  verified tinyint(1) NOT NULL DEFAULT 0,
  is_cached tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY bot_time (bot_id,hit_at),
  KEY time_status (hit_at,status),
  KEY url_bot (url_hash,bot_id)
) {$charset};",
			"CREATE TABLE {$daily} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  day date NOT NULL,
  bot_id smallint(5) unsigned NOT NULL,
  status_bucket smallint(5) unsigned NOT NULL,
  verified tinyint(1) NOT NULL DEFAULT 0,
  hits int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY day_bot (day,bot_id,status_bucket,verified),
  KEY bot_day (bot_id,day)
) {$charset};",
		);
	}

	/**
	 * Create or update tables.
	 *
	 * @return void
	 */
	public function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $this->schema() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Drop tables.
	 *
	 * @return void
	 */
	public function drop(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $this->hits_table() ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table name from prefix.
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $this->daily_table() ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Whether both tables exist.
	 *
	 * @return bool
	 */
	public function tables_exist(): bool {
		global $wpdb;
		$hits  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->hits_table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$daily = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->daily_table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $hits === $this->hits_table() && $daily === $this->daily_table();
	}

	/**
	 * Batched multi-row insert.
	 *
	 * @param array<mixed> $rows Each: hit_at, bot_id, url_hash (8 raw bytes), url, status, ip (raw bytes), verified, is_cached.
	 * @return int Rows inserted.
	 */
	public function insert_hits( array $rows ): int {
		global $wpdb;
		$inserted = 0;
		foreach ( array_chunk( $rows, self::BATCH ) as $chunk ) {
			$placeholders = array();
			$values       = array();
			foreach ( $chunk as $row ) {
				$placeholders[] = '(%s,%d,%s,%s,%d,%s,%d,%d)';
				$values[]       = $row['hit_at'];
				$values[]       = (int) $row['bot_id'];
				$values[]       = $row['url_hash'];
				$values[]       = $row['url'];
				$values[]       = (int) $row['status'];
				$values[]       = $row['ip'];
				$values[]       = (int) $row['verified'];
				$values[]       = (int) $row['is_cached'];
			}
			$sql    = 'INSERT INTO `' . esc_sql( $this->hits_table() ) . '` (hit_at,bot_id,url_hash,url,status,ip,verified,is_cached) VALUES ' . implode( ',', $placeholders );
			$result = $wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- placeholders built from row count; every value goes through prepare().
			if ( false !== $result ) {
				$inserted += (int) $result;
			}
		}
		return $inserted;
	}

	/**
	 * Add counts to the daily table.
	 *
	 * @param array<mixed> $aggregates Each: day (Y-m-d), bot_id, status_bucket, verified, hits.
	 * @return void
	 */
	public function upsert_daily( array $aggregates ): void {
		global $wpdb;
		foreach ( array_chunk( $aggregates, self::BATCH ) as $chunk ) {
			$placeholders = array();
			$values       = array();
			foreach ( $chunk as $agg ) {
				$placeholders[] = '(%s,%d,%d,%d,%d)';
				$values[]       = $agg['day'];
				$values[]       = (int) $agg['bot_id'];
				$values[]       = (int) $agg['status_bucket'];
				$values[]       = (int) $agg['verified'];
				$values[]       = (int) $agg['hits'];
			}
			$sql = 'INSERT INTO `' . esc_sql( $this->daily_table() ) . '` (day,bot_id,status_bucket,verified,hits) VALUES ' . implode( ',', $placeholders ) . ' ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)';
			$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- placeholders built from row count; every value goes through prepare().
		}
	}

	/**
	 * Status bucket: 200, 300, 400, 500.
	 *
	 * @param int $status HTTP status.
	 * @return int
	 */
	public static function bucket( int $status ): int {
		if ( $status < 200 ) {
			return 200;
		}
		return (int) ( floor( $status / 100 ) * 100 );
	}

	/**
	 * Daily series for a range. Reads _daily only: a 90-day chart must never scan a million rows.
	 *
	 * @param int  $days          Number of days ending today.
	 * @param bool $verified_only Verified hits only.
	 * @return array<mixed> day => ['hits' => int, 'ok' => int, 'errors' => int, 'bots' => [bot_id => hits]]
	 */
	public function series( int $days, bool $verified_only ): array {
		global $wpdb;
		$from  = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$table = esc_sql( $this->daily_table() );
		$sql   = "SELECT day, bot_id, status_bucket, verified, hits FROM `{$table}` WHERE day >= %s";
		$args  = array( $from );
		if ( $verified_only ) {
			$sql .= ' AND verified = 1';
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- static SQL, esc_sql() table name, prepared values.

		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day            = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$series[ $day ] = array(
				'hits'   => 0,
				'ok'     => 0,
				'errors' => 0,
				'bots'   => array(),
			);
		}
		foreach ( (array) $rows as $row ) {
			$day = $row['day'];
			if ( ! isset( $series[ $day ] ) ) {
				continue;
			}
			$hits                    = (int) $row['hits'];
			$series[ $day ]['hits'] += $hits;
			$bucket                  = (int) $row['status_bucket'];
			if ( $bucket >= 400 ) {
				$series[ $day ]['errors'] += $hits;
			} else {
				$series[ $day ]['ok'] += $hits;
			}
			$bot = (int) $row['bot_id'];
			if ( ! isset( $series[ $day ]['bots'][ $bot ] ) ) {
				$series[ $day ]['bots'][ $bot ] = 0;
			}
			$series[ $day ]['bots'][ $bot ] += $hits;
		}
		return $series;
	}

	/**
	 * Per-bot summary over a range from _daily.
	 *
	 * @param int $days Range.
	 * @return array<mixed> bot_id => ['total','verified','errors','last_day']
	 */
	public function bots_summary( int $days ): array {
		global $wpdb;
		$from  = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$table = esc_sql( $this->daily_table() );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT bot_id, SUM(hits) AS total, SUM(CASE WHEN verified = 1 THEN hits ELSE 0 END) AS verified, SUM(CASE WHEN status_bucket >= 400 THEN hits ELSE 0 END) AS errors, MAX(day) AS last_day FROM `{$table}` WHERE day >= %s GROUP BY bot_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
				$from
			),
			ARRAY_A
		);
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['bot_id'] ] = array(
				'total'    => (int) $row['total'],
				'verified' => (int) $row['verified'],
				'errors'   => (int) $row['errors'],
				'last_day' => (string) $row['last_day'],
			);
		}
		return $out;
	}

	/**
	 * Most recent hit per bot from the raw table (precise timestamps, short window only).
	 *
	 * @param int $days Window, capped at 7.
	 * @return array<mixed> bot_id => datetime
	 */
	public function last_seen( int $days = 7 ): array {
		global $wpdb;
		$days  = min( 7, max( 1, $days ) );
		$from  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table = esc_sql( $this->hits_table() );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT bot_id, MAX(hit_at) AS last_at FROM `{$table}` WHERE hit_at >= %s GROUP BY bot_id", $from ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['bot_id'] ] = (string) $row['last_at'];
		}
		return $out;
	}

	/**
	 * URLs returning errors to crawlers, from the raw table (7-day window max).
	 *
	 * @param int  $days          Window, capped at 7.
	 * @param bool $verified_only Verified only.
	 * @param int  $limit         Rows.
	 * @return array<mixed>
	 */
	public function failing_urls( int $days, bool $verified_only, int $limit = 25 ): array {
		global $wpdb;
		$days  = min( 7, max( 1, $days ) );
		$from  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table = esc_sql( $this->hits_table() );
		$sql   = "SELECT url_hash, MIN(url) AS url, COUNT(*) AS hits, MAX(status) AS status, COUNT(DISTINCT bot_id) AS bots, MAX(hit_at) AS last_at FROM `{$table}` WHERE hit_at >= %s AND status >= 400";
		$args  = array( $from );
		if ( $verified_only ) {
			$sql .= ' AND verified = 1';
		}
		$sql   .= ' GROUP BY url_hash ORDER BY hits DESC LIMIT %d';
		$args[] = $limit;
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- static SQL, esc_sql() table name, prepared values.
		$out    = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'url'     => (string) $row['url'],
				'hits'    => (int) $row['hits'],
				'status'  => (int) $row['status'],
				'bots'    => (int) $row['bots'],
				'last_at' => (string) $row['last_at'],
			);
		}
		return $out;
	}

	/**
	 * Top URLs by crawler hits (raw table, 7-day max).
	 *
	 * @param int  $days          Window.
	 * @param bool $verified_only Verified only.
	 * @param int  $limit         Rows.
	 * @return array<mixed>
	 */
	public function top_urls( int $days, bool $verified_only, int $limit = 25 ): array {
		global $wpdb;
		$days  = min( 7, max( 1, $days ) );
		$from  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table = esc_sql( $this->hits_table() );
		$sql   = "SELECT url_hash, MIN(url) AS url, COUNT(*) AS hits, COUNT(DISTINCT bot_id) AS bots FROM `{$table}` WHERE hit_at >= %s";
		$args  = array( $from );
		if ( $verified_only ) {
			$sql .= ' AND verified = 1';
		}
		$sql   .= ' GROUP BY url_hash ORDER BY hits DESC LIMIT %d';
		$args[] = $limit;
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- static SQL, esc_sql() table name, prepared values.
		$out    = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'url'  => (string) $row['url'],
				'hits' => (int) $row['hits'],
				'bots' => (int) $row['bots'],
			);
		}
		return $out;
	}

	/**
	 * Latest raw hits.
	 *
	 * @param int $limit Rows.
	 * @return array<mixed>
	 */
	public function recent( int $limit = 50 ): array {
		global $wpdb;
		$table = esc_sql( $this->hits_table() );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT hit_at, bot_id, url, status, verified, is_cached FROM `{$table}` ORDER BY hit_at DESC, id DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return array_map(
			static function ( $row ) {
				return array(
					'hit_at'    => (string) $row['hit_at'],
					'bot_id'    => (int) $row['bot_id'],
					'url'       => (string) $row['url'],
					'status'    => (int) $row['status'],
					'verified'  => (bool) $row['verified'],
					'is_cached' => (bool) $row['is_cached'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Delete raw rows older than the cutoff in chunks, sleeping between them. Never one giant DELETE.
	 *
	 * @param string $cutoff     UTC datetime.
	 * @param int    $chunk      Rows per statement.
	 * @param int    $max_chunks Ceiling per run so a huge backlog is spread over several runs.
	 * @return int Rows deleted.
	 */
	public function purge_before( string $cutoff, int $chunk = 5000, int $max_chunks = 40 ): int {
		global $wpdb;
		$table   = esc_sql( $this->hits_table() );
		$deleted = 0;
		for ( $i = 0; $i < $max_chunks; $i++ ) {
			// No ORDER BY: it would filesort every matching row per chunk; the index range is the order we want.
			$n = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE hit_at < %s LIMIT %d", $cutoff, $chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $n ) {
				break;
			}
			$deleted += (int) $n;
			if ( $n < $chunk ) {
				break;
			}
			usleep( 250000 );
		}
		return $deleted;
	}

	/**
	 * Row counts and on-disk size, for the dashboard.
	 *
	 * @return array<mixed>
	 */
	public function sizes(): array {
		global $wpdb;
		$out = array();
		foreach ( array(
			'hits'  => $this->hits_table(),
			'daily' => $this->daily_table(),
		) as $key => $table ) {
			$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT TABLE_ROWS AS rows_estimate, (DATA_LENGTH + INDEX_LENGTH) AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
					DB_NAME,
					$table
				),
				ARRAY_A
			);
			$rows  = $row ? (int) $row['rows_estimate'] : 0;
			$bytes = $row ? (int) $row['bytes'] : 0;
			// TABLE_ROWS is an InnoDB estimate and lags behind; count exactly while the table is small.
			if ( $bytes < 50 * 1024 * 1024 ) {
				$rows = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			}
			$out[ $key ] = array(
				'rows'  => $rows,
				'bytes' => $bytes,
			);
		}
		return $out;
	}

	/**
	 * Totals from _daily for a range (used by the network overview).
	 *
	 * @param int $days Range.
	 * @return array{total:int, verified:int}
	 */
	public function totals( int $days ): array {
		global $wpdb;
		$from  = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$table = esc_sql( $this->daily_table() );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT SUM(hits) AS total, SUM(CASE WHEN verified = 1 THEN hits ELSE 0 END) AS verified FROM `{$table}` WHERE day >= %s", $from ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return array(
			'total'    => $row ? (int) $row['total'] : 0,
			'verified' => $row ? (int) $row['verified'] : 0,
		);
	}

	/**
	 * Remove every row from both tables.
	 *
	 * @return void
	 */
	public function truncate(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE `' . esc_sql( $this->hits_table() ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'TRUNCATE TABLE `' . esc_sql( $this->daily_table() ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	}
}
