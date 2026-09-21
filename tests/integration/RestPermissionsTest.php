<?php
/**
 * REST permissions.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Tests\Integration;

use AiParseAble\Activation;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Every route is gated on the custom capability.
 */
final class RestPermissionsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Activation::activate( false );
		do_action( 'rest_api_init' );
	}

	public function routes(): array {
		return array(
			array( 'GET', '/ai-parseable/v1/stats' ),
			array( 'GET', '/ai-parseable/v1/urls' ),
			array( 'GET', '/ai-parseable/v1/coverage' ),
			array( 'GET', '/ai-parseable/v1/settings' ),
			array( 'POST', '/ai-parseable/v1/settings' ),
			array( 'GET', '/ai-parseable/v1/robots' ),
			array( 'POST', '/ai-parseable/v1/ingest' ),
		);
	}

	/**
	 * @dataProvider routes
	 */
	public function test_anonymous_is_rejected( string $method, string $route ): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( new WP_REST_Request( $method, $route ) );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @dataProvider routes
	 */
	public function test_editor_without_capability_is_rejected( string $method, string $route ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = rest_do_request( new WP_REST_Request( $method, $route ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_delegated_capability_is_enough(): void {
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		get_role( 'editor' )->add_cap( Activation::CAPABILITY );
		wp_set_current_user( $user );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/ai-parseable/v1/settings' ) );
		$this->assertSame( 200, $response->get_status() );
		get_role( 'editor' )->remove_cap( Activation::CAPABILITY );
	}

	public function test_range_enum_is_validated(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'GET', '/ai-parseable/v1/stats' );
		$request->set_param( 'range', '365d' );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
	}
}
