<?php
/**
 * Plugin Name: CryptoPulse Whale Alerts
 * Plugin URI: https://cryptopulse.uno
 * Description: Display real-time whale wallet movements from 34+ EVM chains on your WordPress site.
 * Version: 1.0.0
 * Author: CryptoPulse
 * Author URI: https://cryptopulse.uno
 * License: GPL v2 or later
 * Text Domain: cryptopulse
 */

if (!defined('ABSPATH')) exit;

define('CRYPTOPULSE_VERSION', '1.0.0');
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
                <tr><th>API Key</th><td><input type="text" name="cryptopulse_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" /></td></tr>
                <tr><th>Base URL</th><td><input type="text" name="cryptopulse_base_url" value="<?php echo esc_attr($base_url); ?>" class="regular-text" /></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2>Usage</h2>
        <p><code>[cryptopulse_whales chain="ethereum" limit="10" theme="dark"]</code></p>
        <p><code>[cryptopulse_wallet address="0x..."]</code></p>
    </div>
    <?php
}

// === ENQUEUE ===
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('cryptopulse', CRYPTOPULSE_PLUGIN_URL . 'assets/css/cryptopulse.css', [], CRYPTOPULSE_VERSION);
    wp_enqueue_script('cryptopulse', CRYPTOPULSE_PLUGIN_URL . 'assets/js/cryptopulse.js', [], CRYPTOPULSE_VERSION, true);
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

// === SHORTCODE: WHALES ===
add_shortcode('cryptopulse_whales', function($atts) {
    $atts = shortcode_atts(['chain' => '', 'limit' => '10', 'theme' => 'dark'], $atts);
    $path = '/api/whales?limit=' . intval($atts['limit']);
    if ($atts['chain']) $path .= '&chain=' . urlencode($atts['chain']);

    $data = cryptopulse_api_get($path);
    if (!$data || empty($data['data'])) return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><p>No whale data available.</p></div>';

    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '" data-refresh="60" data-endpoint="' . esc_attr($path) . '">';
    $html .= '<div class="cp-header">🐋 Whale Alerts</div>';
    $html .= '<table class="cp-table"><thead><tr><th>Chain</th><th>Token</th><th>Value</th><th>Type</th></tr></thead><tbody>';

    foreach ($data['data'] as $w) {
        $html .= '<tr>';
        $html .= '<td>' . esc_html($w['chain'] ?? '') . '</td>';
        $html .= '<td>' . esc_html($w['token'] ?? '') . '</td>';
        $html .= '<td>$' . number_format($w['amountUsd'] ?? 0) . '</td>';
        $html .= '<td>' . esc_html($w['type'] ?? '') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<div class="cp-footer">Powered by <a href="https://cryptopulse.uno" target="_blank">CryptoPulse</a></div>';
    $html .= '</div>';
    return $html;
});

// === SHORTCODE: WALLET ===
add_shortcode('cryptopulse_wallet', function($atts) {
    $atts = shortcode_atts(['address' => '', 'theme' => 'dark'], $atts);
    if (!$atts['address']) return '<p>Please provide a wallet address.</p>';

    $data = cryptopulse_api_get('/api/wallet/' . urlencode($atts['address']));
    if (!$data) return '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '"><p>Wallet not found.</p></div>';

    $html = '<div class="cp-widget cp-' . esc_attr($atts['theme']) . '">';
    $html .= '<div class="cp-header">🔍 ' . esc_html(substr($atts['address'], 0, 6) . '...' . substr($atts['address'], -4)) . '</div>';
    $html .= '<div class="cp-stat">Total Value: <strong>$' . number_format($data['totalValueUsd'] ?? 0) . '</strong></div>';
    $html .= '<div class="cp-stat">Chains: <strong>' . esc_html(implode(', ', $data['chains'] ?? [])) . '</strong></div>';
    $html .= '<div class="cp-footer">Powered by <a href="https://cryptopulse.uno" target="_blank">CryptoPulse</a></div>';
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
        echo do_shortcode('[cryptopulse_whales limit="' . ($instance['limit'] ?? 5) . '" theme="' . ($instance['theme'] ?? 'dark') . '"]');
        echo $args['after_widget'];
    }

    public function form($instance) {
        $limit = $instance['limit'] ?? 5;
        $theme = $instance['theme'] ?? 'dark';
        echo '<p><label>Limit: <input type="number" name="' . $this->get_field_name('limit') . '" value="' . esc_attr($limit) . '" min="1" max="50" /></label></p>';
        echo '<p><label>Theme: <select name="' . $this->get_field_name('theme') . '"><option value="dark"' . selected($theme, 'dark', false) . '>Dark</option><option value="light"' . selected($theme, 'light', false) . '>Light</option></select></label></p>';
    }

    public function update($new, $old) {
        return ['limit' => intval($new['limit']), 'theme' => sanitize_text_field($new['theme'])];
    }
}
add_action('widgets_init', function() { register_widget('CryptoPulse_Widget'); });
