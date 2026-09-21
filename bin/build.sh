#!/usr/bin/env bash
# Release build. Runs the steps in the documented order and produces ../ai-parseable.zip.
# Uses `wp dist-archive` when wp-cli is present; otherwise stages the tree from .distignore with rsync.
set -euo pipefail
cd "$(dirname "$0")/.."
COMPOSER="${COMPOSER_BIN:-composer}"

$COMPOSER install --no-dev --no-interaction --prefer-dist --no-progress
$COMPOSER dump-autoload --classmap-authoritative --no-dev
npm ci --no-audit --no-fund --loglevel=error
npm run build
php bin/version.php --check

# Translation template. Language packs come from translate.wordpress.org; the POT is the source they build from.
WP_BIN="${WP_BIN:-wp}"
if command -v "$WP_BIN" >/dev/null 2>&1 || [ -f "$WP_BIN" ]; then
	$WP_BIN i18n make-pot . languages/ai-parseable.pot --slug=ai-parseable --domain=ai-parseable --exclude=node_modules,vendor,tests,ui,bin
fi

OUT="$(cd .. && pwd)/ai-parseable.zip"
rm -f "$OUT"
if command -v wp >/dev/null 2>&1; then
	wp package install wp-cli/dist-archive-command:@stable >/dev/null 2>&1 || true
	wp dist-archive . "$OUT" --plugin-dirname=ai-parseable
else
	STAGE="$(mktemp -d)/ai-parseable"
	mkdir -p "$STAGE"
	rsync -a --exclude-from=.distignore --exclude='.*' ./ "$STAGE/"
	( cd "$(dirname "$STAGE")" && zip -qr "$OUT" ai-parseable )
	rm -rf "$(dirname "$STAGE")"
fi
echo "Built $OUT ($(du -h "$OUT" | cut -f1))"
