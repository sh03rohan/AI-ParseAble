<?php
/**
 * Managed robots.txt block.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Robots;

use CrawlLedger\Logger\Signatures;

/**
 * Pure functions: build the managed block and splice it into an existing file between markers.
 * Same discipline as core's .htaccess handling — never touch a line outside the markers.
 */
final class Block {

	const BEGIN = '# BEGIN CrawlLedger';
	const END   = '# END CrawlLedger';

	/**
	 * Build rule lines from the per-bot settings. Bots with no explicit rule emit nothing.
	 *
	 * @param array<mixed> $rules bot_id => 'allow' | 'block'.
	 * @return string Rules only, no markers. Empty when no rules are set.
	 */
	public static function rules( array $rules ): string {
		$lines = array();
		foreach ( Signatures::registry() as $id => $bot ) {
			if ( ! isset( $rules[ $id ] ) || empty( $bot['robots'] ) ) {
				continue;
			}
			$rule = $rules[ $id ];
			if ( 'block' === $rule ) {
				$lines[] = 'User-agent: ' . $bot['robots'];
				$lines[] = 'Disallow: /';
				$lines[] = '';
			} elseif ( 'allow' === $rule ) {
				$lines[] = 'User-agent: ' . $bot['robots'];
				$lines[] = 'Allow: /';
				$lines[] = '';
			}
		}
		return $lines ? rtrim( implode( "\n", $lines ) ) . "\n" : '';
	}

	/**
	 * Wrap rules in markers.
	 *
	 * @param string $rules Output of rules().
	 * @return string
	 */
	public static function wrap( string $rules ): string {
		return self::BEGIN . "\n" . $rules . self::END . "\n";
	}

	/**
	 * Replace (or append) the managed block inside existing robots.txt contents.
	 *
	 * @param string $contents Existing file contents.
	 * @param string $rules    Rules to place between the markers; empty removes the block.
	 * @return string
	 */
	public static function splice( string $contents, string $rules ): string {
		$contents = str_replace( "\r\n", "\n", $contents );
		$pattern  = '~' . preg_quote( self::BEGIN, '~' ) . '.*?' . preg_quote( self::END, '~' ) . '\n?~s';
		$block    = '' === $rules ? '' : self::wrap( $rules );

		if ( preg_match( $pattern, $contents ) ) {
			$result = (string) preg_replace( $pattern, $block, $contents, 1 );
			return '' === $rules ? rtrim( $result ) . ( '' === trim( $result ) ? '' : "\n" ) : $result;
		}
		if ( '' === $block ) {
			return $contents;
		}
		$contents = rtrim( $contents );
		return ( '' === $contents ? '' : $contents . "\n\n" ) . $block;
	}

	/**
	 * Whether the given robots output contains our block with exactly our rules.
	 *
	 * @param string $output Final robots.txt output.
	 * @param string $rules  Rules we emitted.
	 * @return bool
	 */
	public static function intact( string $output, string $rules ): bool {
		if ( '' === $rules ) {
			return true;
		}
		$output = str_replace( "\r\n", "\n", $output );
		return false !== strpos( $output, self::wrap( $rules ) );
	}
}
