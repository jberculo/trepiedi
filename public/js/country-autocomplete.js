/*
 * Land-autocomplete voor de wedstrijd-invoer. Hangt zich aan elk invoerveld met
 * data-country-autocomplete en haalt suggesties op bij /api/landen. Elke suggestie
 * toont een vlaggetje; bij keuze wordt de landnaam in het veld gezet.
 */
(function () {
    'use strict';

    var ENDPOINT = '/api/landen';
    var uid = 0;

    function debounce(fn, wait) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    function flagSpan(item) {
        var span = document.createElement('span');
        if (item.code) {
            span.className = 'fi fi-' + item.code + ' team-flag';
        } else {
            span.className = 'team-flag team-flag-unknown';
            span.textContent = '?';
        }
        return span;
    }

    function attach(input) {
        var wrap = document.createElement('div');
        wrap.className = 'country-autocomplete';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.setAttribute('autocomplete', 'off');

        var listId = 'country-ac-' + (++uid);
        var list = document.createElement('ul');
        list.className = 'country-autocomplete-list';
        list.id = listId;
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        wrap.appendChild(list);

        // Combobox-semantiek zodat schermlezers de suggesties aankondigen.
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', listId);
        input.setAttribute('aria-expanded', 'false');

        var items = [];
        var activeIndex = -1;

        function close() {
            list.hidden = true;
            list.innerHTML = '';
            items = [];
            activeIndex = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function choose(name) {
            input.value = name;
            close();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function render(results) {
            list.innerHTML = '';
            items = results;
            activeIndex = -1;
            if (!results.length) {
                close();
                return;
            }
            results.forEach(function (item, i) {
                var li = document.createElement('li');
                li.id = listId + '-opt-' + i;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');
                li.appendChild(flagSpan(item));
                var label = document.createElement('span');
                label.textContent = item.name;
                li.appendChild(label);
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    choose(item.name);
                });
                li.addEventListener('mouseenter', function () { setActive(i); });
                list.appendChild(li);
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function setActive(i) {
            var lis = list.querySelectorAll('li');
            lis.forEach(function (li) {
                li.classList.remove('active');
                li.setAttribute('aria-selected', 'false');
            });
            activeIndex = i;
            if (i >= 0 && lis[i]) {
                lis[i].classList.add('active');
                lis[i].setAttribute('aria-selected', 'true');
                input.setAttribute('aria-activedescendant', lis[i].id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        var fetchSuggestions = debounce(function (q) {
            fetch(ENDPOINT + '?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(render)
                .catch(close);
        }, 150);

        input.addEventListener('input', function () {
            var q = input.value.trim();
            if (q.length < 1) {
                close();
                return;
            }
            fetchSuggestions(q);
        });

        input.addEventListener('keydown', function (e) {
            if (list.hidden) {
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive(Math.min(activeIndex + 1, items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(Math.max(activeIndex - 1, 0));
            } else if (e.key === 'Enter') {
                if (activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    choose(items[activeIndex].name);
                }
            } else if (e.key === 'Escape') {
                close();
            }
        });

        input.addEventListener('blur', function () {
            // Korte vertraging zodat een klik op een suggestie nog meetelt.
            setTimeout(close, 120);
        });
    }

    document.querySelectorAll('input[data-country-autocomplete]').forEach(attach);
})();
