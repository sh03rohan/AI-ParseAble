<?php
/**
 * Front-end collector.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Tests\Unit;

use AiParseAble\Logger\Buffer;
use AiParseAble\Logger\Collector;
use AiParseAble\Logger\Queue;
use AiParseAble\Logger\Signatures;
use AiParseAble\Support\Options;
use PHPUnit\Framework\TestCase;

/**
 * The collector runs while plugin files are still being included, before pluggable.php is loaded.
 * The unit bootstrap deliberately defines no pluggable functions (wp_rand, wp_hash, …) so any use
 * of one here fails the suite instead of returning a 500 to every visitor.
 */
final class CollectorTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['ai_parseable_options'] = array(
			Options::OPTION => array( 'ua_pattern' => Signatures::compile() ),
		);
		$GLOBALS['ai_parseable_filters'] = array();
	}

	private function collector(): Collector {
		$options = new Options();
		return new Collector( $options, new Buffer( new Queue( $options ) ) );
	}

	public function test_non_bot_path_runs_without_pluggable_functions(): void {
		$this->assertFalse( function_exists( 'wp_rand' ), 'Bootstrap must not stub wp_rand for this test to mean anything.' );
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Safari/605.1.15';
		for ( $i = 0; $i < 1000; $i++ ) { // Enough iterations to hit the 1-in-200 timing sample.
			$this->collector()->capture();
		}
		$this->assertTrue( true );
	}

	public function test_bot_path_queues_a_shutdown_write(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; GPTBot/1.2)';
		$this->collector()->capture();
		$this->assertArrayHasKey( 'shutdown', $GLOBALS['ai_parseable_filters'] );
	}

	public function test_nothing_happens_without_a_pattern(): void {
		$GLOBALS['ai_parseable_options'][ Options::OPTION ]['ua_pattern'] = '';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; GPTBot/1.2)';
		$this->collector()->capture();
		$this->assertArrayNotHasKey( 'shutdown', $GLOBALS['ai_parseable_filters'] );
	}
}
