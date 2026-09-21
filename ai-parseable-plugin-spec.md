# AI ParseAble — WordPress plugin build spec

Scope: the WordPress plugin only. The checker website is specified separately.

This document is written on the assumption that the plugin will be read by other developers, reviewed by the wp.org team, and installed on sites the author will never see. Every decision below has a reason attached; where a shortcut exists, the reason it is rejected is stated, because that is the part a less experienced implementation gets wrong.

---

## 1. What this plugin is for

The checker website observes, from outside, whether AI crawlers *can* reach a site. The plugin observes, from inside, whether they *did* — which crawler, when, on which URL, and what status it got back.

That first-party log is the only thing here that no external scanner can ever replicate. It is the product. Everything else — robots control, schema augmentation, llms.txt — is table stakes that a dozen plugins already ship.

**Consequence for the build:** the logger gets the engineering effort and the risk budget. If the logger is unreliable, nothing else matters. If the logger is excellent, the rest can be modest.

**Explicit non-goal:** this is not an SEO plugin. It does not write titles, meta descriptions, sitemaps, or canonical tags. Yoast and Rank Math already own that and competing with them dilutes the positioning.

---

## 2. Compatibility floors

| | Floor | Reason |
|---|---|---|
| PHP | 7.4 | Still ~8% of installs. Typed properties and arrow functions are available; union types and enums are not. Do not use them. |
| WordPress | 6.4 | Covers the block-editor APIs and the REST improvements relied on below |
| MySQL | 5.7 / MariaDB 10.3 | No JSON column type — store JSON in `longtext` |
| Multisite | Supported, per-site settings | See §14 |

Declare `Requires PHP` and `Requires at least` in the header and enforce them with a guard that deactivates cleanly rather than fataling. A white screen on activation is the single fastest way to earn one-star reviews.

```php
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', __NAMESPACE__ . '\\php_notice' );
    return; // never throw, never deactivate silently
}
```

---

## 3. Repository layout

```
ai-parseable/
├── ai-parseable.php            bootstrap only — header, guards, autoload, boot
├── uninstall.php
├── composer.json               dev deps only; no runtime vendor shipped
├── package.json
├── .distignore
├── readme.txt
├── src/
│   ├── Plugin.php              container + module registration
│   ├── Activation.php          install, schema, drop-in, capabilities
│   ├── Migrations.php          versioned schema upgrades
│   ├── Logger/
│   │   ├── Collector.php       request capture
│   │   ├── Signatures.php      user-agent matching
│   │   ├── Verifier.php        IP + rDNS verification
│   │   ├── Buffer.php          batched writes
│   │   └── Repository.php      all SQL lives here, nowhere else
│   ├── Robots/
│   ├── Schema/
│   ├── LlmsTxt/
│   ├── Editor/
│   ├── Rest/
│   ├── Admin/
│   └── Support/                Str, Ip, Cron, Options
├── mu/
│   └── ai-parseable-drop-in.php
├── assets/                     built JS/CSS output
├── ui/                         JS/CSS source
├── languages/
└── tests/
```

`ai-parseable.php` contains no business logic. It declares the header, runs the guards, registers the autoloader, and calls `Plugin::boot()`. That file should be under 80 lines forever.

### Autoloading

