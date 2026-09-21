<?php
/**
 * Drop-in source generation.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Tests\Unit;

use AiParseAble\Logger\DropIn;
use PHPUnit\Framework\TestCase;

/**
 * The generated file must evaluate back to exactly the config that went in.
 */
final class DropInTest extends TestCase {

	public function test_template_placeholder_is_present(): void {
		$template = (string) file_get_contents( AI_PARSEABLE_DIR . 'mu/' . DropIn::FILENAME );
		$this->assertStringContainsString( DropIn::CONFIG_PLACEHOLDER, $template );
		$this->assertStringContainsString( "define( 'AI_PARSEABLE_DROP_IN', '0.0.0' );", $template );
		// A "Plugin Name" header here would make WordPress list the template as a second plugin.
		$this->assertDoesNotMatchRegularExpression( '/^[ \t\/*#@]*Plugin Name:/m', $template );
	}

	public function test_generated_source_carries_a_plugin_header(): void {
		$source = DropIn::fill( (string) file_get_contents( AI_PARSEABLE_DIR . 'mu/' . DropIn::FILENAME ), '1.2.3+abc', array() );
		$this->assertMatchesRegularExpression( '/^ \* Plugin Name: AI ParseAble drop-in$/m', $source );
		$this->assertStringNotContainsString( 'Drop-in Name:', $source );
	}

	public function test_hostile_config_round_trips_through_generated_source(): void {
		$config = array(
			'pattern'   => '~(?<![A-Za-z0-9])(GPTBot|Foo\\\\Bar|It\'s|\$1|Meta\-Bot)~i', // literal backslash-backslash, quote, $1, escaped dash.
			'dir'       => "/var/www/O'Brien/uploads/ai-parseable/queue",
			'token'     => 'abc$1\\0',
			'max_bytes' => 1048576,
		);
		$source = DropIn::fill( (string) file_get_contents( AI_PARSEABLE_DIR . 'mu/' . DropIn::FILENAME ), '9.9.9+deadbeef', $config );

		$this->assertStringContainsString( "define( 'AI_PARSEABLE_DROP_IN', '9.9.9+deadbeef' );", $source );
		$this->assertStringContainsString( ' * Version:     9.9.9+deadbeef', $source );
		$this->assertStringNotContainsString( DropIn::CONFIG_PLACEHOLDER, $source );

		$this->assertTrue( (bool) preg_match( '~^\$ai_parseable_config = (array \(.*?\n\));$~ms', $source, $m ), 'config line present' );
		$this->assertSame( $config, eval( 'return ' . $m[1] . ';' ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- proving the generated PHP evaluates to the input.
	}
}
