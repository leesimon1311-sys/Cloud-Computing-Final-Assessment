/* Progressive enhancement only - the application works with JavaScript off.
   Loaded as an external file so the strict Content-Security-Policy header can
   stay on 'self' with no inline handlers. */
(function () {
    'use strict';

    // Confirmation prompt for destructive forms.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Show the expiry date field only for perishable products.
    var expiryField = document.getElementById('expiry-field');
    var toggles = document.querySelectorAll('[data-type-toggle]');

    if (expiryField && toggles.length) {
        var sync = function () {
            var selected = document.querySelector('[data-type-toggle]:checked');
            var perishable = selected && selected.value === 'perishable';
            expiryField.hidden = !perishable;
        };

        Array.prototype.forEach.call(toggles, function (input) {
            input.addEventListener('change', sync);
        });

        sync();
    }
})();
