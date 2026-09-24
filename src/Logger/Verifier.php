<?php
/**
 * Crawler identity verification.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Logger;

use CrawlLedger\Support\Ip;
use CrawlLedger\Support\Str;

/**
 * Published IP ranges first; forward-confirmed reverse DNS second. Results are cached per /24 or /48
 * for a day. This runs in the ingest job only — never on a page request.
 */
final class Verifier {

	const CACHE_GROUP = 'crawlledger-verify';

	/**
	 * Range store.
	 *
	 * @var Ranges
	 */
	private $ranges;

	/**
	 * Optional DNS resolver override for tests: callable(string $ip): string hostname.
	 *
	 * @var callable|null
	 */
	private $reverse;

	/**
	 * Optional forward resolver override for tests: callable(string $host): string[] ips.
	 *
	 * @var callable|null
	 */
	private $forward;

	/**
	 * Constructor.
	 *
	 * @param Ranges        $ranges  Range store.
	 * @param callable|null $reverse Reverse resolver override.
	 * @param callable|null $forward Forward resolver override.
	 */
	public function __construct( Ranges $ranges, ?callable $reverse = null, ?callable $forward = null ) {
		$this->ranges  = $ranges;
		$this->reverse = $reverse;
		$this->forward = $forward;
	}

	/**
	 * Whether the IP genuinely belongs to the bot.
	 *
	 * @param int    $bot_id Bot id.
	 * @param string $ip     Remote address.
	 * @return bool
	 */
	public function verify( int $bot_id, string $ip ): bool {
		$bot = Signatures::get( $bot_id );
		if ( null === $bot || '' === $ip || ! Ip::is_valid( $ip ) ) {
			return false;
		}
		if ( Signatures::VERIFY_NONE === $bot['verify'] ) {
			return false; // Unverifiable by design; the UI explains this per vendor.
		}

		$key    = $bot_id . ':' . Ip::cache_key( $ip );
		$cached = $this->cache_get( $key );
		if ( null !== $cached ) {
			return $cached;
		}

		$result = false;
		if ( Signatures::VERIFY_RANGES === $bot['verify'] ) {
			$result = $this->in_ranges( $bot_id, $ip );
			if ( ! $result && ! empty( $bot['rdns'] ) ) {
				$result = $this->rdns_confirmed( $ip, (array) $bot['rdns'] );
			}
		} elseif ( Signatures::VERIFY_RDNS === $bot['verify'] ) {
			$result = $this->rdns_confirmed( $ip, (array) $bot['rdns'] );
		}

		$this->cache_set( $key, $result );
		return $result;
	}

	/**
	 * Range membership.
	 *
	 * @param int    $bot_id Bot id.
	 * @param string $ip     Address.
	 * @return bool
	 */
	public function in_ranges( int $bot_id, string $ip ): bool {
		foreach ( $this->ranges->for_bot( $bot_id ) as $cidr ) {
			if ( Ip::in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Forward-confirmed reverse DNS: ip -> host (must end with a known suffix) -> ips (must include ip).
	 * Skipping the forward step is the common mistake; it is what makes this non-spoofable.
	 *
	 * @param string   $ip       Address.
	 * @param string[] $suffixes Allowed hostname suffixes, with leading dot.
	 * @return bool
	 */
	public function rdns_confirmed( string $ip, array $suffixes ): bool {
		if ( ! $suffixes ) {
			return false;
		}
		$host = $this->reverse ? (string) call_user_func( $this->reverse, $ip ) : (string) @gethostbyaddr( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failures are expected.
		if ( '' === $host || $host === $ip ) {
			return false;
		}
		$host = rtrim( $host, '.' );
		$ok   = false;
		foreach ( $suffixes as $suffix ) {
			if ( Str::ends_with( $host, $suffix ) ) {
				$ok = true;
				break;
			}
		}
		if ( ! $ok ) {
			return false;
		}
		$ips = $this->forward ? (array) call_user_func( $this->forward, $host ) : $this->forward_lookup( $host );
		return in_array( $ip, $ips, true );
	}

	/**
	 * A and AAAA lookup.
	 *
	 * @param string $host Hostname.
	 * @return string[]
	 */
	private function forward_lookup( string $host ): array {
		$ips = array();
		$v4  = @gethostbynamel( $host ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_array( $v4 ) ) {
			$ips = $v4;
		}
		if ( function_exists( 'dns_get_record' ) ) {
			$v6 = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $v6 ) ) {
				foreach ( $v6 as $rec ) {
					if ( ! empty( $rec['ipv6'] ) ) {
						$ips[] = (string) $rec['ipv6'];
					}
				}
			}
		}
		return $ips;
	}

	/**
	 * Cache read. Object cache when persistent, transient otherwise.
	 *
	 * @param string $key Key.
	 * @return bool|null
	 */
	private function cache_get( string $key ): ?bool {
		if ( wp_using_ext_object_cache() ) {
			$v = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
			return $found ? (bool) $v : null;
		}
		$v = get_transient( 'crawlledger_v_' . md5( $key ) );
		return false === $v ? null : ( '1' === $v );
	}

	/**
	 * Cache write, 24 hours.
	 *
	 * @param string $key   Key.
	 * @param bool   $value Value.
	 * @return void
	 */
	private function cache_set( string $key, bool $value ): void {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $value ? 1 : 0, self::CACHE_GROUP, DAY_IN_SECONDS );
			return;
		}
		set_transient( 'crawlledger_v_' . md5( $key ), $value ? '1' : '0', DAY_IN_SECONDS );
	}
}
