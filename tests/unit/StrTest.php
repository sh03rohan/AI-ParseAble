<?php
/**
 * Path normalisation.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Tests\Unit;

use CrawlLedger\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * Str::path() must always yield valid UTF-8 of at most 512 bytes.
 */
final class StrTest extends TestCase {

	public function test_query_string_is_dropped_and_empty_becomes_root(): void {
		$this->assertSame( '/shop/', Str::path( '/shop/?page=2' ) );
		$this->assertSame( '/', Str::path( '?only=query' ) );
	}

	public function test_valid_utf8_is_kept(): void {
		$this->assertSame( '/café/', Str::path( '/café/?x' ) );
	}

	public function test_invalid_bytes_are_percent_encoded(): void {
		$path = Str::path( "/\xff\xfe-bogus" );
		$this->assertSame( '/%FF%FE-bogus', $path );
		$this->assertTrue( (bool) preg_match( '//u', $path ) );
	}

	public function test_cap_never_splits_a_multibyte_character(): void {
		$path = Str::path( '/' . str_repeat( 'é', 300 ) ); // 601 bytes.
		$this->assertLessThanOrEqual( 512, strlen( $path ) );
		$this->assertTrue( (bool) preg_match( '//u', $path ) );
		$this->assertSame( 1, strlen( $path ) % 2, 'one slash plus whole 2-byte characters is an odd length' );
	}
}
