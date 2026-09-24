<?php
/**
 * Cross-process lock.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Support;

/**
 * The function wp_cache_add() is atomic only with a persistent object cache. Without one it is per-process and
 * useless as a lock, so fall back to an option row whose staleness is checked explicitly.
 */
final class Lock {

	/**
	 * Object-cache group for locks.
	 */
	const GROUP = 'crawlledger';

	/**
	 * Take a lock.
	 *
	 * @param string $name Lock name.
	 * @param int    $ttl  Seconds before a held lock is considered stale.
	 * @return bool
	 */
	public static function acquire( string $name, int $ttl = 300 ): bool {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $name, 1, self::GROUP, $ttl );
		}

		$option = 'crawlledger_lock_' . sanitize_key( $name );
		$held   = (int) get_option( $option, 0 );
		if ( $held > 0 && ( time() - $held ) < $ttl ) {
			return false;
		}
		if ( $held > 0 ) {
			delete_option( $option );
		}
		return (bool) add_option( $option, time(), '', false );
	}

	/**
	 * Release.
	 *
	 * @param string $name Lock name.
	 * @return void
	 */
	public static function release( string $name ): void {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $name, self::GROUP );
			return;
		}
		delete_option( 'crawlledger_lock_' . sanitize_key( $name ) );
	}
}
