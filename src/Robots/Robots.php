<?php
/**
 * Crawler access control.
 *
 * @package CrawlLedger
 */

namespace CrawlLedger\Robots;

use CrawlLedger\Module;
use CrawlLedger\Support\Options;

/**
 * Writes rules through the robots_txt filter at priority 20 and detects everything that can silently
 * defeat it: a physical file, another plugin rewriting the output, a CDN above WordPress.
 */
final class Robots implements Module {

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
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'filter' ), 20, 2 );
	}

	/**
	 * Current rules text.
	 *
	 * @return string
	 */
	public function rules(): string {
		return Block::rules( (array) $this->options->get( 'robots', array() ) );
	}

	/**
	 * Append the managed block to the virtual robots.txt.
	 *
	 * @param string $output Existing output.
	 * @param bool   $is_public Whether the site is public.
	 * @return string
	 */
	public function filter( $output, $is_public ): string {
		$output = (string) $output;
		if ( ! $is_public ) {
			return $output;
		}
		$rules = $this->rules();
		if ( '' === $rules ) {
			return $output;
		}
		return rtrim( $output ) . "\n\n" . Block::wrap( $rules );
	}

	/**
	 * Inspect the final output and name whoever changed it. Turns a support ticket into a self-service fix.
	 *
	 * @return array<mixed>
	 */
	public function inspect(): array {
		$rules  = $this->rules();
		$output = (string) apply_filters( 'robots_txt', "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n", (bool) get_option( 'blog_public' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- deliberately running core's filter to inspect the final output.
		$intact = Block::intact( $output, $rules );

		return array(
			'rules'             => $rules,
			'output'            => $output,
			'intact'            => $intact,
			'other_filters'     => $this->other_filters(),
			'physical'          => PhysicalFile::exists(),
			'physical_content'  => PhysicalFile::read(),
			'physical_writable' => PhysicalFile::writable(),
			'physical_managed'  => PhysicalFile::exists() && false !== strpos( PhysicalFile::read(), Block::BEGIN ),
			'public'            => (bool) get_option( 'blog_public' ),
			// robots.txt is per host: a sub-site of a subdirectory network never serves one of its own.
			'subdirectory_site' => is_multisite() && ! is_main_site() && ! is_subdomain_install(),
			'main_site_url'     => is_multisite() ? get_home_url( get_main_site_id(), '/' ) : '',
		);
	}

	/**
	 * Other callbacks on robots_txt, mapped to plugin names where possible.
	 *
	 * @return array<mixed> List of ['name' => string, 'priority' => int].
	 */
	private function other_filters(): array {
		global $wp_filter;
		$found = array();
		if ( empty( $wp_filter['robots_txt'] ) || ! ( $wp_filter['robots_txt'] instanceof \WP_Hook ) ) {
			return $found;
		}
		foreach ( $wp_filter['robots_txt']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'];
				if ( is_array( $fn ) && isset( $fn[0] ) && $fn[0] instanceof self ) {
					continue;
				}
				$found[] = array(
					'name'     => $this->describe_callback( $fn ),
					'priority' => (int) $priority,
				);
			}
		}
		return $found;
	}

	/**
	 * Map a callback to the plugin that owns it, via the file it was defined in.
	 *
	 * @param mixed $callback Callable.
	 * @return string
	 */
	private function describe_callback( $callback ): string {
		try {
			if ( is_array( $callback ) ) {
				$ref = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$ref = new \ReflectionMethod( $callback );
			} elseif ( is_string( $callback ) ) {
				$ref = new \ReflectionFunction( $callback );
			} elseif ( $callback instanceof \Closure ) {
				$ref = new \ReflectionFunction( $callback );
			} else {
				return __( 'Unknown callback', 'crawlledger-ai-crawler-log' );
			}
			$file = (string) $ref->getFileName();
		} catch ( \ReflectionException $e ) {
			return __( 'Unknown callback', 'crawlledger-ai-crawler-log' );
		}

		$plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );
		$file        = wp_normalize_path( $file );
		if ( 0 === strpos( $file, $plugins_dir ) ) {
			$slug = explode( '/', ltrim( substr( $file, strlen( $plugins_dir ) ), '/' ) )[0];
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( get_plugins() as $basename => $data ) {
				if ( 0 === strpos( $basename, $slug . '/' ) || $basename === $slug ) {
					return (string) $data['Name'];
				}
			}
			return $slug;
		}
		if ( 0 === strpos( $file, wp_normalize_path( get_theme_root() ) ) ) {
			return __( 'Active theme', 'crawlledger-ai-crawler-log' );
		}
		if ( 0 === strpos( $file, wp_normalize_path( ABSPATH . WPINC ) ) ) {
			return __( 'WordPress core', 'crawlledger-ai-crawler-log' );
		}
		return basename( $file );
	}
}
