/* CryptoPulse Widget JS v1.1.0 */
(function() {
  'use strict';

  // Wallet lookup handler
  window.cryptopulseLookup = function(btn) {
    var widget = btn.closest('.cp-widget');
    var input = widget.querySelector('.cp-input');
    var result = widget.querySelector('.cp-wallet-result');
    var address = input.value.trim();
    if (!address || !address.startsWith('0x')) {
      result.innerHTML = '<p style="color:#ef4444;padding:8px 0">Enter a valid wallet address (0x...)</p>';
      return;
    }

    var base = (window.CryptoPulseWP && CryptoPulseWP.baseUrl) || 'https://cryptopulse.uno';
    var key = (window.CryptoPulseWP && CryptoPulseWP.apiKey) || '';
    var headers = { 'Content-Type': 'application/json' };
    if (key) headers['x-api-key'] = key;

    result.innerHTML = '<p style="opacity:0.5;padding:8px 0">Loading...</p>';

    fetch(base + '/api/wallet/' + address + '?multichain=true', { headers: headers })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data || data.error) {
          result.innerHTML = '<p style="color:#ef4444;padding:8px 0">' + (data.error || 'Wallet not found') + '</p>';
          return;
        }
        var html = '<div style="padding:8px 0">';
        if (data.label) html += '<p><strong>' + data.label + '</strong></p>';
        html += '<p>Address: <code style="font-size:11px">' + address.slice(0, 10) + '...' + address.slice(-6) + '</code></p>';
        if (data.chains) html += '<p>Active on: ' + data.chains.join(', ') + '</p>';
        if (data.smartMoneyScore) html += '<p>Smart Money Score: <strong>' + data.smartMoneyScore + '/100</strong></p>';
        if (data.transactions && data.transactions.length > 0) {
          html += '<p style="margin-top:8px;font-weight:600">Recent Activity:</p>';
          data.transactions.slice(0, 5).forEach(function(tx) {
            html += '<div style="font-size:12px;padding:4px 0;border-bottom:1px solid rgba(128,128,128,0.1)">';
            html += tx.type.toUpperCase() + ' ' + (tx.value || '') + ' ' + (tx.tokenSymbol || '') + ' ($' + (tx.valueUSD || 0).toLocaleString() + ')';
            html += '</div>';
          });
        }
        html += '<p style="margin-top:8px"><a href="' + base + '/dashboard" target="_blank" style="color:#7c3aed">Full analysis →</a></p>';
        html += '</div>';
        result.innerHTML = html;
      })
      .catch(function() {
        result.innerHTML = '<p style="color:#ef4444;padding:8px 0">Error fetching wallet data</p>';
      });
  };

  // Auto-refresh for whale feeds
  document.querySelectorAll('.cp-widget[data-refresh]').forEach(function(el) {
    var interval = parseInt(el.getAttribute('data-refresh')) || 60;
    var endpoint = el.getAttribute('data-endpoint');
    if (!endpoint) return;

    setInterval(function() {
      var base = (window.CryptoPulseWP && CryptoPulseWP.baseUrl) || 'https://cryptopulse.uno';
      var key = (window.CryptoPulseWP && CryptoPulseWP.apiKey) || '';
      var headers = { 'Content-Type': 'application/json' };
      if (key) headers['x-api-key'] = key;
      // Refresh handled by page reload or AJAX (future enhancement)
    }, interval * 1000);
  });
})();
