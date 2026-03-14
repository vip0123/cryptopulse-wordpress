=== CryptoPulse Whale Alerts ===
Contributors: cryptopulse
Tags: crypto, whale, blockchain, defi, tracker, market, dex, bot
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Display real-time whale wallet movements, market data, DEX swaps, and AI bot performance from 34+ EVM chains on your WordPress site.

== Description ==

CryptoPulse brings real-time crypto intelligence to your WordPress site.

Features:
* Real-time whale movement tracking (95K+ movements)
* 34+ EVM chains supported
* Market overview with Fear/Greed Index
* DEX swap feed
* Alpha Bot performance card
* Wallet lookup across all chains
* Dark and light themes
* Auto-refresh every 60 seconds

Shortcodes:
* `[cryptopulse_whales]` — whale feed (all chains)
* `[cryptopulse_whales chain="ethereum" limit="10" period="7d" theme="dark"]`
* `[cryptopulse_wallet]` — interactive wallet lookup
* `[cryptopulse_market]` — market overview + prices
* `[cryptopulse_dex chain="polygon" period="24h"]` — DEX swaps
* `[cryptopulse_bot]` — Alpha Bot performance

== Installation ==

1. Upload cryptopulse-widget to /wp-content/plugins/
2. Activate the plugin
3. Go to Settings → CryptoPulse
4. (Optional) Enter your API key from cryptopulse.uno
5. Use shortcodes or the widget

== Changelog ==

= 1.2.0 =
* Added [cryptopulse_market] shortcode
* Added [cryptopulse_dex] shortcode
* Added [cryptopulse_bot] shortcode
* Fixed API field name mismatches
* Added cURL fallback for API calls
* Better empty state messages
* Bot evolution history display

= 1.1.0 =
* WordPress shortcode bug fix
* Added settings page with API test

= 1.0.0 =
* Initial release
