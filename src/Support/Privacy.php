<?php
/**
 * Privacy integration.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Support;

use AiParseAble\Module;

/**
 * Crawler IPs are not user data, but registering exporters and erasers and suggesting policy text shows
 * the requirements were read. The eraser returns nothing to erase; the exporter returns nothing to export.
 */
final class Privacy implements Module {

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
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'eraser' ) );
		add_action( 'admin_init', array( $this, 'policy_content' ) );
	}

	/**
	 * Register exporter.
	 *
	 * @param array<mixed> $exporters Exporters.
	 * @return array<mixed>
	 */
	public function exporter( $exporters ): array {
		$exporters['ai-parseable'] = array(
			'exporter_friendly_name' => __( 'AI ParseAble', 'ai-parseable' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register eraser.
	 *
	 * @param array<mixed> $erasers Erasers.
	 * @return array<mixed>
	 */
	public function eraser( $erasers ): array {
		$erasers['ai-parseable'] = array(
			'eraser_friendly_name' => __( 'AI ParseAble', 'ai-parseable' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export callback. The log holds crawler addresses only, never keyed to a user.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array<mixed>
	 */
	public function export( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- signature fixed by core.
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	/**
	 * Erase callback.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array<mixed>
	 */
	public function erase( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy text.
	 *
	 * @return void
	 */
	public function policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$mode = (string) $this->options->get( 'ip_mode', Options::IP_MODE_TRUNCATED );
		$how  = Options::IP_MODE_FULL === $mode
			? __( 'the full IP address of the request', 'ai-parseable' )
			: ( Options::IP_MODE_HASHED === $mode ? __( 'a salted, one-way hash of the request IP address', 'ai-parseable' ) : __( 'a truncated IP address (the first three octets for IPv4, the first 48 bits for IPv6)', 'ai-parseable' ) );
		$days = (int) $this->options->get( 'retention_days', 7 );
		$text = sprintf(
			/* translators: 1: how the address is stored, 2: number of days */
			__( 'This site records visits by known AI crawlers (automated software operated by AI companies), including the page requested, the response status, the crawler name and %1$s. Individual records are kept for %2$d days; daily totals per crawler are kept indefinitely. No visitor browsing data is recorded.', 'ai-parseable' ),
			$how,
			$days
		);
		wp_add_privacy_policy_content( __( 'AI ParseAble', 'ai-parseable' ), wp_kses_post( wpautop( $text ) ) );
	}
}
