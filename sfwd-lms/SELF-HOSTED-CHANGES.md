# MMA build of LearnDash 5.2.2 (23 October 2026)

This build is stock LearnDash 5.2.2 with the May 2026 changes (originally made on 5.0.5, labelled 5.0.6) carried forward. It was made to pick up upstream security fixes, including CVE-2026-12843 (unauthorised course enrolment, fixed in 5.1.6.1).

**Carried forward:**
- Change 3, telemetry disabled (`sfwd_lms.php`).
- Change 4, TrustedLogin hooks removed (`src/Core/Modules/Support/Provider.php`).
- Change 5, multi-provider AI: the five new `src/Core/Services/` files plus the seven modified AI files. None of those seven changed between 5.0.5.1 and 5.2.2, so the May versions were used as is.
- Autoloader: `setClassMapAuthoritative(false)` in `vendor/composer/autoload_real.php`, so the new AI classes load.
- Changes 1 and 2, the licence bypass: `learndash_validate_hub_license()` and `learndash_is_license_hub_valid()` in `includes/ld-license.php` return true, and `Status_Checker::get_status()` returns active. Applied by hand, because 5.2.2 rewrote these functions (new `$recheck` parameter, Harbor licensing).

**New in this build:**
- **Updates blocked, two ways.** (a) `learndash_updates_enabled()` in `includes/ld-license.php` returns false, which stops LearnDash's own updater. (b) The plugin header `Update URI` is changed from `learndash` to `learndash-mma-build`, so WordPress never matches this plugin to LearnDash's update feed or to WordPress.org. The licence bypass alone would have done the opposite: it makes the updater think the licence is valid, which switches update offers on.
- `includes/settings/settings-sections/class-ld-settings-section-telemetry.php`: added a guard so the Telemetry settings panel shows "Telemetry is disabled" instead of throwing a fatal error. Telemetry is never initialised, so the panel would otherwise crash.

**Added 23 October 2026 (code review follow-up):**
- **Harbor disabled.** 5.1.0 introduced StellarWP's Harbor library (`vendor-prefixed/stellarwp/harbor/`), a third licensing system alongside the two already bypassed. It schedules a twice-daily cron (`lw_harbor_data_refresh`) that fetches a catalog from `software.liquidweb.com`, and it has its own updater that hooks `pre_set_site_transient_update_plugins` and matches plugins by **file path** (`sfwd-lms/sfwd_lms.php`), so neither the `Update URI` header nor `learndash_updates_enabled()` stops it. Its gate is satisfied whenever the old `nss_plugin_license_sfwd_lms` option is non-empty (LearnDash registers that key with `use_for_updates => true`, and the Status_Checker bypass marks it active). Fix: `src/Core/Libraries/Harbor/Provider.php::configure()` now clears that cron and returns before `LiquidWebHarbor::init()`. The `lw_harbor_*` global functions are registry-based and return false/null/no-op when Harbor is not initialised, so `ld-license.php` still works. The Harbor "Licensing" submenu no longer appears.
- `Status_Checker::get_legacy_license_keys()` returns `[]`. It still called `checkout.learndash.com/wp-json/learndash/v2/subscriptions/keys?license_key=...` with the stored key (invoked via the `lw-harbor/legacy_licenses` filter).
- **Recommended on the live site:** `wp option delete nss_plugin_license_sfwd_lms nss_plugin_license_email_sfwd_lms`. Nothing in this build needs the old key, and removing it closes the update path independently of the code change.
- AI providers (`Anthropic.php`, `Gemini.php`, `OpenAI_Compatible.php`): upstream API error text is now written to the PHP error log instead of being shown to the student, who sees a generic message. An unexpected response shape (for example a Gemini candidate blocked for safety, which has no `parts`) now throws instead of silently returning an empty reply.
- `Gemini.php`: API key is sent in the `x-goog-api-key` header instead of the `?key=` query string, so it no longer lands in server, proxy or CDN logs.
- `Status_Checker.php`: fixed the indentation of the `get_status()` closing brace.
- `CLAUDE.md` / `AGENTS.md`: added an MMA notice so coding agents don't "repair" the bypass. These two files are upstream dev files that LearnDash ships in the release zip; their Harbor rules do not apply to this build.
- Not changed, checked and found inert: `includes/class-ld-addons-updater.php` also hooks the update transients, but its repository data comes from the Hub fetch which `learndash_updates_enabled()` already gates.

