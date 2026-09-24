<?php
/**
 * Virtual llms.txt.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\LlmsTxt;

use CrawlLedger\Module;
use CrawlLedger\Support\Options;

/**
 * Served through a rewrite rule and template_redirect. No physical file: many roots are not writable
 * and a file survives uninstall as litter. Not served until the owner has written a summary.
 */
final class LlmsTxt implements Module {

	const QUERY_VAR = 'crawlledger_llms';

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Hooks. add_rewrite_rule only registers in memory; flushing happens on activation and settings save.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rule' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve' ), 0 );
	}

	/**
	 * Rewrite rule.
	 *
	 * @return void
	 */
	public function add_rule(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Query var.
	 *
	 * @param array<mixed> $vars Vars.
	 * @return array<mixed>
	 */
	public function query_vars( $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Whether the file would be served.
	 *
	 * @return bool
	 */
	public function is_ready(): bool {
		return (bool) $this->options->get( 'llms_enabled', true ) && '' !== trim( (string) $this->options->get( 'llms_summary', '' ) );
	}

	/**
	 * Serve on match.
	 *
	 * @return void
	 */
	public function maybe_serve(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR, '' ) ) {
			return;
		}
		if ( ! $this->is_ready() ) {
			// A real 404 rather than a stub — and not canonical's 301 to /llms.txt/, which agents would cache.
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			remove_action( 'template_redirect', 'redirect_canonical' );
			return;
		}
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text, built from sanitised parts in render().
		exit;
	}

	/**
	 * Build the document.
	 *
	 * @param array<string, mixed> $overrides Settings to use instead of the saved ones (for previews).
	 * @return string
	 */
	public function render( array $overrides = array() ): string {
		$get     = function ( $key, $fallback ) use ( $overrides ) {
			return array_key_exists( $key, $overrides ) ? $overrides[ $key ] : $this->options->get( $key, $fallback );
		};
		$name    = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$summary = wp_strip_all_tags( (string) $get( 'llms_summary', '' ) );
		$lines   = array( '# ' . $name, '' );
		foreach ( preg_split( '~\R{2,}~', trim( $summary ) ) as $para ) {
			$lines[] = '> ' . preg_replace( '~\s+~', ' ', trim( $para ) );
			$lines[] = '';
		}

		$pages = array_filter( array_map( 'intval', (array) $get( 'llms_pages', array() ) ) );
		if ( $pages ) {
			$lines[] = '## Pages';
			$lines[] = '';
			foreach ( $pages as $id ) {
				$post = get_post( $id );
				if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
					continue;
				}
				$lines[] = $this->entry( $post );
			}
			$lines[] = '';
		}

		if ( $get( 'llms_include_posts', false ) ) {
			$posts = get_posts(
				array(
					'post_type'        => 'post',
					'posts_per_page'   => 20,
					'post_status'      => 'publish',
					'suppress_filters' => false,
				)
			);
			if ( $posts ) {
				$lines[] = '## Recent posts';
				$lines[] = '';
				foreach ( $posts as $post ) {
					$lines[] = $this->entry( $post );
				}
				$lines[] = '';
			}
		}

		/**
		 * Filter the rendered llms.txt lines.
		 *
		 * @param string[] $lines Lines.
		 */
		$lines = (array) apply_filters( 'crawlledger_llms_lines', $lines );
		return rtrim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * One list entry.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private function entry( \WP_Post $post ): string {
		$title   = str_replace( array( '[', ']' ), '', wp_strip_all_tags( get_the_title( $post ) ) );
		$excerpt = wp_strip_all_tags( has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 30, '…' ) );
		$excerpt = preg_replace( '~\s+~', ' ', trim( $excerpt ) );
		return '- [' . $title . '](' . get_permalink( $post ) . ')' . ( '' !== $excerpt ? ': ' . $excerpt : '' );
	}
}
