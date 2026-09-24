<?php
/**
 * robots.txt block rewriting.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Tests\Unit;

use CrawlLedger\Robots\Block;
use PHPUnit\Framework\TestCase;

/**
 * Only the lines between the markers may ever change.
 */
final class BlockTest extends TestCase {

	public function test_rules_only_for_explicit_settings(): void {
		$rules = Block::rules( array( 1 => 'block', 7 => 'allow', 99 => 'block' ) );
		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $rules );
		$this->assertStringContainsString( "User-agent: PerplexityBot\nAllow: /", $rules );
		$this->assertStringNotContainsString( 'ClaudeBot', $rules );
		$this->assertSame( '', Block::rules( array() ) );
	}

	public function test_splice_appends_when_absent(): void {
		$existing = "User-agent: *\nDisallow: /private/\n";
		$result   = Block::splice( $existing, "User-agent: GPTBot\nDisallow: /\n" );
		$this->assertStringStartsWith( "User-agent: *\nDisallow: /private/\n\n# BEGIN CrawlLedger\n", $result );
		$this->assertStringEndsWith( "# END CrawlLedger\n", $result );
	}

	public function test_splice_replaces_only_between_markers(): void {
		$existing = "Sitemap: https://x/sitemap.xml\n# BEGIN CrawlLedger\nUser-agent: Old\nDisallow: /\n# END CrawlLedger\nUser-agent: *\nDisallow: /wp-admin/\n";
		$result   = Block::splice( $existing, "User-agent: GPTBot\nAllow: /\n" );
		$this->assertStringContainsString( "Sitemap: https://x/sitemap.xml\n", $result );
		$this->assertStringContainsString( "User-agent: *\nDisallow: /wp-admin/\n", $result );
		$this->assertStringContainsString( "# BEGIN CrawlLedger\nUser-agent: GPTBot\nAllow: /\n# END CrawlLedger\n", $result );
		$this->assertStringNotContainsString( 'Old', $result );
	}

	public function test_splice_removes_block_with_empty_rules(): void {
		$existing = "User-agent: *\nDisallow:\n\n# BEGIN CrawlLedger\nUser-agent: GPTBot\nAllow: /\n# END CrawlLedger\n";
		$result   = Block::splice( $existing, '' );
		$this->assertStringNotContainsString( 'CrawlLedger', $result );
		$this->assertStringContainsString( "User-agent: *\nDisallow:", $result );
	}

	public function test_intact_detects_rewrite_by_another_plugin(): void {
		$rules  = "User-agent: GPTBot\nDisallow: /\n";
		$intact = "User-agent: *\n\n" . Block::wrap( $rules );
		$broken = "User-agent: *\n# BEGIN CrawlLedger\nUser-agent: GPTBot\nAllow: /\n# END CrawlLedger\n";
		$this->assertTrue( Block::intact( $intact, $rules ) );
		$this->assertFalse( Block::intact( $broken, $rules ) );
	}
}
