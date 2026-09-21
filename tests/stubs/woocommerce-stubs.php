<?php
/**
 * WooCommerce symbols referenced behind function_exists() guards, for static analysis only.
 *
 * @package AiParseAble
 */

/**
 * @param string|float $number Number.
 * @param int|false    $dp     Decimals.
 * @return string
 */
function wc_format_decimal( $number, $dp = false ) {}

/**
 * @return int
 */
function wc_get_price_decimals() {}

/**
 * @return string
 */
function get_woocommerce_currency() {}

/**
 * @param int $product_id Product id.
 * @return \WC_Product|false
 */
function wc_get_product( $product_id ) {}

/**
 * @return \WooCommerce
 */
function WC() {} // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid

class WC_Product {
	/** @return string */ public function get_name() {}
	/** @return string */ public function get_short_description() {}
	/** @return string */ public function get_description() {}
	/** @return string */ public function get_sku() {}
	/** @return string */ public function get_price() {}
	/** @return bool */ public function is_in_stock() {}
	/** @return bool */ public function is_on_backorder() {}
}
class WC_Structured_Data {
	/** @return array<int, array<string, mixed>> */ public function get_data() {}
}
class WooCommerce {
	/** @var WC_Structured_Data|null */ public $structured_data;
}
