<?php
/**
 * IP helpers.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Support;

/**
 * Binary IP handling. Everything is stored as inet_pton() bytes so IPv6 needs no special casing.
 */
final class Ip {

	/**
	 * Whether the string is a valid IPv4 or IPv6 address.
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public static function is_valid( string $ip ): bool {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Pack to binary (4 or 16 bytes).
	 *
	 * @param string $ip Address.
	 * @return string|null
	 */
	public static function pack( string $ip ): ?string {
		if ( ! self::is_valid( $ip ) ) {
			return null;
		}
		$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- validated above; silence is for exotic platforms.
		return false === $bin ? null : $bin;
	}

	/**
	 * Unpack binary to a printable address.
	 *
	 * @param string $bin Packed address.
	 * @return string
	 */
	public static function unpack( string $bin ): string {
		if ( 4 !== strlen( $bin ) && 16 !== strlen( $bin ) ) {
			return '';
		}
		$ip = @inet_ntop( $bin ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false === $ip ? '' : $ip;
	}

	/**
	 * Whether the address falls inside a CIDR block. Works for v4 and v6, rejects mixed families.
	 *
	 * @param string $ip   Address.
	 * @param string $cidr Block, e.g. 203.0.113.0/24 or 2001:db8::/32.
	 * @return bool
	 */
	public static function in_cidr( string $ip, string $cidr ): bool {
		if ( false === strpos( $cidr, '/' ) ) {
			return $ip === $cidr;
		}
		list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		$bits                  = (int) $bits;

		$ip_bin  = self::pack( $ip );
		$net_bin = self::pack( $subnet );
		if ( null === $ip_bin || null === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
			return false;
		}
		$max_bits = strlen( $ip_bin ) * 8;
		if ( $bits < 0 || $bits > $max_bits ) {
			return false;
		}
		$full_bytes = intdiv( $bits, 8 );
		$rem_bits   = $bits % 8;

		if ( $full_bytes > 0 && substr( $ip_bin, 0, $full_bytes ) !== substr( $net_bin, 0, $full_bytes ) ) {
			return false;
		}
		if ( 0 === $rem_bits ) {
			return true;
		}
		$mask = ( 0xFF << ( 8 - $rem_bits ) ) & 0xFF;
		return ( ord( $ip_bin[ $full_bytes ] ) & $mask ) === ( ord( $net_bin[ $full_bytes ] ) & $mask );
	}

	/**
	 * Truncate to /24 (v4) or /48 (v6) for privacy-reduced storage.
	 *
	 * @param string $ip Address.
	 * @return string Truncated printable address, or '' when invalid.
	 */
	public static function truncate( string $ip ): string {
		$bin = self::pack( $ip );
		if ( null === $bin ) {
			return '';
		}
		if ( 4 === strlen( $bin ) ) {
			$bin = substr( $bin, 0, 3 ) . "\0";
		} else {
			$bin = substr( $bin, 0, 6 ) . str_repeat( "\0", 10 );
		}
		return self::unpack( $bin );
	}

	/**
	 * Cache key prefix: /24 for v4, /48 for v6.
	 *
	 * @param string $ip Address.
	 * @return string
	 */
	public static function cache_key( string $ip ): string {
		return md5( self::truncate( $ip ) );
	}

	/**
	 * Salted one-way hash of an address, returned as 16 raw bytes so it fits the VARBINARY(16) column.
	 *
	 * @param string $ip   Address.
	 * @param string $salt Site salt.
	 * @return string
	 */
	public static function hash( string $ip, string $salt ): string {
		return substr( hash( 'sha256', $salt . '|' . $ip, true ), 0, 16 );
	}

	/**
	 * Best-effort client IP. REMOTE_ADDR only — trusting X-Forwarded-For blindly is how spoofing gets in.
	 * Sites behind a proxy set the real address on REMOTE_ADDR via their server config or a proxy plugin.
	 *
	 * @return string
	 */
	public static function remote_addr(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
		return self::is_valid( $ip ) ? $ip : '';
	}
}
