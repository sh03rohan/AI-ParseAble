<?php
/**
 * Crawler registry and user-agent matching.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Logger;

/**
 * The registry is a PHP array, not a table: crawler names change, a join does not earn its keep.
 *
 * Bot ids are stable integers and must never be reused.
 */
final class Signatures {

	const TYPE_TRAINING = 'training';
	const TYPE_ANSWER   = 'answer';
	const TYPE_SEARCH   = 'search';

	const VERIFY_RANGES = 'ranges';
	const VERIFY_RDNS   = 'rdns';
	const VERIFY_NONE   = 'none';

	/**
	 * Registry, keyed by stable id.
	 *
	 * Fields: name, vendor, type, ua (substring token, null for robots-only tokens), robots (robots.txt token),
	 * verify (method), ranges (JSON URLs), rdns (hostname suffixes), docs (vendor documentation URL).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function registry(): array {
		static $registry = null;
		if ( null !== $registry ) {
			return $registry;
		}

		$bots = array(
			1  => array(
				'name'   => 'GPTBot',
				'vendor' => 'OpenAI',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'GPTBot',
				'robots' => 'GPTBot',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://openai.com/gptbot.json' ),
				'rdns'   => array(),
				'docs'   => 'https://platform.openai.com/docs/bots',
			),
			2  => array(
				'name'   => 'ChatGPT-User',
				'vendor' => 'OpenAI',
				'type'   => self::TYPE_ANSWER,
				'ua'     => 'ChatGPT-User',
				'robots' => 'ChatGPT-User',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://openai.com/chatgpt-user.json' ),
				'rdns'   => array(),
				'docs'   => 'https://platform.openai.com/docs/bots',
			),
			3  => array(
				'name'   => 'OAI-SearchBot',
				'vendor' => 'OpenAI',
				'type'   => self::TYPE_SEARCH,
				'ua'     => 'OAI-SearchBot',
				'robots' => 'OAI-SearchBot',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://openai.com/searchbot.json' ),
				'rdns'   => array(),
				'docs'   => 'https://platform.openai.com/docs/bots',
			),
			4  => array(
				'name'   => 'ClaudeBot',
				'vendor' => 'Anthropic',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'ClaudeBot',
				'robots' => 'ClaudeBot',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler',
			),
			5  => array(
				'name'   => 'Claude-User',
				'vendor' => 'Anthropic',
				'type'   => self::TYPE_ANSWER,
				'ua'     => 'Claude-User',
				'robots' => 'Claude-User',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler',
			),
			6  => array(
				'name'   => 'Claude-SearchBot',
				'vendor' => 'Anthropic',
				'type'   => self::TYPE_SEARCH,
				'ua'     => 'Claude-SearchBot',
				'robots' => 'Claude-SearchBot',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler',
			),
			7  => array(
				'name'   => 'PerplexityBot',
				'vendor' => 'Perplexity',
				'type'   => self::TYPE_SEARCH,
				'ua'     => 'PerplexityBot',
				'robots' => 'PerplexityBot',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://www.perplexity.com/perplexitybot.json' ),
				'rdns'   => array(),
				'docs'   => 'https://docs.perplexity.ai/guides/bots',
			),
			8  => array(
				'name'   => 'Perplexity-User',
				'vendor' => 'Perplexity',
				'type'   => self::TYPE_ANSWER,
				'ua'     => 'Perplexity-User',
				'robots' => 'Perplexity-User',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://www.perplexity.com/perplexity-user.json' ),
				'rdns'   => array(),
				'docs'   => 'https://docs.perplexity.ai/guides/bots',
			),
			9  => array(
				'name'   => 'Googlebot',
				'vendor' => 'Google',
				'type'   => self::TYPE_SEARCH,
				'ua'     => 'Googlebot',
				'robots' => 'Googlebot',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://developers.google.com/static/search/apis/ipranges/googlebot.json' ),
				'rdns'   => array( '.googlebot.com', '.google.com' ),
				'docs'   => 'https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers',
			),
			10 => array(
				'name'   => 'Google-Extended',
				'vendor' => 'Google',
				'type'   => self::TYPE_TRAINING,
				'ua'     => null,
				'robots' => 'Google-Extended',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers',
			),
			11 => array(
				'name'   => 'GoogleOther',
				'vendor' => 'Google',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'GoogleOther',
				'robots' => 'GoogleOther',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json' ),
				'rdns'   => array( '.googlebot.com', '.google.com' ),
				'docs'   => 'https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers',
			),
			12 => array(
				'name'   => 'Bingbot',
				'vendor' => 'Microsoft',
				'type'   => self::TYPE_SEARCH,
				'ua'     => 'bingbot',
				'robots' => 'Bingbot',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://www.bing.com/toolbox/bingbot.json' ),
				'rdns'   => array( '.search.msn.com' ),
				'docs'   => 'https://www.bing.com/webmasters/help/which-crawlers-does-bing-use-8c184ec0',
			),
			13 => array(
				'name'   => 'Applebot',
				'vendor' => 'Apple',
				'type'   => self::TYPE_SEARCH,
				'ua'     => 'Applebot',
				'robots' => 'Applebot',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://search.developer.apple.com/applebot.json' ),
				'rdns'   => array( '.applebot.apple.com' ),
				'docs'   => 'https://support.apple.com/en-us/119829',
			),
			14 => array(
				'name'   => 'Applebot-Extended',
				'vendor' => 'Apple',
				'type'   => self::TYPE_TRAINING,
				'ua'     => null,
				'robots' => 'Applebot-Extended',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://support.apple.com/en-us/119829',
			),
			15 => array(
				'name'   => 'CCBot',
				'vendor' => 'Common Crawl',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'CCBot',
				'robots' => 'CCBot',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://commoncrawl.org/ccbot',
			),
			16 => array(
				'name'   => 'Meta-ExternalAgent',
				'vendor' => 'Meta',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'meta-externalagent',
				'robots' => 'meta-externalagent',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/',
			),
			17 => array(
				'name'   => 'Meta-ExternalFetcher',
				'vendor' => 'Meta',
				'type'   => self::TYPE_ANSWER,
				'ua'     => 'meta-externalfetcher',
				'robots' => 'meta-externalfetcher',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/',
			),
			18 => array(
				'name'   => 'Amazonbot',
				'vendor' => 'Amazon',
				'type'   => self::TYPE_ANSWER,
				'ua'     => 'Amazonbot',
				'robots' => 'Amazonbot',
				'verify' => self::VERIFY_RDNS,
				'ranges' => array(),
				'rdns'   => array( '.crawl.amazonbot.amazon' ),
				'docs'   => 'https://developer.amazon.com/amazonbot',
			),
			19 => array(
				'name'   => 'Bytespider',
				'vendor' => 'ByteDance',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'Bytespider',
				'robots' => 'Bytespider',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => '',
			),
			20 => array(
				'name'   => 'DuckAssistBot',
				'vendor' => 'DuckDuckGo',
				'type'   => self::TYPE_ANSWER,
				'ua'     => 'DuckAssistBot',
				'robots' => 'DuckAssistBot',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://duckduckgo.com/duckduckgo-help-pages/results/duckassistbot/',
			),
			21 => array(
				'name'   => 'MistralAI-User',
				'vendor' => 'Mistral',
				'type'   => self::TYPE_ANSWER,
				'ua'     => 'MistralAI-User',
				'robots' => 'MistralAI-User',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://docs.mistral.ai/robots/',
			),
			22 => array(
				'name'   => 'cohere-ai',
				'vendor' => 'Cohere',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'cohere-ai',
				'robots' => 'cohere-ai',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => '',
			),
			23 => array(
				'name'   => 'YouBot',
				'vendor' => 'You.com',
				'type'   => self::TYPE_SEARCH,
				'ua'     => 'YouBot',
				'robots' => 'YouBot',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://about.you.com/youbot/',
			),
			24 => array(
				'name'   => 'Diffbot',
				'vendor' => 'Diffbot',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'Diffbot',
				'robots' => 'Diffbot',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => 'https://docs.diffbot.com/docs/diffbot-crawler-and-fetchers',
			),
			25 => array(
				'name'   => 'Google-CloudVertexBot',
				'vendor' => 'Google',
				'type'   => self::TYPE_ANSWER,
				'ua'     => 'Google-CloudVertexBot',
				'robots' => 'Google-CloudVertexBot',
				'verify' => self::VERIFY_RANGES,
				'ranges' => array( 'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json' ),
				'rdns'   => array( '.googlebot.com', '.google.com' ),
				'docs'   => 'https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers',
			),
			26 => array(
				'name'   => 'anthropic-ai',
				'vendor' => 'Anthropic',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'anthropic-ai',
				'robots' => 'anthropic-ai',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => '',
			),
			27 => array(
				'name'   => 'PetalBot',
				'vendor' => 'Huawei',
				'type'   => self::TYPE_SEARCH,
				'ua'     => 'PetalBot',
				'robots' => 'PetalBot',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array( '.petalsearch.com' ),
				'docs'   => '',
			),
			28 => array(
				'name'   => 'Timpibot',
				'vendor' => 'Timpi',
				'type'   => self::TYPE_TRAINING,
				'ua'     => 'Timpibot',
				'robots' => 'Timpibot',
				'verify' => self::VERIFY_NONE,
				'ranges' => array(),
				'rdns'   => array(),
				'docs'   => '',
			),
		);

		/**
		 * Filter the crawler registry. Add-ons may add entries; ids above 1000 are reserved for them.
		 *
		 * @param array<mixed> $bots Registry keyed by stable integer id.
		 */
		$registry = (array) apply_filters( 'crawlledger_signatures', $bots );
		return $registry;
	}

	/**
	 * One entry.
	 *
	 * @param int $id Bot id.
	 * @return array<mixed>|null
	 */
	public static function get( int $id ): ?array {
		$registry = self::registry();
		return isset( $registry[ $id ] ) ? $registry[ $id ] : null;
	}

	/**
	 * Build the single regex used on every request. Longer tokens first so "Perplexity-User" wins over "PerplexityBot".
	 *
	 * @return string PCRE pattern.
	 */
	public static function compile(): string {
		$tokens = array();
		foreach ( self::registry() as $bot ) {
			if ( ! empty( $bot['ua'] ) ) {
				$tokens[] = (string) $bot['ua'];
			}
		}
		usort(
			$tokens,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		$quoted = array_map(
			static function ( $t ) {
				return preg_quote( $t, '~' );
			},
			$tokens
		);
		return '~(?<![A-Za-z0-9])(' . implode( '|', $quoted ) . ')~i';
	}

	/**
	 * Map a matched token back to its id. Case-insensitive.
	 *
	 * @param string $token Matched substring.
	 * @return int 0 when unknown.
	 */
	public static function id_for_token( string $token ): int {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			foreach ( self::registry() as $id => $bot ) {
				if ( ! empty( $bot['ua'] ) ) {
					$map[ strtolower( (string) $bot['ua'] ) ] = (int) $id;
				}
			}
		}
		$key = strtolower( $token );
		return isset( $map[ $key ] ) ? $map[ $key ] : 0;
	}

	/**
	 * Match a user agent with a precompiled pattern.
	 *
	 * @param string $ua      User agent.
	 * @param string $pattern Pattern from compile().
	 * @return int Bot id, 0 when no match.
	 */
	public static function match_ua( string $ua, string $pattern ): int {
		if ( '' === $ua || '' === $pattern ) {
			return 0;
		}
		if ( ! preg_match( $pattern, $ua, $m ) ) {
			return 0;
		}
		return self::id_for_token( $m[1] );
	}

	/**
	 * Bots grouped by type for the access-control UI.
	 *
	 * @return array<string, int[]>
	 */
	public static function by_type(): array {
		$groups = array(
			self::TYPE_TRAINING => array(),
			self::TYPE_ANSWER   => array(),
			self::TYPE_SEARCH   => array(),
		);
		foreach ( self::registry() as $id => $bot ) {
			$groups[ $bot['type'] ][] = (int) $id;
		}
		return $groups;
	}
}
