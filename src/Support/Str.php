<?php
/**
 * String helpers.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Support;

/**
 * Small, dependency-free string utilities.
 */
final class Str {

	/**
	 * Random URL-safe token.
	 *
	 * @param int $length Characters.
	 * @return string
	 */
	public static function token( int $length = 16 ): string {
		try {
			$bytes = random_bytes( (int) ceil( $length / 2 ) );
		} catch ( \Exception $e ) {
			$bytes = md5( uniqid( (string) mt_rand(), true ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- may run before pluggable.php.
		}
		return substr( bin2hex( $bytes ), 0, $length );
	}

	/**
	 * Case-insensitive "starts with".
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	public static function starts_with( string $haystack, string $needle ): bool {
		return '' !== $needle && 0 === strncasecmp( $haystack, $needle, strlen( $needle ) );
	}

	/**
	 * Case-insensitive "ends with".
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	public static function ends_with( string $haystack, string $needle ): bool {
		$len = strlen( $needle );
		return 0 !== $len && strlen( $haystack ) >= $len && 0 === strcasecmp( substr( $haystack, -$len ), $needle );
	}

	/**
	 * Normalise a request path for storage: strip the query string, make it valid UTF-8, cap the length.
	 *
	 * The column is utf8mb4 and the rows are written with a raw query, so an invalid byte sequence
	 * from a crawler would be silently cut at the first bad byte and stored as a phantom "/".
	 * Invalid sequences are percent-encoded instead and the cap never splits a multibyte character.
	 *
	 * @param string $uri Raw request URI.
	 * @return string
	 */
	public static function path( string $uri ): string {
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = '/';
		}
		if ( ! preg_match( '//u', $path ) ) {
			$path = (string) preg_replace_callback(
				'/[\x80-\xFF]/',
				static function ( array $m ): string {
					return '%' . strtoupper( bin2hex( $m[0] ) );
				},
				$path
			);
		}
		if ( strlen( $path ) <= 512 ) {
			return $path;
		}
		return function_exists( 'mb_strcut' ) ? mb_strcut( $path, 0, 512, 'UTF-8' ) : substr( $path, 0, 512 );
	}

	/**
	 * First 8 bytes of sha1(path), the storage form of url_hash.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function url_hash( string $path ): string {
		return substr( sha1( $path, true ), 0, 8 );
	}
}
