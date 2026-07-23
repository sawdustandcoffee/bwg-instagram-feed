# Implementation Spec — Migrate to "Instagram API with Instagram Login"

> **For the coding agent:** This is a ready-to-apply implementation spec. Read it fully, then implement.
> Line numbers are approximate — confirm by reading each file before editing.
> Backlog features **59–63** (category "Instagram API Migration") in `features.db` track this work.

---

## 1. Why

The plugin's Instagram connection was built on the **Instagram Basic Display API**, which Meta
permanently shut down on **2024-12-04**. Current symptom when connecting: **"Invalid Request:
Request parameters are invalid: Invalid platform app"** followed (once that clears) by an invalid-scope
error. The fix is to finish moving the OAuth flow to **Instagram API with Instagram Login** (the
Instagram Graph business-login flow).

**Good news / scope:** most of this was already done in recent commits. The token exchange and the
media/refresh endpoints are **already the correct Instagram Login shapes**. The only true code bug is
the **authorization URL + scopes** in the credentials class. The rest is UI copy + verification.

---

## 2. Distribution model & hard constraints (read before editing)

**Model "A1":** Boston Web Group (BWG) owns **one** Meta app, shared across their own client sites.

- **Credentials come from `wp-config.php` constants** the plugin already reads:
  `BWG_IGF_INSTAGRAM_APP_ID` and `BWG_IGF_INSTAGRAM_APP_SECRET`
  (`includes/class-bwg-igf-instagram-credentials.php` → `get_app_id()` / `get_app_secret()` check the
  constants first, placeholders as fallback).
- ❌ **Do NOT hardcode any real App ID or secret.** Keep the placeholder fallbacks. The secret must
  **never** be committed to git (this repo is on GitHub).
- `client_id` in the OAuth flow **is the Instagram App ID**.
- The connected Instagram account must be a **Business or Creator** account (personal is no longer supported).

**Git / release safety (critical — the plugin auto-updates all client sites):**
- Work on a branch, e.g. `feature/instagram-login-migration`. **Do NOT commit to `main`.**
- **Do NOT bump the version** (`bwg-instagram-feed.php`, `readme.txt`) and **do NOT create a
  release/tag** yet. The plugin-update-checker watches releases and would deploy to every client site.
  Hold the version bump + release until **after** a live Connect test passes on a real site.
- Pushing the *branch* (no tag, no release) is fine.

**Verification available here:** live Connect testing needs BWG's real app + credentials, which are not
in this environment. Do **static** verification: `php -l` on every touched file + a careful self-review
against the live Meta docs
(<https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login>).

---

## 3. File-by-file changes

### #59 — `includes/class-bwg-igf-instagram-credentials.php`  ← the actual bug

