<?php
/**
 * Queue record format.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Tests\Unit;

use AiParseAble\Logger\Record;
use PHPUnit\Framework\TestCase;

/**
 * Round trip and hostile input.
 */
final class RecordTest extends TestCase {

	public function test_round_trip(): void {
		$line = Record::hit( 'GPTBot', '20.171.207.1', 'GET', "/path?x=1\tinjected", 'example.test', 404, 1, 120 );
		$rec  = Record::parse( $line );
		$this->assertSame( 'hit', $rec['type'] );
		$this->assertSame( 'GPTBot', $rec['token'] );
		$this->assertSame( 404, $rec['status'] );
		$this->assertSame( 1, $rec['cached'] );
		$this->assertSame( '/path?x=1 injected', $rec['uri'] );
	}

	public function test_timing_and_garbage(): void {
		$this->assertSame( array( 'type' => 'timing', 'elapsed_us' => 85 ), Record::parse( Record::timing( 85 ) ) );
		$this->assertNull( Record::parse( '' ) );
		$this->assertNull( Record::parse( "garbage\tline" ) );
	}
}
