<?php
/**
 * Queue record format.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Logger;

/**
 * Shared by the drop-in generator, the PHP collector and the ingest so the format lives in one place.
 *
 * Hit line:    <utc datetime>\t<token>\t<ip>\t<method>\t<uri>\t<host>\t<status>\t<cached>\t<elapsed_us>
 * Timing line: T\t<elapsed_us>              (sampled non-bot requests, for the performance figure)
 */
final class Record {

	const HIT_FIELDS = 9;

	/**
	 * Build a hit line from request state.
	 *
	 * @param string $token      Matched UA token.
	 * @param string $ip         Remote address.
	 * @param string $method     HTTP method.
	 * @param string $uri        Request URI.
	 * @param string $host       Host header.
	 * @param int    $status     Response status.
	 * @param int    $cached     1 when a cache header was seen.
	 * @param int    $elapsed_us Collector cost in microseconds.
	 * @return string
	 */
	public static function hit( string $token, string $ip, string $method, string $uri, string $host, int $status, int $cached, int $elapsed_us ): string {
		$clean = static function ( $v ) {
			return str_replace( array( "\t", "\n", "\r" ), ' ', (string) $v );
		};
		return implode(
			"\t",
			array(
				gmdate( 'Y-m-d H:i:s' ),
				$clean( $token ),
				$clean( $ip ),
				$clean( substr( $method, 0, 10 ) ),
				$clean( substr( $uri, 0, 1024 ) ),
				$clean( substr( $host, 0, 255 ) ),
				(int) $status,
				$cached ? 1 : 0,
				(int) $elapsed_us,
			)
		);
	}

	/**
	 * Timing line.
	 *
	 * @param int $elapsed_us Microseconds.
	 * @return string
	 */
	public static function timing( int $elapsed_us ): string {
		return "T\t" . (int) $elapsed_us;
	}

	/**
	 * Parse a line. Returns null for malformed input.
	 *
	 * @param string $line Raw line.
	 * @return array<mixed>|null ['type' => 'hit'|'timing', ...]
	 */
	public static function parse( string $line ): ?array {
		$line = rtrim( $line, "\r\n" );
		if ( '' === $line ) {
			return null;
		}
		$parts = explode( "\t", $line );
		if ( 'T' === $parts[0] ) {
			return isset( $parts[1] ) ? array(
				'type'       => 'timing',
				'elapsed_us' => (int) $parts[1],
			) : null;
		}
		if ( count( $parts ) < self::HIT_FIELDS ) {
			return null;
		}
		return array(
			'type'       => 'hit',
			'at'         => $parts[0],
			'token'      => $parts[1],
			'ip'         => $parts[2],
			'method'     => $parts[3],
			'uri'        => $parts[4],
			'host'       => $parts[5],
			'status'     => (int) $parts[6],
			'cached'     => (int) $parts[7],
			'elapsed_us' => (int) $parts[8],
		);
	}

	/**
	 * Whether the response carried a header that suggests it came from a page cache.
	 *
	 * @param string[] $headers Output of headers_list().
	 * @return bool
	 */
	public static function looks_cached( array $headers ): bool {
		foreach ( $headers as $header ) {
			if ( preg_match( '~^(x-cache|x-litespeed-cache|x-wp-rocket|cf-cache-status|x-nginx-cache|x-proxy-cache|x-srcache-fetch-status)\s*:~i', $header ) ) {
				return true;
			}
		}
		return false;
	}
}
