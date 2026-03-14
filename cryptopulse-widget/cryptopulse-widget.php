<?php
/**
 * Plugin Name: CryptoPulse Whale Alerts
 * Plugin URI: https://cryptopulse.uno
 * Description: Display real-time whale wallet movements from 34+ EVM chains on your WordPress site.
 * Version: 1.2.0
 * Author: CryptoPulse
 * Author URI: https://cryptopulse.uno
 * License: GPL v2 or later
 * Text Domain: cryptopulse
 */

if (!defined('ABSPATH')) exit;

define('CRYPTOPULSE_VERSION', '1.2.0');
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
    $test_result = null;
    
    if (isset($_POST['cp_test_api'])) {
        check_admin_referer('cp_test_nonce');
        $test_data = cryptopulse_api_get('/api/whales?limit=1');
        $test_result = $test_data && !empty($test_data['transactions']) ? 'success' : 'failed';
    }
    ?>
    <div class="wrap">
        <h1>CryptoPulse Settings</h1>
        
        <?php if ($test_result === 'success'): ?>
            <div class="notice notice-success"><p>✅ API connection successful! Whale data is flowing.</p></div>
        <?php elseif ($test_result === 'failed'): ?>
            <div class="notice notice-error"><p>❌ API connection failed. Check error logs: wp-content/debug.log</p></div>
        <?php endif; ?>
        
        <form method="post" action="options.php">
            <?php settings_fields('cryptopulse_options'); ?>
            <table class="form-table">
                <tr><th>API Key (optional)</th><td><input type="text" name="cryptopulse_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" /><p class="description">Free tier works without a key. Get a Pro key at <a href="https://cryptopulse.uno/pricing" target="_blank">cryptopulse.uno/pricing</a></p></td></tr>
                <tr><th>Base URL</th><td><input type="text" name="cryptopulse_base_url" value="<?php echo esc_attr($base_url); ?>" class="regular-text" /><p class="description">Default: https://cryptopulse.uno</p></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('cp_test_nonce'); ?>
            <input type="hidden" name="cp_test_api" value="1" />
            <button type="submit" class="button button-secondary">🧪 Test API Connection</button>
        </form>
        
        <h2>Shortcodes</h2>
        <table class="widefat" style="max-width:700px">
            <tr><td><code>[cryptopulse_whales]</code></td><td>Live whale feed — all chains, 10 items</td></tr>
            <tr><td><code>[cryptopulse_whales chain="ethereum" limit="10" period="7d" theme="dark"]</code></td><td>Ethereum only, 7-day window</td></tr>
            <tr><td><code>[cryptopulse_wallet]</code></td><td>Interactive wallet lookup search box</td></tr>
            <tr><td><code>[cryptopulse_market]</code></td><td>Market overview (prices, fear/greed)</td></tr>
            <tr><td><code>[cryptopulse_dex chain="polygon" period="24h"]</code></td><td>DEX swap feed</td></tr>
            <tr><td><code>[cryptopulse_bot]</code></td><td>Alpha Bot performance card</td></tr>
        </table>
        
        <h2>🧪 Diagnostic Info</h2>
        <p>WordPress HTTP API: <?php echo (function_exists('wp_remote_get') ? '✅ Available' : '❌ Missing'); ?></p>
        <p>cURL: <?php echo (function_exists('curl_version') ? '✅ v' . curl_version()['version'] : '❌ Missing'); ?></p>
        <p>allow_url_fopen: <?php echo (ini_get('allow_url_fopen') ? '✅ On' : '❌ Off'); ?></p>
        <p>PHP version: <?php echo phpversion(); ?></p>
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

// === API HELPER (wp_remote_get + cURL fallback) ===
function cryptopulse_api_get($path) {
    $base = rtrim(get_option('cryptopulse_base_url', 'https://cryptopulse.uno'), '/');
    $key = get_option('cryptopulse_api_key', '');
    $url = $base . $path;
    
    $headers = [
        'Content-Type' => 'application/json',
        'User-Agent' => 'CryptoPulse-WP/' . CRYPTOPULSE_VERSION,
    ];
    if ($key) $headers['Authorization'] = 'Bearer ' . $key;

    // Method 1: wp_remote_get
    $response = wp_remote_get($url, [
        'headers' => $headers,
        'timeout' => 15,
        'sslverify' => false,
    ]);
    
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($data) return $data;
        error_log('CryptoPulse: Invalid JSON from wp_remote_get: ' . substr($body, 0, 200));
    } else {
        error_log('CryptoPulse: wp_remote_get failed: ' . $response->get_error_message());
    }

    // Method 2: cURL fallback
    if (function_exists('curl_init')) {
        $ch = curl_init();
        $curl_headers = [];
        foreach ($headers as $k => $v) $curl_headers[] = "$k: $v";
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $curl_headers,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if (!$err && $body) {
            $data = json_decode($body, true);
            if ($data) return $data;
        }
        if ($err) error_log('CryptoPulse: cURL failed: ' . $err);
    }

    return null;
}

