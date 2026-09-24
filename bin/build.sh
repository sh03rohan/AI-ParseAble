#!/usr/bin/env bash
# Release build. Runs the steps in the documented order and produces ../crawlledger-ai-crawler-log.zip.
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
	$WP_BIN i18n make-pot . languages/crawlledger-ai-crawler-log.pot --slug=crawlledger-ai-crawler-log --domain=crawlledger-ai-crawler-log --exclude=node_modules,vendor,tests,ui,bin
fi

OUT="$(cd .. && pwd)/crawlledger-ai-crawler-log.zip"
rm -f "$OUT"
if command -v wp >/dev/null 2>&1; then
	wp package install wp-cli/dist-archive-command:@stable >/dev/null 2>&1 || true
	wp dist-archive . "$OUT" --plugin-dirname=crawlledger-ai-crawler-log
else
	STAGE="$(mktemp -d)/crawlledger-ai-crawler-log"
	mkdir -p "$STAGE"
	rsync -a --exclude-from=.distignore --exclude='.*' ./ "$STAGE/"
	( cd "$(dirname "$STAGE")" && zip -qr "$OUT" crawlledger-ai-crawler-log )
	rm -rf "$(dirname "$STAGE")"
fi
echo "Built $OUT ($(du -h "$OUT" | cut -f1))"
