<?php
/**
 * Plugin Name: CryptoPulse Whale Alerts
 * Plugin URI: https://cryptopulse.uno
 * Description: Display real-time whale wallet movements from 34+ EVM chains on your WordPress site.
 * Version: 1.1.0
 * Author: CryptoPulse
 * Author URI: https://cryptopulse.uno
 * License: GPL v2 or later
 * Text Domain: cryptopulse
 */

if (!defined('ABSPATH')) exit;

define('CRYPTOPULSE_VERSION', '1.1.0');
define('CRYPTOPULSE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CRYPTOPULSE_PLUGIN_URL', plugin_dir_url(__FILE__));

// === SETTINGS ===
add_action('admin_menu', function() {
    add_options_page('CryptoPulse Settings', 'CryptoPulse', 'manage_options', 'cryptopulse', 'cryptopulse_settings_page');
});

add_action('admin_init', function() {
    register_setting('cryptopulse_options', 'cryptopulse_api_key');
    register_setting('cryptopulse_options', 'cryptopulse_base_url');
});

function cryptopulse_settings_page() {
    $api_key = get_option('cryptopulse_api_key', '');
    $base_url = get_option('cryptopulse_base_url', 'https://cryptopulse.uno');
    ?>
    <div class="wrap">
        <h1>CryptoPulse Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cryptopulse_options'); ?>
            <table class="form-table">
                <tr><th>API Key</th><td><input type="text" name="cryptopulse_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" /><p class="description">Get your free key at <a href="https://cryptopulse.uno/pricing" target="_blank">cryptopulse.uno/pricing</a></p></td></tr>
                <tr><th>Base URL</th><td><input type="text" name="cryptopulse_base_url" value="<?php echo esc_attr($base_url); ?>" class="regular-text" /></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2>Shortcodes</h2>
        <table class="widefat" style="max-width:700px">
            <tr><td><code>[cryptopulse_whales]</code></td><td>Live whale feed with all chains</td></tr>
            <tr><td><code>[cryptopulse_whales chain="ethereum" limit="10"]</code></td><td>Ethereum only, 10 items</td></tr>
            <tr><td><code>[cryptopulse_whales period="7d" theme="light"]</code></td><td>7-day period, light theme</td></tr>
            <tr><td><code>[cryptopulse_wallet]</code></td><td>Wallet lookup search box</td></tr>
            <tr><td><code>[cryptopulse_market]</code></td><td>Market overview (cap, volume, fear/greed)</td></tr>
            <tr><td><code>[cryptopulse_dex]</code></td><td>DEX swap feed</td></tr>
            <tr><td><code>[cryptopulse_bot]</code></td><td>Alpha Bot performance card</td></tr>
        </table>
    </div>
    <?php
}

// === ENQUEUE ===
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('cryptopulse', CRYPTOPULSE_PLUGIN_URL . 'assets/css/cryptopulse.css', [], CRYPTOPULSE_VERSION);
    wp_enqueue_script('cryptopulse', CRYPTOPULSE_PLUGIN_URL . 'assets/js/cryptopulse.js', [], CRYPTOPULSE_VERSION, true);
    wp_localize_script('cryptopulse', 'CryptoPulseWP', [
        'baseUrl' => rtrim(get_option('cryptopulse_base_url', 'https://cryptopulse.uno'), '/'),
        'apiKey' => get_option('cryptopulse_api_key', ''),
    ]);
});

// === API HELPER ===
function cryptopulse_api_get($path) {
    $base = rtrim(get_option('cryptopulse_base_url', 'https://cryptopulse.uno'), '/');
    $key = get_option('cryptopulse_api_key', '');
    $headers = ['Content-Type' => 'application/json'];
    if ($key) $headers['x-api-key'] = $key;

    $response = wp_remote_get($base . $path, ['headers' => $headers, 'timeout' => 15]);
    if (is_wp_error($response)) return null;
    return json_decode(wp_remote_retrieve_body($response), true);
}

function cryptopulse_format_usd($val) {
    if ($val >= 1000000) return '$' . number_format($val / 1000000, 2) . 'M';
    if ($val >= 1000) return '$' . number_format($val / 1000, 2) . 'K';
    return '$' . number_format($val, 2);
}