function cryptopulse_format_usd($val) {
    if (!$val || !is_numeric($val)) return '$0';
    $val = floatval($val);
    if ($val >= 1000000000) return '$' . number_format($val / 1000000000, 2) . 'B';
    if ($val >= 1000000) return '$' . number_format($val / 1000000, 2) . 'M';
    if ($val >= 1000) return '$' . number_format($val / 1000, 1) . 'K';
    return '$' . number_format($val, 2);
}

function cryptopulse_time_ago($ts) {
    $diff = time() - intval($ts);
    if ($diff < 0) $diff = 0;
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
        return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><div class="cp-header">🐋 Whale Feed</div><p class="cp-empty">No whale data available. <a href="https://cryptopulse.uno" target="_blank">Check API status</a></p></div>';
    }

    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header"><span>🐋 Whale Feed</span><span class="cp-badge">' . count($txns) . ' moves</span></div>';

    foreach ($txns as $tx) {
        $type = strtoupper($tx['type'] ?? 'transfer');
        $icon = ($tx['type'] === 'buy') ? '🟢' : (($tx['type'] === 'sell') ? '🔴' : '🟡');
        $value = cryptopulse_format_usd($tx['valueUSD'] ?? 0);
        $token = esc_html($tx['tokenSymbol'] ?? $tx['tokenName'] ?? '?');
        $chain = esc_html($tx['chainName'] ?? $tx['chain'] ?? '');
        $from = esc_html($tx['fromLabel'] ?? (isset($tx['from']) ? substr($tx['from'], 0, 6) . '...' . substr($tx['from'], -4) : '?'));
        $to = esc_html($tx['toLabel'] ?? (isset($tx['to']) && $tx['to'] ? substr($tx['to'], 0, 6) . '...' . substr($tx['to'], -4) : '?'));
        $time = isset($tx['timestamp']) ? cryptopulse_time_ago($tx['timestamp']) : '';
        $explorer = esc_url($tx['explorerUrl'] ?? '');

        $html .= '<div class="cp-tx cp-tx-' . esc_attr($tx['type'] ?? 'transfer') . '">';
        $html .= '<div class="cp-tx-icon">' . $icon . '</div>';
        $html .= '<div class="cp-tx-body">';
        $html .= '<div class="cp-tx-main"><strong>' . $type . '</strong> ' . $token . ' <span class="cp-muted">(' . $value . ')</span></div>';
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
    $html .= '<div class="cp-search"><input type="text" placeholder="Enter wallet address (0x...)" class="cp-input" id="' . $id . '-input" />';
    $html .= '<button class="cp-btn" onclick="cryptopulseLookup(\'' . $id . '\')">Look Up</button></div>';
    $html .= '<div class="cp-wallet-result" id="' . $id . '-result"></div>';
    $html .= '<div class="cp-footer">Scans 34+ EVM chains · <a href="https://cryptopulse.uno" target="_blank">CryptoPulse</a></div>';
    $html .= '</div>';
    return $html;
});

