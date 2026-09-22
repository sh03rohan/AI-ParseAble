=== AI ParseAble – AI Crawler Log & Access Control for GPTBot, ClaudeBot and other AI bots ===
Contributors: shrohan03
Tags: ai, seo, crawler, robots.txt, llms.txt
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See which AI crawlers visit your site (GPTBot, ClaudeBot, PerplexityBot), verify they are real, control them in robots.txt and serve llms.txt.

== Description ==

**AI ParseAble is an AI crawler analytics and control plugin for WordPress.** It keeps a first-party log of every visit from AI bots such as OpenAI's GPTBot and ChatGPT-User, Anthropic's ClaudeBot, PerplexityBot, Google-Extended, Bingbot, Applebot, Amazonbot, Bytespider, CCBot and Meta's crawlers — when they came, which URL they fetched and what response they got. It verifies each visit against the vendor's published IP ranges, lets you allow or block each AI bot in robots.txt, fills the gaps in your schema markup and serves an llms.txt file for AI assistants.

External AI visibility checkers can tell you whether AI crawlers *can* reach your site. AI ParseAble tells you whether they *did* — and whether the visitor was genuinely from that vendor or a scraper wearing its name.

= AI crawler log and analytics =

* Records visits from 26 known AI crawlers: GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, Claude-User, Claude-SearchBot, anthropic-ai, PerplexityBot, Perplexity-User, Googlebot, GoogleOther, Google-Extended, Bingbot, Applebot, Applebot-Extended, Amazonbot, Bytespider, CCBot, cohere-ai, DuckAssistBot, Diffbot, MistralAI-User, meta-externalagent, meta-externalfetcher, PetalBot, Timpibot and YouBot.
* Captures the hit the moment the plugin loads, before any hook runs, and writes it to a file queue at shutdown — never to the database inline.
* Writes to a file queue, never to the database inline: zero database queries and a single regular-expression match on ordinary visitor requests.
* Dashboard with visits per day, error rate, distinct crawlers, most-crawled pages, failing URLs and a live feed of the latest AI bot visits.

= Bot verification: real GPTBot or a fake? =

Many scrapers impersonate GPTBot or Googlebot. AI ParseAble verifies every AI crawler visit with forward-confirmed reverse DNS and each vendor's published IP ranges. Spoofed hits are recorded as unverified and excluded from the default charts, so your AI traffic numbers are real.

= Block or allow AI bots in robots.txt =

Control AI crawler access per bot through the WordPress robots.txt filter. Training crawlers (GPTBot, ClaudeBot, CCBot, Bytespider) and answer or search crawlers (ChatGPT-User, OAI-SearchBot, PerplexityBot, Claude-SearchBot) are presented as the two different decisions they are: blocking a training bot does not remove you from AI answers; blocking an answer bot does. The plugin detects a physical robots.txt file and other plugins that rewrite robots.txt output, and names them.

= Schema markup gap filling for AI search =

Merges missing price, currency, stock, dates and author into the structured data your SEO plugin already outputs (Yoast SEO, Rank Math, WooCommerce). Never emits a duplicate Product or Article node. Shows a before/after of a real page from your site.

= llms.txt for AI assistants =

Serves an llms.txt file virtually, only once you have written a summary, with a live preview in the editor. No boilerplate, no fake content.

= Content clarity checks in the block editor =

A block-editor sidebar with deterministic checks that help AI crawlers and answer engines read your content: a single H1, sequential heading levels, a factual opening paragraph, image alt text coverage and minimum length.

= Honest coverage =

The dashboard states which logging mode is active and what it cannot see, so you never mistake silence for "no AI traffic".

**What it does not do**

AI ParseAble is not a general SEO plugin. It does not write titles, meta descriptions, sitemaps or canonical tags, and it does not rewrite your content.

**Performance and privacy**

Non-bot front-end requests add zero database queries. Bot hits are appended to a file at shutdown. Ingest, verification and aggregation run in cron in batches. Raw rows are kept for a short window; daily totals are kept forever. IP addresses are truncated by default, personal-data exporter and eraser are registered, and multisite is supported.

== External services ==

The plugin does not connect to any service of its own, has no accounts or API keys, and sends no analytics or telemetry. It makes exactly one kind of outbound request:

**Crawler IP-range documents, fetched from the crawler vendors themselves.** To verify that a visit claiming to be a given crawler really came from that vendor, the plugin needs the IP ranges each vendor publishes. Once a week (and once shortly after activation) a background job fetches these public JSON documents:

