# Instagram API with Instagram Login — integration notes

Meta shut down the **Instagram Basic Display API** on 2024-12-04. This plugin was migrated to
**Instagram API with Instagram Login** (the Graph business-login flow) in July 2026 and the
migration is **complete**. Connected accounts have been fetching media on live client sites since
v1.3.41. This file records how the integration is wired so it does not need to be rediscovered.

## Distribution model

Boston Web Group owns **one** Meta app, shared across its client sites. Each site supplies that
app's credentials via `wp-config.php` constants `BWG_IGF_INSTAGRAM_APP_ID` /
`BWG_IGF_INSTAGRAM_APP_SECRET`, or via the encrypted fields on the plugin Settings page. Constants
take precedence. Real credentials are never committed; the credentials class falls back to
placeholders, and `is_using_placeholder_credentials()` disables the Connect button until real
ones are present.

`client_id` in the OAuth flow is the **Instagram App ID** from the Meta app's Instagram product
("API setup with Instagram login"), not the top-level Meta App ID. The connected account must be
a **Business or Creator** account.

## Flow and endpoints

| Step | Where | Endpoint |
|---|---|---|
| Authorize | `includes/class-bwg-igf-instagram-credentials.php` | `https://www.instagram.com/oauth/authorize`, scope `instagram_business_basic`, CSRF `state` |
| Callback | REST route `/wp-json/bwg-igf/v1/instagram-oauth` | Clean URL with no query string (Instagram rejects query strings in redirect URIs; requires pretty permalinks) |
| Short-lived token | `includes/admin/class-bwg-igf-admin-ajax.php` | `POST https://api.instagram.com/oauth/access_token` |
| Long-lived token | same | `GET https://graph.instagram.com/access_token?grant_type=ig_exchange_token` (~60 days, stored encrypted via `BWG_IGF_Encryption`) |
| User info | same | `GET https://graph.instagram.com/{version}/me?fields=id,username,account_type`; `MEDIA_CREATOR` maps to `creator`, unknown values fall back to `business` |
| Media | `includes/class-bwg-igf-instagram-api.php` | `GET https://graph.instagram.com/{version}/me/media?fields=...,like_count,comments_count` |
| Refresh | same | `GET https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token`, run before expiry by `maybe_refresh_token()` |
| Webhooks | Settings page | Optional receiver with callback URL and verify token, only to satisfy Meta's app setup step |

`{version}` is `BWG_IGF_GRAPH_API_VERSION`, defined in `bwg-instagram-feed.php`. The token
endpoints are documented by Meta without a version segment and are called unversioned.

## Importing from Smash Balloon

`includes/class-bwg-igf-smash-balloon-importer.php` reads `{prefix}sbi_sources`, decrypts each
token the way Smash Balloon does (AES-256-CTR with `LOGGED_IN_KEY`/`LOGGED_IN_SALT`, or the
`SBI_ENCRYPTION_*` constants), strips its junk marker, and classifies the token by prefix:
`IG…` tokens came from Instagram Login and work on graph.instagram.com; `EA…` tokens came from
Facebook Login and only work on graph.facebook.com, so they are not importable. Importable
tokens are verified with `/me` before being written to our accounts table under our own key.
The imported token was issued to Smash Balloon's Meta app; it keeps working until the user
removes that app under Instagram's "Apps and websites", after which a normal Connect is needed.

## Keeping it healthy

- Meta supports each Graph API version for roughly two years. Check the
  [Graph API changelog](https://developers.facebook.com/docs/graph-api/changelog/) and bump
  `BWG_IGF_GRAPH_API_VERSION` before the pinned version expires.
- If Connect starts failing, compare the authorize URL and scopes against the
  [business-login docs](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login)
  first; that is where Meta changes things.
- After any redirect URI change, the new value shown on the Settings page must be re-registered
  on the Meta app.
