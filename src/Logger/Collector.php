<?php
/**
 * PHP-level request capture.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Logger;

use CrawlLedger\Module;
use CrawlLedger\Support\Ip;
use CrawlLedger\Support\Options;

/**
 * The only thing loaded on a front-end request.
 *
 * Non-bot path: one preg_match against a precompiled pattern from an autoloaded option, zero queries.
 * Bot path: one closure queued for shutdown. Nothing is written inline.
 */
final class Collector implements Module {

	const TIMING_SAMPLE_RATE = 200; // one non-bot request in N contributes a timing sample.

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Shutdown buffer.
	 *
	 * @var Buffer
	 */
	private $buffer;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 * @param Buffer  $buffer  Buffer.
	 */
	public function __construct( Options $options, Buffer $buffer ) {
		$this->options = $options;
		$this->buffer  = $buffer;
	}

	/**
	 * Match now — the UA is already known — and defer the write.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->capture();
	}

	/**
	 * Capture the current request.
	 *
	 * @return void
	 */
	public function capture(): void {
		$start = microtime( true );
		$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- matched, never output.
		if ( '' === $ua ) {
			return;
		}
		$pattern = (string) $this->options->get( 'ua_pattern', '' );
		if ( '' === $pattern || ! preg_match( $pattern, $ua, $m ) ) {
			$this->maybe_sample_timing( $start );
			return;
		}

		$token      = $m[1];
		$elapsed_us = (int) round( ( microtime( true ) - $start ) * 1000000 );

		$this->buffer->add(
			static function () use ( $token, $elapsed_us ) {
				$status = (int) http_response_code();
				return Record::hit(
					$token,
					Ip::remote_addr(),
					isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$status > 0 ? $status : 200,
					Record::looks_cached( headers_list() ) ? 1 : 0,
					$elapsed_us
				);
			}
		);
	}

	/**
	 * Sample the non-bot cost. The whole point of the budget is the 99% path, so it must be measured.
	 *
	 * @param float $start microtime(true) at entry.
	 * @return void
	 */
	private function maybe_sample_timing( float $start ): void {
		// Not wp_rand(): this runs while plugin files are being included, before pluggable.php exists.
		if ( 0 !== mt_rand( 0, self::TIMING_SAMPLE_RATE - 1 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			return;
		}
		$elapsed_us = (int) round( ( microtime( true ) - $start ) * 1000000 );
		$this->buffer->add(
			static function () use ( $elapsed_us ) {
				return Record::timing( $elapsed_us );
			}
		);
	}
}
