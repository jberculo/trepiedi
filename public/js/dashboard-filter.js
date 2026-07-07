(function () {
    'use strict';

    var container = document.getElementById('dashboard-predictions-config');
    if (!container) {
        return;
    }

    var checkboxes = container.querySelectorAll('[data-filter]');
    if (!checkboxes.length) {
        return;
    }

    var STORE = 'trepiedi:predict-filter';
    var cards = container.querySelectorAll('[data-match-category]');
    var sections = container.querySelectorAll('.js-round-section');

    function checkedFilters() {
        var active = {};
        checkboxes.forEach(function (cb) {
            if (cb.checked) {
                active[cb.dataset.filter] = true;
            }
        });
        return active;
    }

    function allChecked() {
        return Array.prototype.every.call(checkboxes, function (cb) {
            return cb.checked;
        });
    }

    function apply() {
        var active = checkedFilters();

        cards.forEach(function (card) {
            card.classList.toggle('d-none', !active[card.dataset.matchCategory]);
        });

        // Verberg een ronde-kop als er na het filteren niets meer in staat. Ronden
        // zonder wedstrijden (placeholdertekst) tonen we alleen als alles aan staat.
        sections.forEach(function (section) {
            var hasCards = section.querySelector('[data-match-category]');
            var anyVisible = section.querySelector('[data-match-category]:not(.d-none)');
            var hide = hasCards ? !anyVisible : !allChecked();
            section.classList.toggle('d-none', hide);
        });

        try {
            localStorage.setItem(STORE, JSON.stringify(Object.keys(active)));
        } catch (e) {
            // localStorage niet beschikbaar; de keuze wordt dan niet onthouden.
        }
    }

    // Opgeslagen keuze herstellen; zonder (geldige) opslag blijft standaard alles aan.
    var stored = null;
    try {
        stored = JSON.parse(localStorage.getItem(STORE) || 'null');
    } catch (e) {
        // negeren
    }
    if (Array.isArray(stored)) {
        checkboxes.forEach(function (cb) {
            cb.checked = stored.indexOf(cb.dataset.filter) !== -1;
        });
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', apply);
    });

    apply();
})();
