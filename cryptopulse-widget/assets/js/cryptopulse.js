/* CryptoPulse Widget JS v1.2.0 */
(function() {
  'use strict';

  var BASE = (window.CryptoPulseWP && CryptoPulseWP.baseUrl) || 'https://cryptopulse.uno';
  var KEY = (window.CryptoPulseWP && CryptoPulseWP.apiKey) || '';

  function getHeaders() {
    var h = { 'Content-Type': 'application/json' };
    if (KEY) h['x-api-key'] = KEY;
    return h;
  }

  // Wallet lookup — accepts widget ID string or button element
  window.cryptopulseLookup = function(idOrBtn) {
    var widget, input, result;
    if (typeof idOrBtn === 'string') {
      widget = document.getElementById(idOrBtn);
      input = document.getElementById(idOrBtn + '-input');
      result = document.getElementById(idOrBtn + '-result');
    } else {
      widget = idOrBtn.closest('.cp-widget');
      input = widget.querySelector('.cp-input');
      result = widget.querySelector('.cp-wallet-result');
    }
    if (!input || !result) return;

    var address = input.value.trim();
    if (!address || !address.startsWith('0x')) {
      result.innerHTML = '<p style="color:#ef4444;padding:8px 0">Enter a valid wallet address (0x...)</p>';
      return;
    }

    result.innerHTML = '<p style="opacity:0.5;padding:8px 0">🔍 Scanning 34+ chains...</p>';

    fetch(BASE + '/api/wallet/' + address + '?multichain=true', { headers: getHeaders() })
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
            var usd = tx.valueUSD ? '$' + Number(tx.valueUSD).toLocaleString() : '';
            html += '<div style="font-size:12px;padding:4px 0;border-bottom:1px solid rgba(128,128,128,0.1)">';
            html += (tx.type || 'transfer').toUpperCase() + ' ' + (tx.value || '') + ' ' + (tx.tokenSymbol || '');
            if (usd) html += ' (' + usd + ')';
            html += '</div>';
          });
        }
        html += '<p style="margin-top:8px"><a href="' + BASE + '/dashboard" target="_blank" style="color:#7c3aed">Full analysis →</a></p>';
        html += '</div>';
        result.innerHTML = html;
      })
      .catch(function(err) {
        result.innerHTML = '<p style="color:#ef4444;padding:8px 0">Error: ' + err.message + '</p>';
      });
  };
})();
