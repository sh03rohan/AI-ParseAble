module.exports = {
	root: true,
	// The plain "recommended" preset loads @typescript-eslint even for JS-only projects; this one does not.
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended-with-formatting' ],
	globals: { crawlLedger: 'readonly', wp: 'readonly', ResizeObserver: 'readonly' },
	overrides: [ { files: [ '**/*.test.js' ], env: { jest: true } } ],
	rules: {
		'jsdoc/require-param': 'off',
		'jsdoc/require-param-type': 'off',
		'@wordpress/i18n-translator-comments': 'error',
		'@wordpress/i18n-no-collapsible-whitespace': 'error',
	},
};
