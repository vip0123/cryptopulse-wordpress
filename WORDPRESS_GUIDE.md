# CryptoPulse WordPress Plugin — Installation & Troubleshooting

## Quick Start

1. **Download**: https://cryptopulse.uno/api/download/wordpress
2. **Upload**: WordPress Admin → Plugins → Add New → Upload Plugin
3. **Activate**: Find "CryptoPulse Whale Alerts" and click Activate
4. **Use**: Add any shortcode to a page

## Shortcodes

### 🐋 Whale Feed
```
[cryptopulse_whales]
[cryptopulse_whales chain="ethereum" limit="10"]
[cryptopulse_whales period="7d" theme="light"]
```

### 🔍 Wallet Lookup
```
[cryptopulse_wallet]
```

### 📊 Market Overview
```
[cryptopulse_market]
```

### 🔄 DEX Swaps
```
[cryptopulse_dex chain="polygon"]
```

### 🤖 Alpha Bot
```
[cryptopulse_bot]
```

## Troubleshooting

### ❌ "No whale data available"

**Cause**: WordPress server can't reach cryptopulse.uno API

**Fix**:
1. Go to **WordPress Admin → Settings → CryptoPulse**
2. Click **🧪 Test API Connection**
3. Check error log: **Settings → Debug Log** (if WP_DEBUG enabled)

**If still fails**:
- Enable WP_DEBUG in `wp-config.php`:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);
  ```
- Check `/wp-content/debug.log` for exact error
- Contact your host: "Can you allow outbound HTTPS to cryptopulse.uno:443?"

### ❌ Shortcode doesn't appear

**Cause**: Plugin not activated

**Fix**:
1. Go to WordPress Admin → Plugins
2. Find "CryptoPulse Whale Alerts"
3. Click **Activate** (if not already active)

### ❌ SSL certificate error

**Cause**: WordPress server has SSL verification issues

**Fix**: The plugin auto-skips SSL verification. If still failing:
- Ask host to update PHP SSL certificates
- Or use HTTP instead (not recommended for production)

### ⚠️ Wrong data displayed

**Cause**: Conflicting CSS from theme

**Fix**: Use `theme="light"` or `theme="dark"` parameter:
```
[cryptopulse_whales theme="light"]
```

## Configuration

Go to **WordPress Admin → Settings → CryptoPulse**:

- **API Key** (optional): Get free key at https://cryptopulse.uno/pricing
- **Base URL** (default: https://cryptopulse.uno): Change if self-hosting API

## Getting Help

1. **Check debug log**: `/wp-content/debug.log`
2. **Test API manually**: https://cryptopulse.uno/api/whales
3. **Check version**: Plugin is v1.1.1+

## Features

✅ 34+ chains supported  
✅ Real-time whale movements  
✅ Wallet multichain lookup  
✅ Market overview (cap, volume, fear/greed)  
✅ DEX swap feed  
✅ Alpha Bot performance stats  
✅ Dark/light theme support  
✅ Mobile responsive  
✅ SSL-safe (no certificate issues)  
✅ Auto cURL fallback if wp_remote_get fails  

## Support

- Website: https://cryptopulse.uno
- Telegram: https://t.me/cryptopulse_uno
- GitHub: https://github.com/vip0123/cryptopulse-wordpress
