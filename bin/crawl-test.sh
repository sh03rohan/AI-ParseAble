#!/usr/bin/env bash
# Phase 1 verification: hit the site with real-looking and forged crawler agents and a normal browser,
# then ingest and show what landed. Run from the plugin directory.
#
#   bin/crawl-test.sh https://example.test [wp-path]
set -euo pipefail
SITE="${1:?site URL required}"
WP_PATH="${2:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

agents=(
  "Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)"
  "Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)"
  "Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)"
  "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"
  "Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)"
  "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15"
)
paths=( "/" "/?s=widgets" "/this-page-does-not-exist-$RANDOM/" )

for ua in "${agents[@]}"; do
  for p in "${paths[@]}"; do
    code=$(curl -k -s -o /dev/null -w "%{http_code}" -A "$ua" "${SITE}${p}")
    printf '%-4s %-45s %s\n' "$code" "${ua:0:45}" "$p"
  done
done

echo; echo "Queue files:"; ls -la "${WP_PATH}/wp-content/uploads/crawlledger/queue/" 2>/dev/null || echo "(none)"

if command -v wp >/dev/null 2>&1; then
  echo; echo "Ingesting…"
  wp --path="$WP_PATH" eval 'echo wp_json_encode( ( new CrawlLedger\Logger\Ingest( CrawlLedger\Plugin::instance()->options(), CrawlLedger\Plugin::instance()->repository(), CrawlLedger\Plugin::instance()->queue(), new CrawlLedger\Logger\Verifier( new CrawlLedger\Logger\Ranges() ) ) )->run() ), "\n";'
  wp --path="$WP_PATH" db query "SELECT hit_at, bot_id, url, status, verified FROM $(wp --path="$WP_PATH" db prefix)crawlledger_hits ORDER BY id DESC LIMIT 20"
else
  echo; echo "wp-cli not found: trigger ingest from the dashboard ('Ingest queue now') or wait for cron."
fi
