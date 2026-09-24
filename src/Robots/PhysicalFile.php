<?php
/**
 * Physical robots.txt handling.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Robots;

/**
 * WordPress only serves a virtual robots.txt when no physical file exists. When one does, the
 * robots_txt filter is silently dead — so we detect it and offer to manage a marked block inside it.
 */
final class PhysicalFile {

	/**
	 * Path at the web root.
	 *
	 * @return string
	 */
	public static function path(): string {
		return trailingslashit( ABSPATH ) . 'robots.txt';
	}

	/**
	 * Whether a physical file exists.
	 *
	 * @return bool
	 */
	public static function exists(): bool {
		return is_file( self::path() );
	}

	/**
	 * Contents, or '' when absent.
	 *
	 * @return string
	 */
	public static function read(): string {
		return self::exists() ? (string) file_get_contents( self::path() ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file at the web root.
	}

	/**
	 * Whether we could write to it.
	 *
	 * @return bool
	 */
	public static function writable(): bool {
		return self::exists() ? wp_is_writable( self::path() ) : wp_is_writable( ABSPATH );
	}

	/**
	 * Write the managed block into the physical file. Explicit user action only.
	 *
	 * @param string $rules Rules for the block; empty removes it.
	 * @return bool|\WP_Error
	 */
	public static function write_block( string $rules ) {
		if ( ! self::exists() ) {
			return new \WP_Error( 'no_file', __( 'There is no physical robots.txt to update.', 'crawlledger-ai-crawler-log' ), array( 'status' => 400 ) );
		}
		if ( ! self::writable() ) {
			return new \WP_Error( 'not_writable', __( 'robots.txt is not writable by the web server.', 'crawlledger-ai-crawler-log' ), array( 'status' => 400 ) );
		}
		$updated = Block::splice( self::read(), $rules );
		$ok      = self::filesystem() && $GLOBALS['wp_filesystem']->put_contents( self::path(), $updated, FS_CHMOD_FILE );
		return $ok ? true : new \WP_Error( 'write_failed', __( 'Writing robots.txt failed.', 'crawlledger-ai-crawler-log' ), array( 'status' => 500 ) );
	}

	/**
	 * Initialise the direct WP_Filesystem method. Anything needing credentials is reported as not writable.
	 *
	 * @return bool
	 */
	private static function filesystem(): bool {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return (bool) WP_Filesystem() && isset( $GLOBALS['wp_filesystem'] ) && 'direct' === $GLOBALS['wp_filesystem']->method;
	}
}