**Added 23 October 2026 (second review, Gemini; full write-up in `GEMINI-REVIEW.md`):**
- `Harbor/Provider.php`: the `lw-harbor/legacy_licenses` and `lw_harbor/premium_plugin_exists` filters are no longer registered, and the cron cleanup runs on `admin_init` only when a leftover event exists. This matters beyond tidiness: The Events Calendar bundles its own copy of Harbor (`the-events-calendar/common/vendor/vendor-prefixed/stellarwp/harbor`), and Harbor's hooks are shared between plugins, so the old filters still handed LearnDash's licence to that copy with `use_for_updates => true`.
- `Anthropic.php` / `Gemini.php`: a leading assistant message (the Virtual Instructor greeting) is dropped and consecutive same-role messages are merged. The greeting was sent as the first message, which Anthropic and Gemini reject because the first turn must come from the user.
- `ChatGPT.php`: `gpt-5*` and `o1`/`o3`/`o4` models get `max_completion_tokens` and no `temperature`/`top_p` (the default model `gpt-5-mini` rejects those). Upstream errors are logged; admins in wp-admin still see the detail, students see a generic message. An unexpected response shape now throws. Duplicate system messages are filtered in `set_messages()`.
- `OpenAI_Compatible.php`: refuses to send when the base URL is empty or malformed. Gemini's version used `wp_http_validate_url()`, which rejects localhost, private addresses and ports other than 80/443/8080 (so Ollama, LM Studio and vLLM would always fail). Replaced with a plain http/https scheme-and-host check.
- `class-ld-settings-section-ai-integrations.php`: short API keys (10 characters or fewer) are fully masked and no longer have their last 4 characters shown twice. The provider dropdown says it applies to Virtual Instructors only, since Course Outline and Quiz Creation always use OpenAI.

**Added 23 October 2026 (v5): Course Outline and Quiz Creation use the selected provider.**
- `AI_Provider` interface: new `is_configured()` (API key set, or for `OpenAI_Compatible`, base URL set), implemented in all four providers.
- `Course_Outline.php`, `Quiz_Creation.php`, `Quiz_Creation/View.php`: constructors take `AI_Provider` instead of `ChatGPT`; the "key missing" check uses `is_configured()`.
- `Modules/AI/Provider.php`: binds `AI_Provider` in the DI container to `AI_Provider_Factory::make()`, so these tools use whatever is chosen under AI Integrations (Anthropic, Gemini, OpenAI or self-hosted). The ChatGPT key wiring is kept.
- `includes/views/AI/course-outline.php` and `quiz-creation.php`: the missing-key notice no longer says "OpenAI".
- AI Integrations settings: the provider dropdown again says it covers Virtual Instructors, Course Outline and Quiz Creation.
- Virtual Instructor setup wizard (`Virtual_Instructor/Admin.php`, `AJAX/Process_Setup_Wizard.php`): it skipped its "enter OpenAI key" step only when an OpenAI key was saved. It now also skips it when the selected provider is set up, by passing the placeholder `mma-provider-configured`, which the save handler refuses to store.
- This supersedes the earlier note that Course Outline and Quiz Creation are OpenAI-only.

