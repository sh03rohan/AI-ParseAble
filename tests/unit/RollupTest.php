<?php
/**
 * Rollup arithmetic.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Tests\Unit;

use CrawlLedger\Logger\Repository;
use CrawlLedger\Logger\Rollup;
use PHPUnit\Framework\TestCase;

/**
 * Daily buckets.
 */
final class RollupTest extends TestCase {

	public function test_buckets(): void {
		$this->assertSame( 200, Repository::bucket( 200 ) );
		$this->assertSame( 200, Repository::bucket( 204 ) );
		$this->assertSame( 300, Repository::bucket( 301 ) );
		$this->assertSame( 400, Repository::bucket( 404 ) );
		$this->assertSame( 500, Repository::bucket( 503 ) );
		$this->assertSame( 200, Repository::bucket( 0 ) );
	}

	public function test_aggregate(): void {
		$rows = array(
			array( 'hit_at' => '2026-09-14 01:00:00', 'bot_id' => 1, 'status' => 200, 'verified' => 1 ),
			array( 'hit_at' => '2026-09-14 02:00:00', 'bot_id' => 1, 'status' => 200, 'verified' => 1 ),
			array( 'hit_at' => '2026-09-14 03:00:00', 'bot_id' => 1, 'status' => 404, 'verified' => 1 ),
			array( 'hit_at' => '2026-09-14 04:00:00', 'bot_id' => 1, 'status' => 200, 'verified' => 0 ),
			array( 'hit_at' => '2026-09-15 00:00:00', 'bot_id' => 2, 'status' => 200, 'verified' => 1 ),
		);
		$daily = Rollup::aggregate( $rows );
		$this->assertCount( 4, $daily );
		$find = static function ( $day, $bot, $bucket, $v ) use ( $daily ) {
			foreach ( $daily as $d ) {
				if ( $d['day'] === $day && $d['bot_id'] === $bot && $d['status_bucket'] === $bucket && $d['verified'] === $v ) {
					return $d['hits'];
				}
			}
			return null;
		};
		$this->assertSame( 2, $find( '2026-09-14', 1, 200, 1 ) );
		$this->assertSame( 1, $find( '2026-09-14', 1, 400, 1 ) );
		$this->assertSame( 1, $find( '2026-09-14', 1, 200, 0 ) );
		$this->assertSame( 1, $find( '2026-09-15', 2, 200, 1 ) );
		$this->assertSame( 5, array_sum( array_column( $daily, 'hits' ) ) );
	}
}
