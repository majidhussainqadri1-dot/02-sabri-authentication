=== Sabri Authentication and Accounts ===
Contributors: sabrihomeopathy
Tags: authentication, google login, accounts, homeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

Secure email and Google account authentication for the Sabri Social Homeopathy Platform.

== Installation ==

1. Install and activate Sabri Platform Foundation first.
2. Upload this ZIP in WordPress Admin under Plugins > Add New Plugin > Upload Plugin.
3. Activate Sabri Authentication and Accounts.
4. Open Sabri Platform > Accounts.
5. Email/password registration works immediately.
6. Configure Google OAuth before enabling Continue with Google.

== Google OAuth setup ==

Create a Google Cloud Web application OAuth client for your verified domain. Add the exact redirect URI displayed on the Accounts settings page. Configure a public app home, Privacy Policy and Terms on sabrihomeopathy.com. The module requests only openid, email and profile and does not retain Google access or refresh tokens.

== Security ==

This module uses WordPress nonces, sanitization, output escaping, rate limiting, safe redirects, state and nonce checks, verified Google email, token claim validation and encrypted storage for the Google client secret.

== Changelog ==

= 0.1.0 =
* Initial authentication and account foundation.

