<?php
/**
 * Identity verification.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Tests\Unit;

use CrawlLedger\Logger\Ranges;
use CrawlLedger\Logger\Verifier;
use PHPUnit\Framework\TestCase;

/**
 * Fixture: a spoofed GPTBot from an unlisted IP must be unverified; forward confirmation is mandatory.
 */
final class VerifierTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['crawlledger_transients'] = array();
		$GLOBALS['crawlledger_options']    = array(
			Ranges::OPTION => array(
				1 => array( 'cidrs' => array( '20.171.207.0/24', '2a01:111:f403:c000::/62' ), 'fetched' => time(), 'error' => '' ),
			),
		);
	}

	public function test_spoofed_gptbot_from_unlisted_ip_is_unverified(): void {
		$v = new Verifier( new Ranges() );
		$this->assertFalse( $v->verify( 1, '203.0.113.9' ) );
	}

	public function test_gptbot_from_published_range_is_verified(): void {
		$v = new Verifier( new Ranges() );
		$this->assertTrue( $v->verify( 1, '20.171.207.44' ) );
		$this->assertTrue( $v->verify( 1, '2a01:111:f403:c001::5' ) );
	}

	public function test_rdns_requires_forward_confirmation(): void {
		// Reverse says googlebot.com, but forward resolves elsewhere: spoofed PTR record.
		$v = new Verifier(
			new Ranges(),
			static function () { return 'crawl-66-249-66-1.googlebot.com'; },
			static function () { return array( '198.51.100.1' ); }
		);
		$this->assertFalse( $v->verify( 9, '66.249.66.1' ) );

		$GLOBALS['crawlledger_transients'] = array(); // Verification results are cached per /24; clear between cases.
		$v2 = new Verifier(
			new Ranges(),
			static function () { return 'crawl-66-249-66-1.googlebot.com'; },
			static function () { return array( '66.249.66.1' ); }
		);
		$this->assertTrue( $v2->verify( 9, '66.249.66.1' ) );
	}

	public function test_rdns_rejects_wrong_suffix(): void {
		$v = new Verifier(
			new Ranges(),
			static function () { return 'host.evil-googlebot.com.example'; },
			static function () { return array( '66.249.66.1' ); }
		);
		$this->assertFalse( $v->verify( 9, '66.249.66.1' ) );
	}

	public function test_unverifiable_vendor_is_false_without_dns(): void {
		$calls = 0;
		$v     = new Verifier( new Ranges(), static function () use ( &$calls ) { $calls++; return ''; } );
		$this->assertFalse( $v->verify( 4, '1.2.3.4' ) ); // ClaudeBot: no ranges, no rDNS.
		$this->assertSame( 0, $calls );
	}

	public function test_result_is_cached_per_prefix(): void {
		$calls = 0;
		$v     = new Verifier(
			new Ranges(),
			static function () use ( &$calls ) { $calls++; return 'x.crawl.amazonbot.amazon'; },
			static function () { return array( '52.70.240.1', '52.70.240.2' ); }
		);
		$this->assertTrue( $v->verify( 18, '52.70.240.1' ) );
		$this->assertTrue( $v->verify( 18, '52.70.240.2' ) ); // same /24: cached, no second lookup.
		$this->assertSame( 1, $calls );
	}
}
