<?php
/**
 * Activation, capability, drop-in, migrations.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Tests\Integration;

use AiParseAble\Activation;
use AiParseAble\Migrations;
use AiParseAble\Plugin;
use WP_UnitTestCase;

/**
 * Runs inside wp-env against the core test suite.
 */
final class ActivationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Activation::activate( false );
	}

	public function test_tables_exist_after_activation(): void {
		$this->assertTrue( Plugin::instance()->repository()->tables_exist() );
		$this->assertSame( Migrations::SCHEMA_VERSION, (int) get_option( Migrations::OPTION ) );
	}

	public function test_administrator_receives_capability(): void {
		$this->assertTrue( get_role( 'administrator' )->has_cap( Activation::CAPABILITY ) );
	}

	public function test_pattern_is_compiled_into_the_autoloaded_option(): void {
		$pattern = Plugin::instance()->options()->get( 'ua_pattern' );
		$this->assertNotEmpty( $pattern );
		$this->assertSame( 1, preg_match( $pattern, 'Mozilla/5.0 (compatible; GPTBot/1.0)' ) );
	}

	public function test_every_option_read_on_plugins_loaded_is_autoloaded(): void {
		// Migrations::maybe_run() reads these on every request; a non-autoloaded option would cost a query per visitor.
		wp_cache_delete( 'alloptions', 'options' );
		$all = wp_load_alloptions();
		$this->assertArrayHasKey( Migrations::OPTION, $all );
		$this->assertArrayHasKey( Migrations::OPTION_PLUGIN, $all );
		$this->assertArrayHasKey( \AiParseAble\Support\Options::OPTION, $all );
	}

	public function test_upgrade_repairs_non_autoloaded_version_options(): void {
		update_option( Migrations::OPTION_PLUGIN, '0.0.1', false );
		wp_set_option_autoload_values( array( Migrations::OPTION => false ) );
		Migrations::maybe_run();
		wp_cache_delete( 'alloptions', 'options' );
		$all = wp_load_alloptions();
		$this->assertSame( AI_PARSEABLE_VERSION, $all[ Migrations::OPTION_PLUGIN ] ?? null );
		$this->assertArrayHasKey( Migrations::OPTION, $all );
	}

	public function test_drop_in_installed_or_notice_recorded(): void {
		$drop_in = Plugin::instance()->drop_in();
		if ( wp_is_writable( WPMU_PLUGIN_DIR ) || wp_is_writable( WP_CONTENT_DIR ) ) {
			$this->assertFileExists( $drop_in->path() );
			$this->assertTrue( $drop_in->is_current() );
			$this->assertSame( 0, (int) exec( 'php -l ' . escapeshellarg( $drop_in->path() ) . ' > /dev/null 2>&1; echo $?' ) );
		} else {
			$this->assertSame( 'mu-unwritable', Plugin::instance()->options()->get( 'drop_in_notice' ) );
		}
	}

	/**
	 * Fixture: mu-plugins directory that is not writable — must fall back, never fatal.
	 */
	public function test_unwritable_mu_plugins_falls_back_to_php_logging(): void {
		$drop_in = Plugin::instance()->drop_in();
		$drop_in->remove();
		if ( 0 === posix_geteuid() ) {
			$this->markTestSkipped( 'Root ignores file permissions.' );
		}
		chmod( WPMU_PLUGIN_DIR, 0555 );
		try {
			$this->assertFalse( $drop_in->install() );
			$this->assertSame( 'php', $drop_in->mode() );
			$this->assertSame( 'mu-unwritable', Plugin::instance()->options()->get( 'drop_in_notice' ) );
		} finally {
			chmod( WPMU_PLUGIN_DIR, 0755 );
		}
	}

	/**
	 * Migrations run from plugins_loaded, never from the activation hook.
	 */
	public function test_migration_runs_when_version_differs(): void {
		update_option( Migrations::OPTION_PLUGIN, '0.0.1' );
		update_option( Migrations::OPTION, 0 );
		Migrations::maybe_run();
		$this->assertSame( AI_PARSEABLE_VERSION, get_option( Migrations::OPTION_PLUGIN ) );
		$this->assertSame( Migrations::SCHEMA_VERSION, (int) get_option( Migrations::OPTION ) );
	}
}