**Added 23 October 2026 (v6): output clean-up for non-OpenAI models, found by testing with Claude on staging.**
- `Quiz_Creation/Parser.php` (`parse_json_response()`): strips a ```json code fence and any text around the JSON object before decoding. Claude returned fenced JSON, which parsed as 0 questions.
- `Course_Outline.php` (`parse()`): strips `**`/`__` emphasis and wrapping quotes from lesson titles.
- `Course_Outline.php` and `Quiz_Creation.php`: `set_messages( [] )` before each `send_command()`, so every outline request and every quiz question type starts a fresh conversation. Upstream reused one conversation, and earlier answers leaked into later question types.

**Added 23 October 2026 (v7): remaining phone-home paths in the built-in LearnDash Hub and admin banners.**
- `includes/licensing/src/traits/license.php`: `is_signed_on()` returns false. It called `API::verify_license()`, which POSTs the site URL, licence key, email and site stats (WordPress version, multisite) to LearnDash's `/site/auth` about once a day, from every request type including the front end. The Hub now loads only its sign-in controller, which does nothing unless someone submits its form.
- `includes/licensing/src/boot.php`: `RemoteBanners` is no longer registered (it fetched `remote.learndash.com/wp-json/learndash/v2/banners` from admin pages).
- `src/Core/Modules/Admin/Banner/Provider.php`: `hooks()` returns early, so the Black Friday banner, the v5.0 update banner (which called the Hub API's `get_projects()`) and the Nexcess "software packages" ad never load.
- Left in place because they only run when an admin starts them: the translations downloader (LearnDash Settings > Translations), the Course Wizard playlist parser, and the LearnDash Stripe/PayPal connect clients (unused; MMA takes payments through PMPro).

**Version:** reported as 5.2.2 (plugin header and `LEARNDASH_VERSION`), with an "MMA build" note in the plugin description. The dashboard will no longer offer LearnDash updates, so security fixes have to be watched for manually (for example on WPScan or Wordfence for "LearnDash LMS"). To move to a newer release, download it from your LearnDash account and re-apply every change in this file: the 13 locations listed at the end, plus the telemetry settings guard, `learndash_updates_enabled()`, the `Update URI` header, the Harbor bypass, the AI-provider hardening and the v5 provider routing the v6 output clean-up and the v7 Hub/banner changes above.

---

# LearnDash LMS — Self-Hosted Modifications

This document records all changes made to the LearnDash LMS plugin (v5.0.5) to make it fully self-contained and independently maintainable.

**Date of modifications:** May 2026  
**Original plugin version:** 5.0.5  
**Modified plugin version:** 5.0.6  
**Modified by:** Charlie Knoles (with Claude)

---

## Background

Following the acquisition of LearnDash by Liquid Web / StellarWP, the plugin's pricing and licensing terms changed significantly. These modifications allow the plugin to be self-maintained without requiring an active paid subscription to LearnDash's licensing servers.

The changes are minimal and surgical — they touch only the outbound communication and licensing enforcement code. No course functionality, student-facing features, payment processing logic, or WordPress integration code has been altered.

---

## Changes Made

### 1. License validation bypass

**File:** `includes/ld-license.php`

**Functions modified:**
- `learndash_validate_hub_license()` — Originally made HTTP calls to the LearnDash Hub API (`LearnDash\Hub\Component\API->verify_license()`) to remotely validate the license key and email against LearnDash's servers. Now returns `true` unconditionally without making any network request.
- `learndash_is_license_hub_valid()` — Originally read a 6-hour cached license result from the WordPress database, and triggered a fresh remote validation call when the cache expired. Now returns `true` unconditionally, bypassing both the cache read and any remote revalidation.

**Why:** These two functions are the root of the license enforcement system. Every admin-facing license check in the plugin ultimately calls one of these. They controlled: license expiry notices in wp-admin, access to the add-ons marketplace page, and the "completed" state of the setup wizard. No frontend or course-serving functionality was gated behind them — the impact is entirely in the admin UI.

---

### 2. Status Checker bypass (newer licensing system)

**File:** `src/Core/Licensing/Status_Checker.php`

**Method modified:** `get_status()`

Originally this method fetched license data from a WordPress transient cache (12-hour timeout), and on cache miss made an HTTP GET request to `https://checkout.learndash.com/wp-json/learndash/v2/subscriptions/status?license_key=...` to retrieve the subscription status for a given plugin slug (LearnDash core, ProPanel, or Reports). The response determined whether the subscription was `active`, `pending-cancel`, `on-hold`, `cancelled`, or `expired`.