* OpenAI — `https://openai.com/gptbot.json`, `https://openai.com/chatgpt-user.json`, `https://openai.com/searchbot.json` — [Terms of use](https://openai.com/policies/terms-of-use), [Privacy policy](https://openai.com/policies/privacy-policy)
* Google — `https://developers.google.com/static/search/apis/ipranges/googlebot.json`, `https://developers.google.com/static/search/apis/ipranges/special-crawlers.json` — [Terms of service](https://policies.google.com/terms), [Privacy policy](https://policies.google.com/privacy)
* Perplexity — `https://www.perplexity.com/perplexitybot.json`, `https://www.perplexity.com/perplexity-user.json` — [Terms of service](https://www.perplexity.ai/hub/legal/terms-of-service), [Privacy policy](https://www.perplexity.ai/hub/legal/privacy-policy)
* Microsoft Bing — `https://www.bing.com/toolbox/bingbot.json` — [Terms of use](https://www.microsoft.com/en-us/servicesagreement), [Privacy statement](https://privacy.microsoft.com/privacystatement)
* Apple — `https://search.developer.apple.com/applebot.json` — [Terms of use](https://www.apple.com/legal/internet-services/terms/site.html), [Privacy policy](https://www.apple.com/legal/privacy/)

What is sent: a plain HTTP GET with the plugin's user agent (`AI ParseAble/<version>; <your site URL>`) so vendors can see who is fetching. No visitor data, no log data and nothing about your site's content is sent. The fetch runs in cron, never during a visitor's request. A failed fetch keeps the last good copy and never interrupts logging.

**Reverse DNS lookups.** For crawlers that publish a hostname pattern instead of IP ranges (Googlebot, Bingbot, Applebot, Amazonbot, PetalBot), verification performs a reverse and forward DNS lookup of the crawler's IP address through your server's normal DNS resolver, exactly as a web server log analyser would. Results are cached for 24 hours.

== Privacy ==

The plugin records the IP address of requests that match a known AI crawler user agent. By default the address is truncated to /24 (IPv4) or /48 (IPv6) before storage; you can choose a salted hash or the full address in Settings. Verification always runs on the real address before it is reduced. Individual records are kept for the configured retention window; daily totals per crawler are kept indefinitely and contain no addresses. Log files live under `wp-content/uploads/ai-parseable/` with an `index.php` and a `.htaccess` deny rule. Personal-data exporter and eraser callbacks are registered; suggested privacy-policy text is provided under Settings → Privacy.

Uninstalling keeps your data by default. Turn off "Keep my data" in Settings before deleting the plugin to remove tables, options, cron events and the log directory.

== Installation ==

1. Upload the plugin and activate it.
2. Activation creates the tables and a private log directory under `wp-content/uploads/ai-parseable/`. Nothing is written anywhere else.
3. Open **AI ParseAble** in the admin menu. Administrators receive the `aiparseable_manage` capability; grant it to other roles to delegate access.

== Frequently Asked Questions ==

= Which AI crawlers does the plugin detect? =

GPTBot, ChatGPT-User and OAI-SearchBot (OpenAI); ClaudeBot, Claude-User, Claude-SearchBot and anthropic-ai (Anthropic); PerplexityBot and Perplexity-User; Googlebot, GoogleOther and Google-Extended; Bingbot; Applebot and Applebot-Extended; Amazonbot; Bytespider (ByteDance); CCBot (Common Crawl); cohere-ai; DuckAssistBot; Diffbot; MistralAI-User; meta-externalagent and meta-externalfetcher (Meta); PetalBot; Timpibot; YouBot. New crawlers are added in plugin updates.

= How do I block ChatGPT or GPTBot from crawling my site? =

Open the Crawlers tab, set GPTBot (training) or ChatGPT-User and OAI-SearchBot (answers and search) to Block, and save. The rule is written to your robots.txt. Blocking GPTBot stops OpenAI from using your content for model training; blocking ChatGPT-User and OAI-SearchBot removes your site from ChatGPT answers and search results — they are separate decisions.

= How do I know whether an AI bot visit is real? =

Every logged visit is checked against the vendor's published IP ranges and, where available, forward-confirmed reverse DNS. Verified visits show a check mark; unverified ones are likely scrapers impersonating the crawler and are excluded from the default charts.

= What is llms.txt and do I need it? =

llms.txt is a plain-text file at the root of your site that gives AI assistants a short, factual summary of what the site is about. The plugin serves it only after you write the summary, so you never publish boilerplate.

= The dashboard shows zero hits but my server log shows crawlers. =

Check the coverage banner at the top of the Overview. If a page cache serves responses before PHP runs (advanced-cache.php, server-level caches such as LiteSpeed or Nginx FastCGI cache, or a CDN), those hits never reach WordPress and so never reach the plugin. Server-log ingestion is the only complete answer for those setups.

= Why does a crawler show as unverified? =

Either the request came from an IP the vendor does not publish (likely a scraper impersonating the crawler), or the vendor publishes no ranges and no reverse-DNS pattern, in which case the column shows "n/a".

= My robots.txt rules do nothing. =

If a physical `robots.txt` exists at the web root, WordPress does not serve the virtual one. The Crawlers tab detects this and offers to write a marked block into the physical file.

= Does it slow down my site? =

No. Ordinary visitors trigger zero database queries and one regular-expression match. AI crawler hits are appended to a file after the response is sent and processed later in the background.

= Does it work with WooCommerce, Yoast SEO and Rank Math? =

Yes. The schema gap filling detects the provider and only adds properties that are missing from the graph it already outputs — it never creates a second Product node.

= Is it GDPR friendly? =

IP addresses of AI crawler visits are truncated by default (you can choose hashed or full). Personal-data exporter and eraser callbacks are registered and suggested privacy-policy text is provided.

== Screenshots ==

1. AI crawler analytics dashboard: coverage status, verified-only KPIs with period deltas, AI bot visits per day, and every crawler with its verified share.
2. Failing URLs that AI crawlers could not fetch, most-crawled pages and the latest AI bot visits feed, with cache-served and verified markers.
3. AI crawler access control: answer and search crawlers (ChatGPT-User, PerplexityBot) are one decision, training crawlers (GPTBot, ClaudeBot) another, with visit counts beside each robots.txt rule.
4. Blocking AI training crawlers in robots.txt in one click; the resulting robots.txt block is shown below the tables.
5. Schema markup gap filling: a before/after of a real WooCommerce product page with the added structured-data properties listed.
6. llms.txt editor for AI assistants with a live preview of the served file.
7. Settings: log retention, IP storage mode for privacy, per-crawler rate ceiling and uninstall behaviour.

== Changelog ==

= 0.1.0 =
* Initial release.