function cryptopulse_time_ago($ts) {
    $diff = time() - $ts;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

// === SHORTCODE: WHALES ===
add_shortcode('cryptopulse_whales', function($atts) {
    $atts = shortcode_atts([
        'chain' => 'all',
        'limit' => '10',
        'period' => '24h',
        'theme' => 'dark',
    ], $atts);

    $path = '/api/whales?limit=' . intval($atts['limit']) . '&period=' . urlencode($atts['period']);
    if ($atts['chain'] && $atts['chain'] !== 'all') $path .= '&chain=' . urlencode($atts['chain']);

    $data = cryptopulse_api_get($path);
    $txns = $data['transactions'] ?? [];

    if (empty($txns)) {
        return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><div class="cp-header">🐋 Whale Feed</div><p class="cp-empty">No whale data for this period. Try a longer timeframe.</p></div>';
    }

    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header"><span>🐋 Whale Feed</span><span class="cp-badge">' . count($txns) . ' moves</span></div>';

    foreach ($txns as $tx) {
        $type = strtoupper($tx['type'] ?? 'transfer');
        $icon = ($tx['type'] === 'buy') ? '🟢' : (($tx['type'] === 'sell') ? '🔴' : '🟡');
        $value = cryptopulse_format_usd($tx['valueUSD'] ?? 0);
        $token = esc_html($tx['tokenSymbol'] ?? '?');
        $chain = esc_html($tx['chainName'] ?? $tx['chain'] ?? '');
        $from = esc_html($tx['fromLabel'] ?? substr($tx['from'] ?? '', 0, 6) . '...' . substr($tx['from'] ?? '', -4));
        $to = esc_html($tx['toLabel'] ?? substr($tx['to'] ?? '', 0, 6) . '...' . substr($tx['to'] ?? '', -4));
        $time = isset($tx['timestamp']) ? cryptopulse_time_ago($tx['timestamp']) : '';
        $explorer = esc_url($tx['explorerUrl'] ?? '');

        $html .= '<div class="cp-tx cp-tx-' . esc_attr($tx['type'] ?? 'transfer') . '">';
        $html .= '<div class="cp-tx-icon">' . $icon . '</div>';
        $html .= '<div class="cp-tx-body">';
        $html .= '<div class="cp-tx-main"><strong>' . $type . '</strong> ' . esc_html($tx['value'] ?? '') . ' ' . $token . ' <span class="cp-muted">(' . $value . ')</span></div>';
        $html .= '<div class="cp-tx-meta">' . $from . ' → ' . $to;
        if ($chain) $html .= ' <span class="cp-chain">• ' . $chain . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="cp-tx-right">';
        $html .= '<div class="cp-time">' . $time . '</div>';
        if ($explorer) $html .= '<a href="' . $explorer . '" target="_blank" rel="noopener" class="cp-link">View →</a>';
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '<div class="cp-footer">Powered by <a href="https://cryptopulse.uno" target="_blank">CryptoPulse</a></div>';
    $html .= '</div>';
    return $html;
});

// === SHORTCODE: WALLET LOOKUP ===
add_shortcode('cryptopulse_wallet', function($atts) {
    $atts = shortcode_atts(['theme' => 'dark'], $atts);
    $id = 'cp-wallet-' . wp_rand();
    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '" id="' . $id . '">';
    $html .= '<div class="cp-header">🔍 Wallet Lookup</div>';
    $html .= '<div class="cp-search"><input type="text" placeholder="Enter wallet address (0x...)" class="cp-input" />';
    $html .= '<button class="cp-btn" onclick="cryptopulseLookup(this)">Look Up</button></div>';
    $html .= '<div class="cp-wallet-result"></div>';
    $html .= '<div class="cp-footer">Scans 34+ chains · <a href="https://cryptopulse.uno" target="_blank">CryptoPulse</a></div>';
    $html .= '</div>';
    return $html;
});

// === SHORTCODE: MARKET ===
add_shortcode('cryptopulse_market', function($atts) {
    $atts = shortcode_atts(['theme' => 'dark'], $atts);
    $data = cryptopulse_api_get('/api/market');
    if (!$data || !isset($data['overview'])) return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><p class="cp-empty">Market data unavailable.</p></div>';

    $o = $data['overview'];
    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header">📊 Market Overview</div>';
    $html .= '<div class="cp-stats">';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Market Cap</div><div class="cp-stat-value">' . esc_html($o['marketCap'] ?? 'N/A') . '</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">24h Volume</div><div class="cp-stat-value">' . esc_html($o['volume'] ?? 'N/A') . '</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">BTC Dominance</div><div class="cp-stat-value">' . esc_html($o['btcDominance'] ?? 'N/A') . '</div></div>';
    if (isset($o['fearGreed'])) {
        $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Fear/Greed</div><div class="cp-stat-value">' . esc_html($o['fearGreed']) . '</div></div>';
    }
    $html .= '</div>';

    // Prices
    if (!empty($data['prices'])) {
        $html .= '<div class="cp-prices">';
        foreach (array_slice($data['prices'], 0, 5) as $coin) {
            $change = $coin['change24h'] ?? 0;
            $color = $change >= 0 ? '#10b981' : '#ef4444';
            $html .= '<div class="cp-price-row">';
            $html .= '<span>' . esc_html($coin['symbol'] ?? '') . '</span>';
            $html .= '<span>$' . number_format($coin['price'] ?? 0, 2) . '</span>';
            $html .= '<span style="color:' . $color . '">' . ($change >= 0 ? '+' : '') . number_format($change, 1) . '%</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="cp-footer">Powered by <a href="https://cryptopulse.uno" target="_blank">CryptoPulse</a></div>';
    $html .= '</div>';
    return $html;
});

// === SHORTCODE: DEX ===
add_shortcode('cryptopulse_dex', function($atts) {
    $atts = shortcode_atts(['chain' => '', 'period' => '24h', 'limit' => '10', 'theme' => 'dark'], $atts);
    $path = '/api/dex?mode=swaps&period=' . urlencode($atts['period']);
    if ($atts['chain']) $path .= '&chain=' . urlencode($atts['chain']);

    $data = cryptopulse_api_get($path);
    $swaps = $data['swaps'] ?? [];
    if (empty($swaps)) return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><div class="cp-header">🔄 DEX Swaps</div><p class="cp-empty">No DEX swaps found.</p></div>';

    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header"><span>🔄 DEX Swaps</span><span class="cp-badge">' . count($swaps) . '</span></div>';

    foreach (array_slice($swaps, 0, intval($atts['limit'])) as $s) {
        $html .= '<div class="cp-tx">';
        $html .= '<div class="cp-tx-body">';
        $html .= '<div class="cp-tx-main">' . esc_html($s['tokenIn'] ?? '?') . ' → ' . esc_html($s['tokenOut'] ?? '?') . ' <span class="cp-muted">(' . cryptopulse_format_usd($s['valueUSD'] ?? 0) . ')</span></div>';
        $html .= '<div class="cp-tx-meta">' . esc_html($s['walletLabel'] ?? substr($s['wallet'] ?? '', 0, 10) . '...') . ' on ' . esc_html($s['dex'] ?? '') . '</div>';
        $html .= '</div></div>';
    }

    $html .= '<div class="cp-footer">Powered by <a href="https://cryptopulse.uno" target="_blank">CryptoPulse</a></div></div>';
    return $html;
});

// === SHORTCODE: BOT ===
add_shortcode('cryptopulse_bot', function($atts) {
    $atts = shortcode_atts(['theme' => 'dark'], $atts);
    $data = cryptopulse_api_get('/api/bot/status');
    if (!$data || !isset($data['performance'])) return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><p class="cp-empty">Bot status unavailable.</p></div>';

    $p = $data['performance'];
    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header">🤖 Alpha Bot ' . esc_html($data['version'] ?? '') . '</div>';
    $html .= '<div class="cp-stats">';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">P&L</div><div class="cp-stat-value" style="color:#10b981">+' . number_format($p['total_pnl_pct'] ?? 0, 2) . '%</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Win Rate</div><div class="cp-stat-value">' . number_format($p['win_rate'] ?? 0, 1) . '%</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Max DD</div><div class="cp-stat-value">' . number_format($p['max_drawdown_pct'] ?? 0, 2) . '%</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Sharpe</div><div class="cp-stat-value">' . number_format($p['sharpe_ratio'] ?? 0, 2) . '</div></div>';
    $html .= '</div>';
    $html .= '<div class="cp-footer"><a href="https://cryptopulse.uno/bot" target="_blank">View signals →</a></div>';
    $html .= '</div>';
    return $html;
});

// === WIDGET CLASS ===
class CryptoPulse_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('cryptopulse_widget', 'CryptoPulse Whales', ['description' => 'Show recent whale movements']);
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        echo do_shortcode('[cryptopulse_whales limit="' . ($instance['limit'] ?? 5) . '" theme="' . ($instance['theme'] ?? 'dark') . '" period="' . ($instance['period'] ?? '24h') . '"]');
        echo $args['after_widget'];
    }

    public function form($instance) {
        $limit = $instance['limit'] ?? 5;
        $theme = $instance['theme'] ?? 'dark';
        $period = $instance['period'] ?? '24h';
        echo '<p><label>Limit: <input type="number" name="' . $this->get_field_name('limit') . '" value="' . esc_attr($limit) . '" min="1" max="50" /></label></p>';
        echo '<p><label>Theme: <select name="' . $this->get_field_name('theme') . '"><option value="dark"' . selected($theme, 'dark', false) . '>Dark</option><option value="light"' . selected($theme, 'light', false) . '>Light</option></select></label></p>';
        echo '<p><label>Period: <select name="' . $this->get_field_name('period') . '"><option value="1h"' . selected($period, '1h', false) . '>1h</option><option value="6h"' . selected($period, '6h', false) . '>6h</option><option value="24h"' . selected($period, '24h', false) . '>24h</option><option value="7d"' . selected($period, '7d', false) . '>7d</option><option value="30d"' . selected($period, '30d', false) . '>30d</option></select></label></p>';
    }

    public function update($new, $old) {
        return ['limit' => intval($new['limit']), 'theme' => sanitize_text_field($new['theme']), 'period' => sanitize_text_field($new['period'])];
    }
}
add_action('widgets_init', function() { register_widget('CryptoPulse_Widget'); });
