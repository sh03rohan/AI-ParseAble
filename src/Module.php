<?php
/**
 * Module contract.
 *
 * @package AiParseAble
 */

namespace AiParseAble;

/**
 * Every feature is a module. Constructors assign dependencies; register() adds hooks; work happens in callbacks.
 */
interface Module {

	/**
	 * Register hooks. add_action / add_filter only — no work here.
	 *
	 * @return void
	 */
	public function register(): void;
}
