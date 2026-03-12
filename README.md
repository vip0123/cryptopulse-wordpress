# CryptoPulse WordPress Plugin

> Display live whale alerts and wallet lookup on any WordPress site.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org)
[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0)

## Features

- 🐋 **Live Whale Feed** — Real-time whale movements widget & shortcode
- 🔍 **Wallet Lookup** — Let visitors search any wallet address
- 🎨 **Dark & Light Themes** — Matches any site design
- ⚡ **Auto-Refresh** — Updates without page reload
- ⚙️ **Admin Settings** — Configure API key, chain, refresh interval

## Install

1. Download `cryptopulse-widget.zip` from [Releases](https://github.com/vip0123/cryptopulse-wordpress/releases) or [cryptopulse.uno/api/download/wordpress](https://cryptopulse.uno/api/download/wordpress)
2. WordPress Dashboard → Plugins → Add New → Upload Plugin
3. Activate **CryptoPulse Whale Alerts**
4. Go to Settings → CryptoPulse → Enter your API key

Get your free API key at [cryptopulse.uno/pricing](https://cryptopulse.uno/pricing)

## Shortcodes

```
[cryptopulse_whales]              — Live whale feed (default settings)
[cryptopulse_whales chain="ethereum" limit="10" theme="dark"]
[cryptopulse_wallet]              — Wallet lookup search box
[cryptopulse_wallet theme="light"]
```

### Shortcode Parameters

| Parameter | Default | Options |
|-----------|---------|---------|
| `chain` | `all` | `ethereum`, `polygon`, `binance-smart-chain`, `arbitrum`, `base`, etc. |
| `limit` | `10` | 1-50 |
| `theme` | `dark` | `dark`, `light` |
| `refresh` | `60` | Refresh interval in seconds |

## Widget

The plugin also registers a **CryptoPulse Whale Alerts** sidebar widget:
- Appearance → Widgets → Add "CryptoPulse Whale Alerts"
- Configure title, chain filter, display count

## Admin Settings

Settings → CryptoPulse:
- **API Key** — Your CryptoPulse API key
- **Base URL** — Default `https://cryptopulse.uno` (change for self-hosted)
- **Default Chain** — Chain filter for all shortcodes
- **Default Theme** — Dark or Light

## File Structure

```
cryptopulse-widget/
├── cryptopulse-widget.php    # Main plugin file
├── readme.txt                # WordPress.org readme
├── uninstall.php             # Cleanup on uninstall
└── assets/
    ├── css/cryptopulse.css   # Widget styles
    └── js/cryptopulse.js     # Auto-refresh & interactions
```

## API

This plugin connects to the [CryptoPulse API](https://cryptopulse.uno/api-docs):
- `GET /api/whales` — Whale movements
- `GET /api/wallet/:address` — Wallet lookup
- `GET /api/chains` — Available chains

## Links

- 🌐 [cryptopulse.uno](https://cryptopulse.uno)
- 📦 [npm SDK](https://www.npmjs.com/package/@cryptopulse/sdk)
- 📄 [API Docs](https://cryptopulse.uno/api-docs)

## License

GPLv2 or later
