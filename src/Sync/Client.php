<?php
/**
 * Checker-site client.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Sync;

use AiParseAble\Support\Options;

/**
 * Optional. Everything else works without a key. A scan request sends the site URL and nothing else.
 * The key is stored in an option, never a transient; it is never logged and is redacted from debug output.
 */
final class Client {

	const OPTION_STATE = 'ai_parseable_sync';

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
	 * API base URL.
	 *
	 * @return string
	 */
	public static function base_url(): string {
		$default = defined( 'AI_PARSEABLE_CHECKER_URL' ) ? AI_PARSEABLE_CHECKER_URL : 'https://aiparseable.com/api/v1';
		return untrailingslashit( (string) apply_filters( 'ai_parseable_checker_url', $default ) );
	}

	/**
	 * Whether a key is set.
	 *
	 * @return bool
	 */
	public function connected(): bool {
		return '' !== (string) $this->options->get( 'api_key', '' );
	}

	/**
	 * Cached status (score, findings, last scan) plus connection state.
	 *
	 * @return array<mixed>
	 */
	public function status(): array {
		$state = get_option( self::OPTION_STATE, array() );
		return array(
			'connected'  => $this->connected(),
			'base_url'   => self::base_url(),
			'score'      => isset( $state['score'] ) ? $state['score'] : null,
			'findings'   => isset( $state['findings'] ) ? (array) $state['findings'] : array(),
			'scanned_at' => isset( $state['scanned_at'] ) ? (int) $state['scanned_at'] : 0,
			'error'      => isset( $state['error'] ) ? (string) $state['error'] : '',
		);
	}

	/**
	 * Request a rescan and refresh the cached result.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function rescan() {
		if ( ! $this->connected() ) {
			return new \WP_Error( 'no_key', __( 'Enter an API key to connect this site to the checker.', 'ai-parseable' ), array( 'status' => 400 ) );
		}
		$response = wp_remote_post(
			self::base_url() . '/scan',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . (string) $this->options->get( 'api_key', '' ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'url' => home_url( '/' ) ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->remember( array( 'error' => $response->get_error_message() ) );
			return new \WP_Error( 'http', __( 'The checker could not be reached.', 'ai-parseable' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 401 === $code || 403 === $code ) {
			$this->remember( array( 'error' => 'unauthorised' ) );
			return new \WP_Error( 'auth', __( 'The checker rejected the API key.', 'ai-parseable' ), array( 'status' => 401 ) );
		}
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$this->remember( array( 'error' => 'HTTP ' . $code ) );
			return new \WP_Error( 'http', __( 'The checker returned an unexpected response.', 'ai-parseable' ), array( 'status' => 502 ) );
		}
		$this->remember(
			array(
				'score'      => isset( $data['score'] ) ? (int) $data['score'] : null,
				'findings'   => isset( $data['findings'] ) && is_array( $data['findings'] ) ? array_slice( array_map( 'sanitize_text_field', array_map( 'strval', $data['findings'] ) ), 0, 50 ) : array(),
				'scanned_at' => time(),
				'error'      => '',
			)
		);
		return $this->status();
	}

	/**
	 * Persist state.
	 *
	 * @param array<mixed> $values Values.
	 * @return void
	 */
	private function remember( array $values ): void {
		$state = get_option( self::OPTION_STATE, array() );
		update_option( self::OPTION_STATE, array_merge( is_array( $state ) ? $state : array(), $values ), false );
	}
}
