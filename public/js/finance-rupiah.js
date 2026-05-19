/**
 * Finance Module - Rupiah Input Formatter
 * Otomatis format input angka ke format rupiah (1.000.000)
 * Gunakan class "fm-rupiah" pada input number.
 */
(function() {
    function formatRupiah(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function unformatRupiah(str) {
        return str.replace(/\./g, '');
    }

    function initRupiahInputs() {
        document.querySelectorAll('input.fm-rupiah').forEach(function(input) {
            if (input.dataset.rupiahInit) return;
            input.dataset.rupiahInit = '1';

            // Change type to text for formatting
            input.type = 'text';
            input.inputMode = 'numeric';

            // Format existing value
            if (input.value && input.value !== '0') {
                input.value = formatRupiah(input.value);
            }

            input.addEventListener('input', function() {
                var raw = unformatRupiah(this.value).replace(/[^0-9]/g, '');
                if (raw === '') {
                    this.value = '';
                    return;
                }
                this.value = formatRupiah(raw);
            });

            input.addEventListener('focus', function() {
                if (this.value === '0') this.value = '';
            });
        });
    }

    // Override fetch to auto-unformat rupiah values before sending
    var originalFetch = window.fetch;
    window.fetch = function() {
        var args = Array.from(arguments);
        if (args[1] && args[1].body && typeof args[1].body === 'string') {
            try {
                var body = JSON.parse(args[1].body);
                ['amount', 'balance', 'fee', 'total'].forEach(function(key) {
                    if (body[key] && typeof body[key] === 'string') {
                        body[key] = parseInt(unformatRupiah(body[key])) || 0;
                    }
                });
                args[1].body = JSON.stringify(body);
            } catch(e) {}
        }
        return originalFetch.apply(this, args);
    };

    // Init on load and after DOM changes (for modals)
    document.addEventListener('DOMContentLoaded', initRupiahInputs);
    var observer = new MutationObserver(initRupiahInputs);
    observer.observe(document.body, { childList: true, subtree: true });
})();
