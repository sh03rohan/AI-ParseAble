<?php
/**
 * Queue file helper.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Logger;

use CrawlLedger\Support\Options;
use CrawlLedger\Support\Str;

/**
 * Hits are appended to daily files under uploads/crawlledger/queue/ and ingested by cron.
 *
 * File names carry a random token so they cannot be guessed; the directory carries an index.php and a
 * .htaccess deny rule. Records are tab-separated, one per line.
 */
final class Queue {

	const MAX_FILE_BYTES = 52428800; // 50 MB per day: self-defence against a flood.

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
	 * Base directory (uploads/crawlledger).
	 *
	 * On multisite the queue is network-wide, so it lives under the main site's uploads.
	 *
	 * @return string
	 */
	public function base_dir(): string {
		if ( is_multisite() && ! is_main_site() ) {
			switch_to_blog( get_main_site_id() );
			try {
				$uploads = wp_upload_dir( null, false );
			} finally {
				restore_current_blog();
			}
		} else {
			$uploads = wp_upload_dir( null, false );
		}
		return trailingslashit( $uploads['basedir'] ) . 'crawlledger';
	}

	/**
	 * Queue directory.
	 *
	 * @return string
	 */
	public function dir(): string {
		return $this->base_dir() . '/queue';
	}

	/**
	 * Random token baked into file names. Created once.
	 *
	 * @return string
	 */
	public function token(): string {
		// The queue is network-wide, so the token in its file names must be too: read it from the main site.
		if ( is_multisite() && ! is_main_site() ) {
			switch_to_blog( get_main_site_id() );
			$this->options->reset();
			try {
				return $this->token();
			} finally {
				restore_current_blog();
				$this->options->reset();
			}
		}
		$token = (string) $this->options->get( 'queue_token', '' );
		if ( '' === $token ) {
			$token = Str::token( 16 );
			$this->options->set( 'queue_token', $token );
		}
		return $token;
	}

	/**
	 * Today's file for a given token.
	 *
	 * @return string
	 */
	public function current_file(): string {
		return $this->dir() . '/' . gmdate( 'Y-m-d' ) . '-' . $this->token() . '.log';
	}

	/**
	 * Create the directory with protection files. Returns whether it is usable.
	 *
	 * @return bool
	 */
	public function ensure(): bool {
		$base = $this->base_dir();
		$dir  = $this->dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$protect = array(
			$base . '/index.php' => "<?php\n// Silence is golden.\n",
			$base . '/.htaccess' => "# CrawlLedger: crawler logs are private.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
			$dir . '/index.php'  => "<?php\n// Silence is golden.\n",
		);
		foreach ( $protect as $path => $body ) {
			if ( ! file_exists( $path ) ) {
				@file_put_contents( $path, $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- protection files, best effort.
			}
		}
		return is_dir( $dir ) && wp_is_writable( $dir );
	}

	/**
	 * Append one already-formatted line. Sub-millisecond; safe under concurrency thanks to LOCK_EX.
	 *
	 * @param string $line Line without trailing newline.
	 * @return bool
	 */
	public function append( string $line ): bool {
		$file = $this->current_file();
		$dir  = dirname( $file );
		if ( ! is_dir( $dir ) && ! $this->ensure() ) {
			return false;
		}
		if ( is_file( $file ) && filesize( $file ) > self::MAX_FILE_BYTES ) {
			return false;
		}
		return false !== @file_put_contents( $file, $line . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- hot path; WP_Filesystem is far too heavy here.
	}

	/**
	 * Claim files for ingest: rename each *.log to *.processing so writers move to a fresh file.
	 *
	 * @param int $limit Max files per run.
	 * @return string[] Paths of claimed files.
	 */
	public function claim( int $limit = 20 ): array {
		$dir = $this->dir();
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$claimed = array();
		// Pick up files abandoned by a crashed run first.
		foreach ( (array) glob( $dir . '/*.processing' ) as $stale ) {
			if ( filemtime( $stale ) < time() - 15 * MINUTE_IN_SECONDS ) {
				$claimed[] = $stale;
			}
		}
		foreach ( (array) glob( $dir . '/*.log' ) as $file ) {
			if ( count( $claimed ) >= $limit ) {
				break;
			}
			$target = preg_replace( '~\.log$~', '.' . Str::token( 6 ) . '.processing', $file );
			if ( @rename( $file, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.rename_rename -- atomic claim; another run may have taken it. WP_Filesystem::move() is not atomic.
				$claimed[] = $target;
			}
		}
		return $claimed;
	}

	/**
	 * Remove a processed file.
	 *
	 * @param string $file Path.
	 * @return void
	 */
	public function discard( string $file ): void {
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Number of pending files and their bytes, for the dashboard.
	 *
	 * @return array{files:int, bytes:int}
	 */
	public function pending(): array {
		$dir   = $this->dir();
		$files = is_dir( $dir ) ? (array) glob( $dir . '/*.log' ) : array();
		$bytes = 0;
		foreach ( $files as $f ) {
			$bytes += (int) filesize( $f );
		}
		return array(
			'files' => count( $files ),
			'bytes' => $bytes,
		);
	}

	/**
	 * Whether the base directory can be fetched over HTTP (it must not be).
	 *
	 * @return bool|null True when readable, false when denied, null when the check could not run.
	 */
	public function is_web_readable(): ?bool {
		$cached = get_transient( 'crawlledger_queue_readable' );
		if ( false !== $cached ) {
			return 'null' === $cached ? null : (bool) (int) $cached;
		}
		$result = $this->probe_web_readable();
		set_transient( 'crawlledger_queue_readable', null === $result ? 'null' : ( $result ? '1' : '0' ), HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Uncached HTTP probe of the log directory.
	 *
	 * @return bool|null
	 */
	private function probe_web_readable(): ?bool {
		$uploads = wp_upload_dir( null, false );
		$url     = trailingslashit( $uploads['baseurl'] ) . 'crawlledger/.htaccess';
		$res     = wp_remote_head(
			$url,
			array(
				'timeout'   => 4,
				'sslverify' => false,
			)
		);
		if ( is_wp_error( $res ) ) {
			return null;
		}
		return 200 === (int) wp_remote_retrieve_response_code( $res );
	}

	/**
	 * Delete the whole queue tree.
	 *
	 * @return void
	 */
	public function remove_all(): void {
		$base = $this->base_dir();
		if ( ! is_dir( $base ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- our own upload subtree.
			} else {
				wp_delete_file( $item->getPathname() );
			}
		}
		@rmdir( $base ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- our own upload subtree.
	}
}
