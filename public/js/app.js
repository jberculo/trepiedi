(function () {
    'use strict';

    // Bevestiging vóór verzenden (vervangt inline onsubmit="return confirm(...)").
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Tekst selecteren bij klik (vervangt inline onclick="this.select()").
    document.querySelectorAll('[data-select-on-click]').forEach(function (el) {
        el.addEventListener('click', function () {
            el.select();
        });
    });

    // Navigeren naar de gekozen waarde (vervangt inline onchange op een <select>).
    document.querySelectorAll('select[data-navigate-on-change]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.value) {
                window.location.href = select.value;
            }
        });
    });

    // "Alles selecteren"-checkbox die de bijbehorende vinkjes omzet.
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
        master.addEventListener('change', function () {
            document.querySelectorAll('input[name="' + master.dataset.checkAll + '"]').forEach(function (cb) {
                cb.checked = master.checked;
            });
        });
    });

    // Breedte van voortgangsbalken zetten via CSSOM (geen inline style-attribuut nodig).
    document.querySelectorAll('[data-width]').forEach(function (el) {
        el.style.width = el.dataset.width + '%';
    });
})();