PSR-4 under the namespace `AiParseAble\`. Generate a classmap at build time with `composer dump-autoload --classmap-authoritative --no-dev` and ship that — do not ship `vendor/` with runtime dependencies.

**If a runtime dependency ever becomes unavoidable, scope it.** Two plugins shipping different versions of the same unprefixed library is the classic fatal-error-on-someone-else's-site scenario. Use `php-scoper` in the build pipeline and prefix everything into `AiParseAble\Vendor\`.

---

## 4. Bootstrapping and hook discipline

`Plugin::boot()` registers modules. Each module implements:

```php
interface Module {
    public function register(): void;   // add_action / add_filter only
}
```

Rules that hold everywhere in this codebase:

- **No work in the constructor.** Constructors assign dependencies. Hooks are registered in `register()`. Actual work happens in callbacks.
- **No `new` inside business logic.** Dependencies are injected. This is what makes the logger testable without a live HTTP request.
- **No global functions, no `global $variable`.** The only global surface is the namespace.
- **Never hook to `init` what belongs on `admin_init`.** Front-end requests must not load a single admin class.
- **Never call `load_plugin_textdomain()` on `plugins_loaded`.** Since WP 6.7 that produces a `_load_textdomain_just_in_time` notice. Let WordPress handle it, or hook `init` at default priority.

### The conditional load map

On a front-end request, exactly one thing should be loaded: the collector. Nothing else.

```php
if ( is_admin() ) {
    $this->register_admin_modules();
} elseif ( wp_doing_ajax() || wp_doing_cron() ) {
    $this->register_background_modules();
} else {
    $this->collector->register();        // and nothing more
    $this->robots->register();           // robots_txt filter, negligible cost
    $this->schema->register();           // only if schema output is enabled
    $this->llms->register();             // rewrite rule only
}
```

---

## 5. The logger

This is the hardest component and the reason the plugin exists. Four problems must be solved, in this order.

### 5.1 Problem one: page caching bypasses PHP entirely

With WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, or a CDN in front, a cached response never executes PHP. The crawler visited; the plugin never ran. A dashboard that reports zero hits while the server log shows four hundred is worse than shipping nothing, because it produces confident wrong decisions.

**Solution: an mu-plugin drop-in that loads before `advanced-cache.php`.**

```
wp-content/mu-plugins/ai-parseable-drop-in.php
```

The drop-in is deliberately tiny — under 100 lines, zero dependencies, no WordPress functions beyond what exists at that point in the load. It matches the user-agent, and if it matches, appends a single line to a daily append-only file:

```
wp-content/uploads/ai-parseable/queue/2026-09-14.log
```

A newline-delimited record per hit: timestamp, UA hash, IP, method, request URI, status is filled later. Appending to a file with `LOCK_EX` costs far less than a database round trip and is safe under concurrency. A cron job ingests these files into the table in batches and deletes them.

**Installation of the drop-in:**

- Attempt on activation. If `wp-content/mu-plugins/` is not writable, do not fail — fall back to PHP-level logging and say so.
- Keep a version constant inside the drop-in and refresh it on plugin upgrade.
- Verify on every admin page load (cached in a transient for an hour) that the file still exists and matches the expected version. Hosts wipe `mu-plugins` during migrations.
- Remove it on uninstall.

**The dashboard must state which mode is active and what it misses.** Three states: full coverage with drop-in, PHP-level only with cached hits missing, and server-log ingestion. Honest coverage reporting is a feature, not an apology.

### 5.2 Problem two: user-agent strings are trivially forged

A meaningful share of traffic claiming to be `GPTBot` is a scraper wearing its name. Reporting unverified hits as real crawler interest is misinformation.

**Solution: forward-confirmed reverse DNS plus published IP ranges.**

1. Most providers publish their crawler IP ranges as JSON at a documented URL. Fetch each weekly via cron, validate the structure, store in an option, and fall back to the last good copy on failure. Never let a failed fetch break logging.
2. For providers that rely on reverse DNS instead (Google's pattern), do `gethostbyaddr()`, check the hostname suffix, then `gethostbyname()` the result and confirm it resolves back to the original IP. The forward confirmation step is what makes this non-spoofable; skipping it is the common mistake.
3. Cache each verification result keyed on `/24` (IPv4) or `/48` (IPv6) for 24 hours in the object cache. A DNS lookup on a page request is unacceptable — verification runs in the cron ingest, never inline.

Store a `verified` flag per row. Show verified and unverified counts separately in the UI, and default the charts to verified only.

### 5.3 Problem three: writing on every request is a performance liability

**Never `$wpdb->insert()` inside a page request.** Under a crawl burst that is a write per request on the same table, and the plugin becomes the reason the site falls over.

Write path, in order of preference:

1. **Drop-in active** → append to the daily file. Cost is one `file_put_contents` with `FILE_APPEND | LOCK_EX`, sub-millisecond.
2. **Drop-in unavailable** → buffer in memory, flush on `shutdown` after `fastcgi_finish_request()` where available so the user's response has already been sent.
3. **Ingest** → a cron job every five minutes reads the queue files, verifies IPs, aggregates, and does batched multi-row inserts of up to 500 rows per statement.

Guard the ingest with a lock so two overlapping cron runs cannot double-insert:

```php
if ( ! wp_cache_add( 'ai_parseable_ingest_lock', 1, 'ai-parseable', 300 ) ) {
    return; // another run holds the lock
}
```

**Do not rely on WP-Cron alone.** On low-traffic sites it may not fire for hours; on sites with `DISABLE_WP_CRON` it never fires. Detect WooCommerce's bundled Action Scheduler and use it when present; otherwise register WP-Cron events and surface a dashboard warning when the last ingest is over an hour old.

**Self-defence:** cap logging at a configurable ceiling per minute. If a single UA exceeds it, keep counting but stop storing per-request rows and record an aggregate instead. The plugin must never become an amplification vector for someone hammering the site.

### 5.4 Problem four: the table grows without bound

A mid-traffic store can see six figures of bot hits per month. Unmanaged, this table becomes the reason the host emails the site owner.

**Two-tier storage:**

- `..._hits` — raw rows, 30 days by default, configurable 7 / 30 / 90
- `..._daily` — one row per day per crawler per status bucket, kept forever

A nightly cron rolls raw rows older than the retention window into `_daily` and deletes them in chunks of 5,000 with a sleep between chunks. Never `DELETE` a hundred thousand rows in one statement on shared hosting.

Show the current table size in the dashboard. Owners who can see it stop worrying about it.

---

## 6. Data model

```php
$charset = $wpdb->get_charset_collate();