Now returns a hardcoded response of `['type' => 'current', 'status' => 'active']` for any plugin slug, with no network call and no transient lookup.

**Why:** This is a parallel, newer licensing system introduced in v4.17.0 that runs alongside the Hub-based system above. It was used in one active location (the ProPanel migration notice check) and would have continued phoning home to `checkout.learndash.com` independently of the Hub API changes. Both systems needed to be addressed for complete silence.

---

### 3. Telemetry disabled

**File:** `sfwd_lms.php` (main plugin bootstrap file)

**What was removed:** The initialisation block for the `stellarwp/telemetry` package, which ran on the `plugins_loaded` hook at priority 0. This block configured and started `StellarWP\Telemetry\Core`, pointing it at `https://telemetry.stellarwp.com/api/v1` (or a custom URL if the `STELLARWP_TELEMETRY_SERVER` constant was defined). The two `use` imports for `TelemetryConfig` and `Telemetry` were also removed.

**Why:** The StellarWP Telemetry package collects usage data about the plugin installation and reports it to StellarWP's servers. On a self-hosted installation there is no reason to send this data to a third party, and doing so raises an unnecessary privacy concern for site administrators and students. The rest of the `plugins_loaded` initialisation (DB, Validation, AdminNotices) is unaffected.

---

### 4. TrustedLogin remote support access removed

**File:** `src/Core/Modules/Support/Provider.php`

**What was removed:** Three `add_action` calls that wired up the `TrustedLogin` class to WordPress hooks:
- `learndash_init` → `TrustedLogin::register()` — instantiated the TrustedLogin `Client` with LearnDash's vendor API key (`7e1b0fbfa9aad4fa`) and connected it to their support system, creating a hidden admin page (`grant-learndash-access`) through which LearnDash support staff could request temporary administrator access to any site running the plugin.
- `admin_head` → `TrustedLogin::remove_submenu_item()` — hid the grant-access page from the admin menu while keeping it accessible by direct URL.
- `admin_enqueue_scripts` → `TrustedLogin::add_scripts()` — enqueued JS on the TrustedLogin page.

The unused `use` import for the `TrustedLogin` class was also removed. The class file itself (`src/Core/Modules/Support/TrustedLogin/TrustedLogin.php`) is left in place to avoid autoloader errors.

**Why:** TrustedLogin is a legitimate vendor support tool — it is opt-in and requires a site admin to actively click "Grant Access" before any third party can log in. However, on a self-maintained installation there is no LearnDash support relationship to facilitate, and the mechanism is unnecessary. Removing the hooks means the TrustedLogin client is never instantiated, the grant-access page is never registered, and no connection is ever made to TrustedLogin's service.

---

---

### 5. Multi-provider AI support

**New files:**
- `src/Core/Services/AI_Provider.php` — Interface defining the contract for all AI backends
- `src/Core/Services/AI_Provider_Factory.php` — Static factory that instantiates the configured provider
- `src/Core/Services/Anthropic.php` — Anthropic (Claude) provider
- `src/Core/Services/Gemini.php` — Google Gemini provider
- `src/Core/Services/OpenAI_Compatible.php` — Generic OpenAI-compatible provider

