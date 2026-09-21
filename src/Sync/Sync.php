<?php
/**
 * Sync module.
 *
 * @package AiParseAble
 */

namespace AiParseAble\Sync;

use AiParseAble\Module;
use AiParseAble\Support\Options;

/**
 * Keeps the API key out of debug output. Site Health's debug tab lists options only via filters, so we
 * hook the plugin's own debug info and redact.
 */
final class Sync implements Module {

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
		add_filter( 'debug_information', array( $this, 'debug_info' ) );
	}

	/**
	 * Site Health debug section, with the key redacted.
	 *
	 * @param array<mixed> $info Sections.
	 * @return array<mixed>
	 */
	public function debug_info( $info ): array {
		$all                  = $this->options->all();
		$info['ai-parseable'] = array(
			'label'  => __( 'AI ParseAble', 'ai-parseable' ),
			'fields' => array(
				'version'   => array(
					'label' => __( 'Version', 'ai-parseable' ),
					'value' => AI_PARSEABLE_VERSION,
				),
				'retention' => array(
					'label' => __( 'Retention (days)', 'ai-parseable' ),
					'value' => (int) $all['retention_days'],
				),
				'ip_mode'   => array(
					'label' => __( 'IP storage', 'ai-parseable' ),
					'value' => (string) $all['ip_mode'],
				),
				'api_key'   => array(
					'label'   => __( 'Checker API key', 'ai-parseable' ),
					'value'   => '' !== (string) $all['api_key'] ? __( 'Set (redacted)', 'ai-parseable' ) : __( 'Not set', 'ai-parseable' ),
					'private' => true,
				),
			),
		);
		return $info;
	}
}
