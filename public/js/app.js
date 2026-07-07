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

    // Wegklikbare banners die per periode terugkomen. In localStorage[store] bewaren we
    // de token; is die al gelijk, dan is de banner voor die periode weggeklikt en tonen
    // we 'm niet. De token bepaalt de terugkeer: een datum (elke dag opnieuw) of een
    // datum + inhoud-hash (opnieuw bij een nieuwe dag óf gewijzigde inhoud).
    document.querySelectorAll('[data-dismiss-store]').forEach(function (el) {
        var store = el.dataset.dismissStore;
        var token = el.dataset.dismissToken || '';
        try {
            if (localStorage.getItem(store) === token) {
                el.remove();
                return;
            }
        } catch (e) {
            // localStorage niet beschikbaar; dan tonen we de banner gewoon.
        }

        var btn = el.querySelector('[data-dismiss-close]');
        if (btn) {
            btn.addEventListener('click', function () {
                try {
                    localStorage.setItem(store, token);
                } catch (e) {
                    // negeren
                }
                el.remove();
            });
        }
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
