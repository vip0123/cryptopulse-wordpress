(function() {
  function refreshWidgets() {
    document.querySelectorAll('.cp-widget[data-refresh]').forEach(function(el) {
      var interval = parseInt(el.getAttribute('data-refresh'), 10) * 1000;
      if (!interval || interval < 10000) return;
      setInterval(function() {
        var endpoint = el.getAttribute('data-endpoint');
        if (!endpoint) return;
        fetch(endpoint).then(r => r.json()).then(function(data) {
          if (!data || !data.data) return;
          var tbody = el.querySelector('tbody');
          if (!tbody) return;
          tbody.innerHTML = data.data.map(function(w) {
            return '<tr><td>' + (w.chain||'') + '</td><td>' + (w.token||'') + '</td><td>$' + (w.amountUsd||0).toLocaleString() + '</td><td>' + (w.type||'') + '</td></tr>';
          }).join('');
        }).catch(function() {});
      }, interval);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refreshWidgets);
  else refreshWidgets();
})();