- **`get_oauth_url()` (~line 88-99):**
  - **~line 91:** `$scope = 'user_profile,user_media';` → derive from the single source of truth:
    `$scope = implode( ',', self::get_scopes() );`
  - **~line 94:** change the authorize host from
    `https://api.instagram.com/oauth/authorize` → **`https://www.instagram.com/oauth/authorize`**.
    (`www.instagram.com/oauth/authorize` is the current Instagram-Login authorization host;
    `api.instagram.com/oauth/authorize` was Basic Display. Meta's reference page is inconsistent here —
    **confirm against the live business-login doc and the app's behavior**; this is live-test item #1.)
  - Keep params otherwise: `client_id`, `redirect_uri`, `response_type=code`, `scope`.
- **`get_scopes()` (~line 106-111):** replace the two-element array with:
  ```php
  return array( 'instagram_business_basic' );
  ```
  Add `instagram_business_content_publish` / `instagram_business_manage_comments` **only if** a feature
  needs them. For read-only feed display, `instagram_business_basic` alone is correct.

### #60 — token exchange in `templates/admin/accounts.php` (~lines 28-224)  ← verify, likely no change

The 3-step flow is **already correct for Instagram Login**. Confirm, don't rewrite:
- **~line 38 short-lived:** `POST https://api.instagram.com/oauth/access_token` with
  `client_id, client_secret, grant_type=authorization_code, redirect_uri, code` → returns
  `access_token, user_id, permissions`. Code already reads `$data['user_id']`. ✅
- **~line 71 long-lived:** `GET https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret=…&access_token=…`
  → `{access_token, expires_in ≈ 5184000}`. Encryption via `BWG_IGF_Encryption::encrypt` and the ~60-day
  `expires_at` already in place (~lines 103-104). ✅
- **~line 85 user info:** `GET https://graph.instagram.com/me?fields=id,username,account_type&access_token=…`.
  **Verify against live docs** — under Instagram Login the `/me` fields differ from Basic Display;
  `account_type` may return `BUSINESS`/`MEDIA_CREATOR`, and it may be `user_id` rather than `id`. Existing
  normalization (~lines 95-100) fails safe. Consider changing the default fallback from `'basic'` to
  `'business'` since personal/basic accounts are unsupported. (Live-test item #3.)

### #61 — `includes/class-bwg-igf-instagram-api.php`  ← verify + optional version pin

- **Media fetch (~line 1044):**
  `https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count&access_token=…&limit=…`
  — **already the correct Instagram Login endpoint + field set.** `like_count`/`comments_count` are valid
  under Instagram Login (they weren't under Basic Display), so **keep them**. *Optional hardening:* pin an
  API version, e.g. `https://graph.instagram.com/v21.0/me/media`.
- **Refresh (~line 1268):**
  `https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=…`
  — **unchanged** for Instagram Login. No edit needed.
- Reuse the existing native-cURL helpers, `BWG_IGF_Encryption`, and `BWG_IGF_Logger`.

### #62 — UI copy in `templates/admin/accounts.php` (~lines 254-299)

- **~lines 266-279 (Step 1):** rewrite from "add the Instagram Basic Display product" to the current flow:
  1. Create/select a **Business-type** Meta app.
  2. Add the **Instagram** product → **"API setup with Instagram login"** (use case: *"Manage messaging and
     content on Instagram"*).
  3. Under **Business login settings**, add the redirect URI (keep the existing `<code>` display + Copy button,
     ~lines 271-277 — **leave those intact**).
  4. Copy the **Instagram App ID** and **Instagram App Secret** (emphasize: *not* the top-level Meta App ID/secret).
- **Keep Step 2/3** (the wp-config constants block, ~lines 281-292) as-is — that mechanism is unchanged.
- **~line 297 doc link:** change
  `https://developers.facebook.com/docs/instagram-basic-display-api/getting-started` →
  `https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login`.
- Add a sentence: the account **must be a Business or Creator account** (personal no longer supported).

### #63 — guardrail, **no code change**

- `get_app_id()` / `get_app_secret()` (~lines 32-60): keep reading the wp-config constants first,
  placeholders as fallback. **Do not** hardcode real credentials.
- `is_using_placeholder_credentials()` (~lines 120-126) must keep gating the Connect button (gate is at
  `accounts.php` ~line 254; disabled button ~lines 301-306). Leave intact.

### Cosmetic (optional)

- Log-label strings `'graph.instagram.com/me/media'` in
  `includes/admin/class-bwg-igf-admin-ajax.php` (~lines 1218, 1234) are cosmetic — fine as-is.

---

## 4. Live-test checklist (owner does this after code lands, on a real site)

Requires BWG's real app + `BWG_IGF_INSTAGRAM_APP_ID`/`_SECRET` in that site's `wp-config.php`, the site's
redirect URI registered on the app, and a Business/Creator IG account connectable (tester invite or
App Review):

1. **Authorize host** — `www.instagram.com` vs `api.instagram.com/oauth/authorize`: confirm which the app
   accepts with no "Invalid platform app" / invalid-scope.
2. **`instagram_business_basic`** scope accepted for the app's use case.
3. **`/me` fields** — `account_type` (and `id` vs `user_id`) return as expected and map into the
   `bwg_igf_accounts` account-type ENUM.
4. **`like_count` / `comments_count`** actually populate on `/me/media` for the connected business account.
5. **Full round-trip:** Connect → token stored encrypted with ~60-day expiry → media renders on the
   frontend → `refresh_access_token` succeeds near expiry.

Only after this passes: bump version in `bwg-instagram-feed.php` + `readme.txt`, commit, and cut the
GitHub release so client sites auto-update.

---

## 5. When done (code-complete, pre-live-test)

- `php -l` clean on every touched file.
- In `features.db` (no `sqlite3` CLI — use `python3` + `sqlite3` module), set `in_progress=0` for the
  finished features. **Leave `passes=0`** — only a real end-to-end test should mark them passing. Note in
  your summary which are code-complete pending the live test.
- Report: what changed per file, endpoint/field decisions and why, the branch name, and what still needs
  the owner's live test.

---

## 6. Files involved (absolute paths)

- `/home/buckneri/projects/bwg-instagram-feed/includes/class-bwg-igf-instagram-credentials.php` — #59, #63
- `/home/buckneri/projects/bwg-instagram-feed/templates/admin/accounts.php` — #60, #62, #63 gate
- `/home/buckneri/projects/bwg-instagram-feed/includes/class-bwg-igf-instagram-api.php` — #61 (verify / optional version pin)
- `/home/buckneri/projects/bwg-instagram-feed/includes/admin/class-bwg-igf-admin-ajax.php` — log labels only (optional)