// === SHORTCODE: MARKET ===
add_shortcode('cryptopulse_market', function($atts) {
    $atts = shortcode_atts(['theme' => 'dark'], $atts);
    $data = cryptopulse_api_get('/api/market');
    if (!$data) return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><p class="cp-empty">Market data unavailable.</p></div>';

    $o = $data['overview'] ?? [];
    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header">📊 Market Overview</div>';
    $html .= '<div class="cp-stats">';

    // Handle both field name formats (totalMarketCap or marketCap)
    $mcap = $o['totalMarketCap'] ?? $o['marketCap'] ?? 0;
    $vol = $o['totalVolume24h'] ?? $o['volume'] ?? 0;
    $btcDom = $o['btcDominance'] ?? 0;
    $fgi = $o['fearGreedIndex'] ?? $o['fearGreed'] ?? null;
    $fgLabel = $o['fearGreedLabel'] ?? '';

    if ($mcap > 0) $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Market Cap</div><div class="cp-stat-value">' . cryptopulse_format_usd($mcap) . '</div></div>';
    if ($vol > 0) $html .= '<div class="cp-stat-card"><div class="cp-stat-label">24h Volume</div><div class="cp-stat-value">' . cryptopulse_format_usd($vol) . '</div></div>';
    if ($btcDom > 0) $html .= '<div class="cp-stat-card"><div class="cp-stat-label">BTC Dominance</div><div class="cp-stat-value">' . number_format($btcDom, 1) . '%</div></div>';
    if ($fgi !== null) $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Fear/Greed</div><div class="cp-stat-value">' . intval($fgi) . ($fgLabel ? " ($fgLabel)" : '') . '</div></div>';
    $html .= '</div>';

    // Prices
    $prices = $data['prices'] ?? [];
    if (!empty($prices)) {
        $html .= '<div class="cp-prices">';
        foreach (array_slice($prices, 0, 10) as $coin) {
            $symbol = esc_html($coin['symbol'] ?? '');
            $price = floatval($coin['price'] ?? $coin['current_price'] ?? 0);
            $change = floatval($coin['change24h'] ?? $coin['price_change_percentage_24h'] ?? 0);
            $color = $change >= 0 ? '#10b981' : '#ef4444';
            $html .= '<div class="cp-price-row">';
            $html .= '<span class="cp-price-symbol">' . $symbol . '</span>';
            $html .= '<span class="cp-price-val">$' . number_format($price, $price < 1 ? 6 : 2) . '</span>';
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
    
    if (empty($swaps)) {
        $msg = $atts['chain'] ? 'No DEX swaps found for ' . esc_html($atts['chain']) . '. Try removing the chain filter.' : 'No DEX swaps found.';
        return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><div class="cp-header">🔄 DEX Swaps</div><p class="cp-empty">' . $msg . '</p></div>';
    }

    $total = $data['total'] ?? count($swaps);
    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header"><span>🔄 DEX Swaps</span><span class="cp-badge">' . number_format($total) . ' total</span></div>';

    foreach (array_slice($swaps, 0, intval($atts['limit'])) as $s) {
        // tokenIn/tokenOut can be objects {symbol,name,amount} or strings
        $tIn = $s['tokenIn'] ?? $s['token_in'] ?? '?';
        $tokenIn = is_array($tIn) ? esc_html($tIn['symbol'] ?? '?') : esc_html($tIn);
        $tOut = $s['tokenOut'] ?? $s['token_out'] ?? '?';
        $tokenOut = is_array($tOut) ? esc_html($tOut['symbol'] ?? '?') : esc_html($tOut);
        $value = cryptopulse_format_usd($s['estimatedUSD'] ?? $s['valueUSD'] ?? $s['usd_value'] ?? 0);
        $wallet = esc_html($s['walletLabel'] ?? (isset($s['wallet']) ? substr($s['wallet'], 0, 10) . '...' : '?'));
        $dex = esc_html($s['dex'] ?? $s['protocol'] ?? '');
        $chain = esc_html($s['chainName'] ?? $s['chain'] ?? '');
        $direction = strtoupper($s['direction'] ?? 'swap');
        $dirIcon = ($s['direction'] === 'buy') ? '🟢' : (($s['direction'] === 'sell') ? '🔴' : '🔄');
        $time = isset($s['timestamp']) ? cryptopulse_time_ago($s['timestamp']) : '';

        $html .= '<div class="cp-tx">';
        $html .= '<div class="cp-tx-icon">' . $dirIcon . '</div>';
        $html .= '<div class="cp-tx-body">';
        $html .= '<div class="cp-tx-main"><strong>' . $direction . '</strong> ' . $tokenIn . ' <span class="cp-muted">(' . $value . ')</span></div>';
        $html .= '<div class="cp-tx-meta">' . $wallet;
        if ($dex && $dex !== 'Direct Transfer') $html .= ' on ' . $dex;
        if ($chain) $html .= ' <span class="cp-chain">• ' . $chain . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        if ($time) $html .= '<div class="cp-tx-right"><div class="cp-time">' . $time . '</div></div>';
        $html .= '</div>';
    }

    $html .= '<div class="cp-footer">Powered by <a href="https://cryptopulse.uno" target="_blank">CryptoPulse</a></div></div>';
    return $html;
});

// === SHORTCODE: BOT ===
add_shortcode('cryptopulse_bot', function($atts) {
    $atts = shortcode_atts(['theme' => 'dark'], $atts);
    $data = cryptopulse_api_get('/api/bot/status');
    if (!$data) return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><p class="cp-empty">Bot status unavailable.</p></div>';

    $p = $data['performance'] ?? [];
    // Handle both camelCase (API) and snake_case field names
    $pnl = $p['totalPnl'] ?? $p['total_pnl_pct'] ?? 0;
    $wr = $p['winRate'] ?? $p['win_rate'] ?? 0;
    $dd = $p['maxDrawdown'] ?? $p['max_drawdown_pct'] ?? 0;
    $sharpe = $p['sharpeRatio'] ?? $p['sharpe_ratio'] ?? 0;
    $pf = $p['profitFactor'] ?? $p['profit_factor'] ?? 0;
    $trades = $p['totalTrades'] ?? $p['total_trades'] ?? 0;
    $version = esc_html($data['version'] ?? '');
    $asset = esc_html($data['asset'] ?? '');

    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header"><span>🤖 Alpha Bot ' . $version . '</span>';
    if ($asset) $html .= '<span class="cp-badge">' . $asset . '</span>';
    $html .= '</div>';
    $html .= '<div class="cp-stats">';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">P&L</div><div class="cp-stat-value" style="color:#10b981">+' . number_format($pnl, 2) . '%</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Win Rate</div><div class="cp-stat-value">' . number_format($wr, 1) . '%</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Max DD</div><div class="cp-stat-value" style="color:#ef4444">' . number_format($dd, 2) . '%</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Sharpe</div><div class="cp-stat-value">' . number_format($sharpe, 2) . '</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Profit Factor</div><div class="cp-stat-value">' . number_format($pf, 2) . '</div></div>';
    $html .= '<div class="cp-stat-card"><div class="cp-stat-label">Trades</div><div class="cp-stat-value">' . intval($trades) . '</div></div>';
    $html .= '</div>';

    // Latest signals
    $sigData = cryptopulse_api_get('/api/bot/signals');
    $signals = $sigData['signals'] ?? [];
    if (!empty($signals)) {
        $sellSignals = array_filter($signals, function($s) { return strtoupper($s['signal'] ?? '') === 'SELL'; });
        $showSignals = array_slice(array_values($sellSignals), 0, 3);
        if (!empty($showSignals)) {
            $html .= '<div class="cp-signals"><div class="cp-evo-title">🔴 Latest Sell Signals</div>';
            foreach ($showSignals as $sig) {
                $symbol = esc_html($sig['symbol'] ?? '');
                $price = number_format(floatval($sig['price'] ?? 0), $sig['price'] < 1 ? 6 : 2);
                $conf = esc_html($sig['confidence'] ?? '');
                $time = esc_html($sig['timestamp'] ?? '');
                $html .= '<div class="cp-signal-row">';
                $html .= '<span class="cp-signal-type" style="color:#ef4444;font-weight:700">SELL</span> ';
                $html .= '<span class="cp-signal-symbol">' . $symbol . '</span> ';
                $html .= '<span class="cp-muted">@ $' . $price . '</span> ';
                $html .= '<span class="cp-signal-conf">' . $conf . '</span>';
                if ($time) $html .= ' <span class="cp-time">' . $time . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
    }

    // Evolution history
    $evo = $data['evolution']['history'] ?? [];
    if (!empty($evo)) {
        $html .= '<div class="cp-evo"><div class="cp-evo-title">📈 Evolution History</div>';
        foreach (array_reverse($evo) as $v) {
            $html .= '<div class="cp-evo-row">';
            $html .= '<span class="cp-evo-ver">' . esc_html($v['version'] ?? '') . '</span>';
            $html .= '<span style="color:#10b981">+' . number_format($v['pnl'] ?? 0, 2) . '%</span>';
            $html .= '<span>' . number_format($v['winRate'] ?? 0, 1) . '% WR</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="cp-footer"><a href="https://cryptopulse.uno/bot" target="_blank">View live signals →</a></div>';
    $html .= '</div>';
    return $html;
});

// === WIDGET CLASS ===
class CryptoPulse_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('cryptopulse_widget', 'CryptoPulse Whales', ['description' => 'Show recent whale movements from 34+ chains']);
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
