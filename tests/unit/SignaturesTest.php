<?php
/**
 * UA matching.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Tests\Unit;

use CrawlLedger\Logger\Signatures;
use PHPUnit\Framework\TestCase;

/**
 * The precompiled pattern is the entire front-end cost, so its behaviour is pinned here.
 */
final class SignaturesTest extends TestCase {

	/**
	 * Known agents map to their ids; the longer token wins where one contains another.
	 *
	 * @return array
	 */
	public function agents(): array {
		return array(
			array( 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', 1 ),
			array( 'Mozilla/5.0 (compatible; ChatGPT-User/1.0; +https://openai.com/bot)', 2 ),
			array( 'Mozilla/5.0 (compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)', 3 ),
			array( 'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 4 ),
			array( 'Mozilla/5.0 (compatible; Claude-User/1.0)', 5 ),
			array( 'Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)', 7 ),
			array( 'Mozilla/5.0 (compatible; Perplexity-User/1.0)', 8 ),
			array( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 9 ),
			array( 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', 12 ),
			array( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', 0 ),
			array( 'curl/8.4.0', 0 ),
			array( '', 0 ),
		);
	}

	/**
	 * Match.
	 *
	 * @dataProvider agents
	 * @param string $ua       UA.
	 * @param int    $expected Bot id.
	 */
	public function test_match( string $ua, int $expected ): void {
		$pattern = Signatures::compile();
		$this->assertSame( $expected, Signatures::match_ua( $ua, $pattern ) );
	}

	/**
	 * A token embedded in a larger word must not match ("MyGPTBotApp" is not GPTBot).
	 */
	public function test_word_boundary(): void {
		$this->assertSame( 0, Signatures::match_ua( 'Mozilla/5.0 MyGPTBot/1.0', Signatures::compile() ) );
	}

	/**
	 * Pattern is a valid PCRE and matches case-insensitively.
	 */
	public function test_pattern_is_valid_and_case_insensitive(): void {
		$pattern = Signatures::compile();
		$this->assertNotFalse( @preg_match( $pattern, '' ) ); // phpcs:ignore
		$this->assertSame( 1, Signatures::match_ua( 'gptbot/1.0', $pattern ) );
	}

	/**
	 * Ids are unique and robots-only tokens have no UA.
	 */
	public function test_registry_integrity(): void {
		$ids = array_keys( Signatures::registry() );
		$this->assertSame( $ids, array_unique( $ids ) );
		$this->assertNull( Signatures::registry()[10]['ua'] );
		$this->assertSame( 'Google-Extended', Signatures::registry()[10]['robots'] );
	}
}