**Modified files:**
- `src/Core/Services/ChatGPT.php`
- `src/Core/Modules/AI/Provider.php`
- `src/Core/Modules/AI/Virtual_Instructor/Chat_Session.php`
- `src/Core/Modules/AI/ChatGPT_Summarizer.php`
- `src/Core/Modules/AI/Virtual_Instructor/AJAX/Chat_Send.php`
- `src/Core/Modules/AI/Virtual_Instructor/AJAX/Chat_Input.php`
- `includes/settings/settings-sections/class-ld-settings-section-ai-integrations.php`

**What was added:** The original Virtual Instructor feature was hard-wired to OpenAI (ChatGPT) as the sole AI backend. The chat session, conversation summarisation, and role constants all lived in the single `ChatGPT` class. This change decouples the Virtual Instructor runtime from any specific provider.

**Architecture — two tiers of AI usage:**

The plugin has two distinct categories of AI-using code that require different integration approaches, due to how the `lucatume/di52` dependency injection container works.

*Admin-side generation tools* (`Course_Outline`, `Quiz_Creation`, `Quiz_Creation\View`) are registered as DI singletons and have their dependencies auto-wired by the container. These classes continue to use `ChatGPT` directly — the container resolves it via the existing `when(ChatGPT::class)->needs('$api_key')->give(...)` binding in `AI/Provider.php`. This means the Course Outline and Quiz Creation admin tools only work with OpenAI.

*Frontend runtime classes* (`Chat_Session`, `ChatGPT_Summarizer`) are not DI singletons — they are instantiated at request time with varying constructor arguments. These classes call `AI_Provider_Factory::make()` instead of relying on the container, receiving a fresh instance of whichever provider is configured in the admin settings.

**`AI_Provider` interface** (`src/Core/Services/AI_Provider.php`) defines the contract all backends must satisfy: `send_command()`, `set_instruction()`, `get_instruction()`, `set_messages()`, `get_messages()`, `add_message()`, `get_max_context_window_tokens()`, and the three role constants (`ROLE_SYSTEM`, `ROLE_USER`, `ROLE_ASSISTANT`) that were previously static properties on `ChatGPT`.

**`AI_Provider_Factory`** (`src/Core/Services/AI_Provider_Factory.php`) reads the `learndash_ai_integrations` option from the database and returns the appropriate provider instance. It is the single place where provider selection logic lives.

**`Anthropic`** calls `https://api.anthropic.com/v1/messages`. The Claude API differs from OpenAI in two key ways: the system prompt is a top-level request parameter rather than a message with `role: system`, and the API key is passed in an `x-api-key` header rather than `Authorization: Bearer`. Default model is `claude-3-5-haiku-20241022`; configurable via the settings page.

**`Gemini`** calls Google's `generativelanguage.googleapis.com` endpoint. The Gemini format differs significantly: the API key is passed as a URL query parameter, messages are wrapped in a `contents` array with nested `parts` objects, the system prompt goes in a `systemInstruction` object, and the AI role is `model` rather than `assistant` (the provider handles this mapping internally). Default model is `gemini-1.5-flash`.

**`OpenAI_Compatible`** uses the same request format as OpenAI but with a configurable base URL and model name. This allows any server implementing the OpenAI chat completions API to be used — including Ollama, LM Studio, vLLM, LocalAI, and similar tools running on a VPS or local machine. The API key field is optional and is omitted from the request headers if left empty, supporting unauthenticated local servers.

The existing `ChatGPT` class was updated to implement `AI_Provider` and had a non-static `get_max_context_window_tokens()` method added (delegating to the existing static method) to satisfy the interface contract. All existing behaviour is unchanged.

The AI Integrations settings section (`includes/settings/settings-sections/class-ld-settings-section-ai-integrations.php`) was expanded to include a provider dropdown and credential fields for all four providers. Fields irrelevant to the selected provider are saved but unused. The settings are stored in the existing `learndash_ai_integrations` option in `wp_options`.

