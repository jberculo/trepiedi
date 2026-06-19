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

    // Scrollpositie bewaren rond een actie die de pagina herlaadt (taal/poule wisselen
    // in het profiel). Zonder dit springt de pagina na de redirect naar boven.
    var SCROLL_KEY = 'trepiedi:keep-scroll';

    try {
        var saved = JSON.parse(sessionStorage.getItem(SCROLL_KEY) || 'null');
        if (saved && saved.path === window.location.pathname) {
            window.scrollTo(0, saved.y);
        }
        sessionStorage.removeItem(SCROLL_KEY);
    } catch (e) {
        // sessionStorage niet beschikbaar; scroll-herstel wordt dan overgeslagen.
    }

    document.querySelectorAll('[data-keep-scroll]').forEach(function (el) {
        var eventName = el.tagName === 'FORM' ? 'submit' : 'click';
        el.addEventListener(eventName, function () {
            try {
                sessionStorage.setItem(SCROLL_KEY, JSON.stringify({
                    path: window.location.pathname,
                    y: window.scrollY,
                }));
            } catch (e) {
                // negeren
            }
        });
    });
})();