// {$wpdb->prefix}aiparseable_hits
"CREATE TABLE {$table} (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hit_at       DATETIME NOT NULL,
  bot_id       SMALLINT UNSIGNED NOT NULL,   -- FK into a PHP-side registry, not a table
  url_hash     BINARY(8) NOT NULL,            -- first 8 bytes of sha1(path)
  url          VARCHAR(512) NOT NULL,
  status       SMALLINT UNSIGNED NOT NULL,
  ip           VARBINARY(16) NOT NULL,        -- inet_pton, handles v4 and v6
  verified     TINYINT(1) NOT NULL DEFAULT 0,
  is_cached    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY bot_time (bot_id, hit_at),
  KEY time_status (hit_at, status),
  KEY url_bot (url_hash, bot_id)
) {$charset}"
```

Notes that matter:

- `VARBINARY(16)` with `inet_pton()`, not a `VARCHAR` IP string. Quarter the storage, and IPv6 works without special casing.
- `url_hash` exists because indexing a 512-char `VARCHAR` is wasteful. Group by the hash, display the URL.
- `bot_id` maps to a PHP registry array, not a lookup table. Crawler names change; a join does not earn its keep here.
- Composite index order follows the actual queries: every dashboard query filters by bot then by time range.

Run through `dbDelta()`, which is particular — two spaces after `PRIMARY KEY`, `KEY` not `INDEX`, lowercase types. Store a schema version in an option and run `Migrations::to( $version )` on `plugins_loaded` when it differs. **Never rely on the activation hook for upgrades** — it does not fire on an automatic background update.

---

## 7. Crawler access control

Writes rules through the `robots_txt` filter.

**The trap:** WordPress only serves a virtual robots.txt when no physical file exists at the web root. If one does, the filter is silently dead and the plugin appears broken.

Detect this on activation and on every settings save. When a physical file is found, show its contents in the UI, explain the situation, and offer to append the managed block to it with clear delimiters:

```
# BEGIN AI ParseAble
User-agent: PerplexityBot
Allow: /
# END AI ParseAble
```

Only ever rewrite between those markers. Never touch a line outside them. Same discipline as core's `.htaccess` handling, and for the same reason.

**Conflict detection.** Wordfence, All In One SEO, Rank Math and several security plugins also filter `robots_txt`. Run your filter at priority 20, then inspect the final output on the settings screen and warn if the rules that came out are not the rules that went in. Naming the conflicting plugin turns a support ticket into a self-service fix.

**The UI must separate training crawlers from answer crawlers** and must state the asymmetry plainly: blocking a training crawler does not reduce presence in AI answers; blocking an answer crawler removes you from them. The two groups are not the same decision, and every competing tool presents them as though they are.

**Be honest about the ceiling.** Cloudflare's AI-scraper toggle and similar CDN controls operate above WordPress. When the log shows failures from a crawler this plugin has allowed, say so and link to instructions rather than pretending the toggle fixed it.

---

## 8. Schema augmentation

**The rule: merge into the existing graph, never emit a second one.** Most sites already run Yoast, Rank Math, or WooCommerce's own structured data. A duplicate `Product` node is worse than a missing property.

Detect the active provider and hook its graph filter:

| Provider | Filter |
|---|---|
| Yoast SEO | `wpseo_schema_graph` |
| Rank Math | `rank_math/json_ld` |
| WooCommerce | `woocommerce_structured_data_product` |
| None detected | emit our own minimal graph on `wp_head` at priority 20 |

Only fill properties that are genuinely absent. Read values live at render time from the source of truth — `wc_get_product()` for price, currency and stock — so the markup can never drift from reality. Never cache a price into an option.

The settings screen shows a before/after diff of a real page from the site, not a generic example. Owners approve changes they can see.

---

## 9. llms.txt

Serve virtually through a rewrite rule and a `template_redirect` handler. **Do not write a physical file.** Many hosts have a non-writable root, and a physical file survives uninstall as litter.

```php
add_rewrite_rule( '^llms\.txt$', 'index.php?aiparseable_llms=1', 'top' );
```

Flush rewrite rules on activation and on settings save only. Never on `init` — that is a full option rewrite on every request.

**Do not generate boilerplate.** The summary field starts empty and the file is not served until the owner writes one. Roughly 40% of existing llms.txt files in the wild are plugin-generated stubs, and shipping another one makes the plugin part of the problem.

State the honest position in the UI: answer crawlers rarely request this file, Google has said it does not use it, coding agents do read it. Low weight, low cost, ship it anyway. A plugin that downplays one of its own features earns credibility for the others.

---

## 10. Editor checks

A block-editor sidebar panel, registered through `@wordpress/plugins`, running the same deterministic clarity checks the website uses: single h1, no skipped heading levels, opening paragraph states a fact, alt coverage, minimum length.

Checks run client-side against the block list. No REST round trip, no debounced save, no network call while typing.

Ship it as a separate small bundle loaded only on `enqueue_block_editor_assets`. It must not appear in the main admin bundle.

---

## 11. Site scan sync

Optional. An API key connects the site to the checker, pulls the latest score and findings, and exposes a rescan button.

**wp.org requires that the plugin works without it.** Every logger, robots, schema and llms.txt feature functions with no key entered. The external service is disclosed in `readme.txt` with what is sent, where, and the privacy policy URL — a scan request sends the site URL and nothing else.

Store the key in an option, never in a transient. Never log it. Redact it from any debug output.

---

## 12. Admin UI

### Rendering

A single React app mounted on one admin page, built with `@wordpress/scripts`. Use the `@wordpress/*` packages already in core — `apiFetch`, `i18n`, `element`, `components` — as externals so nothing is bundled twice. No jQuery. No chart library: the area chart, sparklines and bars are hand-written SVG, roughly 120 lines in total, and a charting dependency would outweigh the rest of the bundle.

Target: under 60KB gzipped for the main bundle.

### REST

Namespace `ai-parseable/v1`. Every route declares a `permission_callback` — never `__return_true`, which is the most common review rejection.

```php
register_rest_route( 'ai-parseable/v1', '/stats', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [ $this, 'stats' ],
    'permission_callback' => fn() => current_user_can( 'aiparseable_manage' ),
    'args'                => [
        'range' => [
            'type'              => 'string',
            'enum'              => [ '7d', '30d', '90d' ],
            'default'           => '30d',
            'sanitize_callback' => 'sanitize_key',
        ],
    ],
] );
```

Declare `args` with types and enums so WordPress validates before the callback runs. Toggle writes require a nonce, which `apiFetch` sends automatically via `wp.apiFetch.createNonceMiddleware`.

**Dashboard queries never touch the raw hits table for ranges over 7 days.** They read `_daily`. A 90-day chart must not scan a million rows.

### Capability

Register a custom capability `aiparseable_manage`, granted to Administrator on activation. Do not gate on `manage_options` — agencies need to delegate this to an editor without handing over the whole site.

### Admin notices

At most one, dismissible, stored per user. No upgrade nags on unrelated screens. No review prompts before 30 days of use. This is the difference between a plugin people keep and one they delete.

---

## 13. Security and privacy

- Every query through `$wpdb->prepare()`. For `IN` clauses, build the placeholder string from the count — never interpolate, not even integers you believe you control.
- Escape at output, always: `esc_html`, `esc_attr`, `esc_url`, `wp_json_encode`. Never trust that a value was sanitised on the way in.
- Sanitise on input with the narrowest function that fits. `sanitize_key` for identifiers, not `sanitize_text_field`.
- Nonces on every state-changing request, capability checks on every handler. Both, not either.
- **IP storage is personal data under GDPR.** Provide a setting to store a truncated IP (`/24`, `/48`) or a salted hash instead of the full address, and document the default in `readme.txt`. Register export and erase handlers via `wp_privacy_personal_data_exporters` even though crawler IPs are not user data — it demonstrates the plugin was built by someone who has read the requirements.
- Drop-in log files live in `uploads/ai-parseable/` with an `index.php`, a `.htaccess` deny rule, and randomised filenames. Verify on activation that the directory is not web-readable and warn if it is.
- No phone-home without explicit opt-in. No bundled analytics SDK.

---

## 14. Multisite

Per-site settings and per-site tables. Network admins get a network-wide overview page; they do not get a shared configuration, because two sites in a network rarely want the same crawler rules.

Handle `wp_initialize_site` to install tables on new sites, and `wp_uninitialize_site` to drop them. Loop with `switch_to_blog()` on network activation and always `restore_current_blog()` in a `finally`.

The drop-in is network-wide by nature — write it once, and have it resolve the current site from `$_SERVER['HTTP_HOST']`.

---

## 15. Performance budget

Measured, not asserted. Add to the dashboard a self-reported figure from the plugin's own timing.

| Path | Budget |
|---|---|
| Front-end request, non-bot | under 0.4ms, and zero queries |
| Front-end request, matched bot | under 2ms, and zero queries |
| Cron ingest, 5,000 rows | under 4s |
| Dashboard first paint | under 800ms |

The non-bot path is the one that matters, because it is 99% of traffic. A UA that matches no signature must exit after a single string comparison against a precompiled pattern. Do not loop thirty `stripos()` calls per request — build one regex at install time and cache the compiled pattern in an option.

**Zero queries on the front end is a hard requirement, not a target.** Settings load from a single autoloaded option. If it ever takes a query, the design is wrong.

---

## 16. Internationalisation

Text domain `ai-parseable`, matching the slug exactly — wp.org's language pack system requires it. Every user-facing string wrapped, with translator comments on anything containing a placeholder:

```php
/* translators: %s: name of the AI crawler, e.g. GPTBot */
sprintf( __( '%s has not visited in 14 days.', 'ai-parseable' ), $bot );
```

JavaScript strings through `@wordpress/i18n` with `wp_set_script_translations()`. Never concatenate translated fragments — word order differs by language.

---

## 17. Testing

| Layer | Tool | What it covers |
|---|---|---|
| Unit | PHPUnit, no WP bootstrap | UA matching, IP parsing, rollup arithmetic, robots block rewriting |
| Integration | `wp-env` + WP test suite | Activation, migrations, REST permissions, drop-in install |
| Static | PHPStan level 6 with `szepeviktor/phpstan-wordpress` | |
| Standards | PHPCS, `WordPress-Extra` + `WordPress-Docs` | Escaping and sanitisation violations |
| E2E | Playwright against `wp-env` | Dashboard loads, toggle persists, chart renders |

Fixtures that must exist as test cases: a spoofed `GPTBot` from an unlisted IP, a physical robots.txt blocking the filter, Rank Math active, WooCommerce active, an mu-plugins directory that is not writable, and a site with `DISABLE_WP_CRON` set. Those six scenarios are where real installs break.

CI matrix: PHP 7.4 / 8.1 / 8.3 / 8.4 against WP 6.4 / latest / trunk. Trunk is allowed to fail without blocking the build, but it must be visible — that is the early warning for the next core release.

---

## 18. Build and release

`.distignore` excludes `tests/`, `ui/`, `node_modules/`, `composer.json`, `phpunit.xml`, `.github/`, and every dotfile. The shipped zip contains only runtime code and built assets.

Build script, in order: `composer install --no-dev`, `composer dump-autoload --classmap-authoritative`, `npm ci`, `npm run build`, `wp dist-archive`. Version number lives in exactly one place — the plugin header — and a script propagates it to `readme.txt` and the constant. Two versions drifting apart is how a release ships with the wrong upgrade routine.

Tag-triggered SVN deploy via the `10up/action-wordpress-plugin-deploy` action. Never commit to SVN by hand.

Every release has an upgrade routine entry in `Migrations`, even if it is a no-op, so the version ladder is unbroken.

---

## 19. wp.org review checklist

The items that actually get plugins rejected:

- [ ] All external service calls disclosed in `readme.txt` with what is sent and a privacy policy link
- [ ] Plugin fully functional without the external service
- [ ] No `permission_callback => '__return_true'` anywhere
- [ ] Every input sanitised, every output escaped, every query prepared
- [ ] Text domain matches the slug
- [ ] No obfuscated or minified PHP
- [ ] No calling home on activation
- [ ] No admin notices outside the plugin's own screens
- [ ] No modifying files outside the plugin directory without user consent — the drop-in install is explicit and reversible
- [ ] GPLv2-or-later, with every bundled asset's licence compatible
- [ ] `uninstall.php` honours a "keep my data" setting and defaults to keeping

---

## 20. Free and paid split

Free, in the wp.org repo: 7 days of crawler history, crawler access control, llms.txt, schema gap filling, editor checks.

Paid: unlimited history, per-URL failure breakdown, email alerts when an answer crawler goes quiet or error rates spike, WooCommerce offer injection, scan sync.

History is the correct thing to gate, because it is the recurring value. A one-time score is not worth a subscription; a log that gets more useful the longer it runs is.

Licensing through Freemius or EDD Software Licensing. **Do not write your own.** Whichever is chosen, the free build in the repo must contain no licensing code paths at all — keep the premium layer in a separate add-on plugin rather than shipping dead code to the repo.

---

## 21. Build order

**Phase 1 — the collector, standalone.** UA registry, precompiled matcher, drop-in, queue files, ingest cron, table, rollups. No UI. Verify with a script that hits the site with forged and real crawler agents and confirms the rows land. This phase is most of the difficulty and all of the moat.

**Phase 2 — verification.** IP range fetching, forward-confirmed rDNS, caching, the `verified` flag. Test against a spoofed agent from an unlisted IP.

**Phase 3 — dashboard.** REST endpoints reading `_daily`, React app, chart, bot table, failing-URL panel, coverage banner.

**Phase 4 — robots control.** Filter, physical-file detection, marker block, conflict warnings.

**Phase 5 — schema and llms.txt.** Provider detection, graph merging, diff preview, virtual llms.txt.

**Phase 6 — hardening.** Editor panel, multisite, i18n, privacy settings, performance measurement, the full test matrix, release pipeline.

---

## 22. Definition of done

- Non-bot front-end requests add zero database queries and under 0.4ms
- The drop-in survives a plugin upgrade and self-heals when a host wipes `mu-plugins`
- A spoofed crawler from an unlisted IP is recorded as unverified and excluded from the default charts
- A physical robots.txt is detected and handled without the user discovering it themselves
- Schema augmentation on a Rank Math site produces exactly one `Product` node with a price in it
- The 90-day dashboard loads in under 800ms on a table with a million rows
- Uninstall leaves no tables, options, drop-in, cron events, or upload directories when "delete my data" is on, and leaves all of them untouched when it is off
- PHPCS clean against `WordPress-Extra`, PHPStan level 6 clean
- Every string translatable, every placeholder carrying a translator comment