**Where to configure:** WordPress admin → LearnDash → Advanced Settings → AI Integrations. The provider dropdown defaults to OpenAI, so existing setups are unaffected until it is changed. Only the Virtual Instructor chat feature uses the selected provider; the Course Outline and Quiz Creation tools continue to use OpenAI only.

**Why:** The original hard-coded OpenAI dependency created both a cost and a vendor lock-in concern for the Veda Center's Virtual Instructors. This change makes it possible to run those instructors on Claude, Gemini, or a self-hosted model on a VPS — without modifying the plugin code again.

---

## What Was NOT Changed

- All course, lesson, quiz, and assignment functionality
- Student-facing templates and themes
- Payment gateway integrations (Stripe, PayPal, Razorpay)
- REST API endpoints
- WordPress user role and capability management
- All add-on compatibility code
- The LearnDash Hub plugin loader (if the Hub plugin is installed separately, it operates independently)
- WordPress automatic update checks for this plugin (these are already inert without a valid license key stored in the database, and are controlled separately by `learndash_updates_enabled()`)

---

## Maintenance Notes

Because this plugin is now self-maintained, the following should be monitored manually:

- **WordPress compatibility** — Test after each major WordPress release (typically April and November). Minor releases rarely cause issues.
- **PHP compatibility** — Plugin requires PHP 7.4 minimum. Test if upgrading the server PHP version.
- **Security disclosures** — Monitor the WPScan vulnerability database or similar for any disclosed vulnerabilities in LearnDash. Without an active subscription, automatic security updates will not be delivered.
- **Payment gateway SDKs** — Stripe and PayPal periodically deprecate older API versions. If using payment features, check gateway compatibility annually.
- **AI provider API changes** — Anthropic, Google, and OpenAI occasionally update their API formats or deprecate model versions. If an AI feature stops working, check the relevant provider's changelog and update the endpoint URL or request format in the corresponding service class.

---

## Re-applying These Changes After a Plugin Update

If a newer version of LearnDash is ever obtained and applied, the following locations will need to be re-patched:

**Licensing & telemetry (5 locations):**
1. `includes/ld-license.php` — `learndash_validate_hub_license()`, `learndash_is_license_hub_valid()` and `learndash_updates_enabled()`
2. `src/Core/Licensing/Status_Checker.php` — `get_status()` and `get_legacy_license_keys()`
2a. `src/Core/Libraries/Harbor/Provider.php` — `configure()` returns before `LiquidWebHarbor::init()`
3. `sfwd_lms.php` — Telemetry initialisation block
4. `src/Core/Modules/Support/Provider.php` — TrustedLogin hook registrations

**Multi-provider AI (8 locations):**
5. `src/Core/Services/ChatGPT.php` — `implements AI_Provider` declaration and `get_max_context_window_tokens()` instance method
6. `src/Core/Modules/AI/Provider.php` — Simplified to only wire `ChatGPT`'s API key; no interface binding
7. `src/Core/Modules/AI/Virtual_Instructor/Chat_Session.php` — `AI_Provider_Factory::make()` call and `AI_Provider::ROLE_ASSISTANT` constant
8. `src/Core/Modules/AI/ChatGPT_Summarizer.php` — `AI_Provider_Factory::make()` calls replacing `App::make()`
9. `src/Core/Modules/AI/Virtual_Instructor/AJAX/Chat_Send.php` — `AI_Provider::ROLE_ASSISTANT` constant
10. `src/Core/Modules/AI/Virtual_Instructor/AJAX/Chat_Input.php` — `AI_Provider::ROLE_USER` constant
11. `includes/settings/settings-sections/class-ld-settings-section-ai-integrations.php` — Provider dropdown and credential fields

The five new files (`AI_Provider.php`, `AI_Provider_Factory.php`, `Anthropic.php`, `Gemini.php`, `OpenAI_Compatible.php`) are entirely custom additions with no counterpart in the original plugin, so they will not be overwritten by an update and only need to be carried forward.
