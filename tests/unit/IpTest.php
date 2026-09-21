<?php
/**
 * IP helpers.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Tests\Unit;

use AiParseAble\Support\Ip;
use PHPUnit\Framework\TestCase;

/**
 * Binary storage, CIDR membership, privacy reduction.
 */
final class IpTest extends TestCase {

	public function test_pack_lengths(): void {
		$this->assertSame( 4, strlen( Ip::pack( '203.0.113.9' ) ) );
		$this->assertSame( 16, strlen( Ip::pack( '2001:db8::1' ) ) );
		$this->assertNull( Ip::pack( 'not-an-ip' ) );
	}

	public function test_cidr_v4(): void {
		$this->assertTrue( Ip::in_cidr( '20.171.207.5', '20.171.207.0/24' ) );
		$this->assertFalse( Ip::in_cidr( '20.171.208.5', '20.171.207.0/24' ) );
		$this->assertTrue( Ip::in_cidr( '10.0.0.1', '10.0.0.0/8' ) );
		$this->assertTrue( Ip::in_cidr( '10.0.0.1', '10.0.0.1/32' ) );
		$this->assertFalse( Ip::in_cidr( '10.0.0.2', '10.0.0.1/32' ) );
		$this->assertTrue( Ip::in_cidr( '1.2.3.4', '0.0.0.0/0' ) );
	}

	public function test_cidr_v6(): void {
		$this->assertTrue( Ip::in_cidr( '2001:4860:4801:10::1', '2001:4860:4801::/48' ) );
		$this->assertFalse( Ip::in_cidr( '2001:4860:4802::1', '2001:4860:4801::/48' ) );
		$this->assertTrue( Ip::in_cidr( '2a03:2880:f800::1', '2a03:2880:f800::/45' ) );
	}

	public function test_mixed_families_never_match(): void {
		$this->assertFalse( Ip::in_cidr( '2001:db8::1', '10.0.0.0/8' ) );
		$this->assertFalse( Ip::in_cidr( '10.0.0.1', '2001:db8::/32' ) );
	}

	public function test_truncate(): void {
		$this->assertSame( '203.0.113.0', Ip::truncate( '203.0.113.77' ) );
		$this->assertSame( '2001:db8:abcd::', Ip::truncate( '2001:db8:abcd:1234:5678:9abc:def0:1' ) );
	}

	public function test_hash_is_16_bytes_and_salted(): void {
		$a = Ip::hash( '203.0.113.77', 'salt-a' );
		$b = Ip::hash( '203.0.113.77', 'salt-b' );
		$this->assertSame( 16, strlen( $a ) );
		$this->assertNotSame( $a, $b );
	}
}
