<?php
/**
 * Physical robots.txt fixture.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Tests\Integration;

use AiParseAble\Activation;
use AiParseAble\Plugin;
use AiParseAble\Robots\Block;
use AiParseAble\Robots\PhysicalFile;
use AiParseAble\Robots\Robots;
use WP_UnitTestCase;

/**
 * A physical file makes the filter dead; the plugin must notice and manage a block inside it.
 */
final class RobotsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Activation::activate( false );
	}

	public function tear_down(): void {
		if ( PhysicalFile::exists() ) {
			unlink( PhysicalFile::path() );
		}
		parent::tear_down();
	}

	public function test_filter_emits_block(): void {
		Plugin::instance()->options()->set( 'robots', array( 1 => 'block' ) );
		$robots = new Robots( Plugin::instance()->options() );
		$robots->register();
		$out = apply_filters( 'robots_txt', "User-agent: *\n", true );
		$this->assertStringContainsString( "# BEGIN AI ParseAble\nUser-agent: GPTBot\nDisallow: /\n# END AI ParseAble", $out );
	}

	public function test_physical_file_detected_and_managed(): void {
		file_put_contents( PhysicalFile::path(), "User-agent: *\nDisallow: /secret/\n" ); // phpcs:ignore
		Plugin::instance()->options()->set( 'robots', array( 7 => 'allow' ) );
		$robots = new Robots( Plugin::instance()->options() );
		$info   = $robots->inspect();
		$this->assertTrue( $info['physical'] );
		$this->assertFalse( $info['physical_managed'] );

		$this->assertTrue( PhysicalFile::write_block( $robots->rules() ) );
		$contents = PhysicalFile::read();
		$this->assertStringContainsString( "Disallow: /secret/\n", $contents );
		$this->assertStringContainsString( Block::wrap( "User-agent: PerplexityBot\nAllow: /\n" ), $contents );

		$this->assertTrue( PhysicalFile::write_block( '' ) );
		$this->assertStringNotContainsString( 'AI ParseAble', PhysicalFile::read() );
		$this->assertStringContainsString( 'Disallow: /secret/', PhysicalFile::read() );
	}

	public function test_conflicting_filter_is_named(): void {
		Plugin::instance()->options()->set( 'robots', array( 1 => 'block' ) );
		add_filter( 'robots_txt', static function () { return "User-agent: *\nDisallow:\n"; }, 99 );
		$robots = new Robots( Plugin::instance()->options() );
		$robots->register();
		$info = $robots->inspect();
		$this->assertFalse( $info['intact'] );
		$this->assertNotEmpty( $info['other_filters'] );
	}
}
