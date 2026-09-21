=== AI ParseAble ===
Contributors: shrohan03
Tags: ai, crawlers, robots.txt, llms.txt, schema
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A first-party log of which AI crawlers visited your site, when, on which URL, and what they got back.

== Description ==

External checkers can tell you whether AI crawlers *can* reach your site. AI ParseAble tells you whether they *did*: which crawler, when, on which URL, and with what status — and whether the visit was genuinely from that vendor or a scraper wearing its name.

**What it does**

* **Crawler log** — records visits from GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, PerplexityBot, Googlebot, Bingbot, Applebot, CCBot, Amazonbot and more. Captured before plugins load via a tiny mu-plugin drop-in so page caches do not hide the traffic; written to a file queue, never to the database inline.
* **Verification** — forward-confirmed reverse DNS plus each vendor's published IP ranges. Spoofed hits are recorded as unverified and excluded from the default charts.
* **Honest coverage** — the dashboard states which logging mode is active and what it cannot see.
* **Crawler access control** — robots.txt rules through the WordPress filter, with training crawlers and answer crawlers presented as the two different decisions they are. Detects a physical robots.txt and other plugins that rewrite the output, and names them.
* **Schema gap filling** — merges missing price, currency, stock, dates and author into the graph your SEO plugin already outputs (Yoast, Rank Math, WooCommerce). Never emits a duplicate node. Shows a before/after of a real page from your site.
* **llms.txt** — served virtually, only once you have written a summary. No boilerplate.
* **Editor checks** — a block-editor sidebar with deterministic clarity checks: single H1, sequential heading levels, a factual opening paragraph, alt coverage, minimum length.

**What it does not do**

It is not an SEO plugin. It does not write titles, meta descriptions, sitemaps or canonical tags.

**Performance**

Non-bot front-end requests add zero database queries and a single regular-expression match. Bot hits are appended to a file at shutdown. Ingest, verification and aggregation run in cron in batches. Raw rows are kept for a short window; daily totals are kept forever.

== External services ==

This plugin can optionally connect to the AI ParseAble checker service (https://aiparseable.com) when you enter an API key on the Settings screen. Without a key, no data is sent anywhere and every feature works.

When you request a scan, the plugin sends your site's home URL and nothing else to `https://aiparseable.com/api/v1/scan`, authenticated with your API key, and receives a score and a list of findings. Terms: https://aiparseable.com/terms — Privacy policy: https://aiparseable.com/privacy

Separately, once a week the plugin fetches the published crawler IP range documents from the vendors themselves (for example https://openai.com/gptbot.json, https://developers.google.com/static/search/apis/ipranges/googlebot.json, https://www.perplexity.com/perplexitybot.json, https://www.bing.com/toolbox/bingbot.json, https://search.developer.apple.com/applebot.json). These requests carry the plugin's user agent and your site URL as a courtesy identifier and no other data. They are needed to verify crawler identity. A failed fetch keeps the last good copy and never interrupts logging.

== Privacy ==

The plugin records the IP address of requests that match a known AI crawler user agent. By default the address is truncated to /24 (IPv4) or /48 (IPv6) before storage; you can choose a salted hash or the full address in Settings. Verification always runs on the real address before it is reduced. Individual records are kept for the configured retention window; daily totals per crawler are kept indefinitely and contain no addresses. Log files live under `wp-content/uploads/ai-parseable/` with an `index.php` and a `.htaccess` deny rule. Personal-data exporter and eraser callbacks are registered; suggested privacy-policy text is provided under Settings → Privacy.

Uninstalling keeps your data by default. Turn off "Keep my data" in Settings before deleting the plugin to remove tables, options, the drop-in, cron events and the log directory.

== Installation ==

1. Upload the plugin and activate it.
2. On activation the plugin writes `wp-content/mu-plugins/ai-parseable-drop-in.php`. If that directory is not writable it falls back to PHP-level logging and says so on the dashboard.
3. Open **AI ParseAble** in the admin menu. Administrators receive the `aiparseable_manage` capability; grant it to other roles to delegate access.

== Frequently Asked Questions ==

= The dashboard shows zero hits but my server log shows crawlers. =

Check the coverage banner at the top of the Overview. If the drop-in is not installed, or a page cache serves responses before PHP runs (server-level caches such as LiteSpeed, Nginx FastCGI cache or a CDN), those hits never reach the plugin. Server-log ingestion is the only complete answer for those setups.

= Why does a crawler show as unverified? =

Either the request came from an IP the vendor does not publish (likely a scraper impersonating the crawler), or the vendor publishes no ranges and no reverse-DNS pattern, in which case the column shows "n/a".

= My robots.txt rules do nothing. =

If a physical `robots.txt` exists at the web root, WordPress does not serve the virtual one. The Crawlers tab detects this and offers to write a marked block into the physical file.

== Screenshots ==

1. Overview: coverage status, verified-only KPIs with period deltas, visits per day, and every crawler with its verified share.
2. Failing URLs, most-crawled pages and the latest visits feed, with cache-served and verified markers.
3. Crawler access control: answer and search crawlers are one decision, training crawlers another, with visit counts beside each rule.
4. Training crawlers blocked in one click; the resulting robots.txt block is shown below the tables.
5. Schema gap filling: a before/after of a real page from your site with the added properties listed.
6. llms.txt editor with a live preview of the served file.
7. Settings: retention, IP storage mode, per-crawler ceiling, optional scan sync and uninstall behaviour.

== Changelog ==

= 0.1.0 =
* Initial release.
