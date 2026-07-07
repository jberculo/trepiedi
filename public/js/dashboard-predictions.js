(function () {
    'use strict';

    var config = document.getElementById('dashboard-predictions-config');
    if (!config) {
        return;
    }

    var dashboardErrorText = config.dataset.errorText || 'Error';

    var modalEl = document.getElementById('inconsistent-modal');
    var modalMessage = document.getElementById('inconsistent-modal-message');
    var modalConfirm = document.getElementById('inconsistent-modal-confirm');
    var modal = (modalEl && window.bootstrap) ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;

    // De voorspelling die op een bevestiging in de modal wacht.
    var pending = null;

    function post(form, confirmInconsistent) {
        var body = new FormData(form);
        if (confirmInconsistent) {
            body.append('confirm_inconsistent', '1');
        }

        return fetch(form.action, {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            // Laat een autosave die net vóór het wegnavigeren start alsnog afronden.
            keepalive: true
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        });
    }

    function setStatus(status, text, className) {
        if (status) {
            status.textContent = text;
            status.className = 'save-status small ' + className;
        }
    }

    function showResult(status, result) {
        if (result.ok && result.data.ok) {
            setStatus(status, result.data.message, 'rise');
            return;
        }

        setStatus(status, result.data.message || dashboardErrorText, 'text-danger');
    }

    function save(ctx, confirmInconsistent) {
        if (ctx.btn) {
            ctx.btn.disabled = true;
        }
        setStatus(ctx.status, '...', 'text-muted');

        return post(ctx.form, confirmInconsistent).then(function (result) {
            // Tegenstrijdige voorspelling: eerst bevestigen via de modal.
            if (!confirmInconsistent && result.ok && result.data && result.data.needsConfirmation) {
                if (modal) {
                    if (modalMessage) {
                        modalMessage.textContent = result.data.message;
                    }
                    pending = ctx;
                    modal.show();
                } else {
                    // Geen modal beschikbaar: niet opslaan, wel uitleggen waarom.
                    setStatus(ctx.status, result.data.message, 'text-danger');
                }
                return;
            }

            showResult(ctx.status, result);
        }).catch(function () {
            setStatus(ctx.status, dashboardErrorText, 'text-danger');
        }).finally(function () {
            if (ctx.btn) {
                ctx.btn.disabled = false;
            }
        });
    }

    // Een voorspelling is compleet zodra beide scores én de winnaar zijn ingevuld.
    // Alleen dan wordt automatisch opgeslagen: onvolledige invoer zou de server
    // afwijzen (winnaar is verplicht) en tijdens het typen foutmeldingen geven.
    function fieldValue(form, name) {
        var el = form.querySelector('[name$="[' + name + ']"]');
        return el ? el.value.trim() : '';
    }

    function isComplete(form) {
        return fieldValue(form, 'homeScore') !== ''
            && fieldValue(form, 'awayScore') !== ''
            && fieldValue(form, 'advancingSide') !== '';
    }

    function debounce(fn, wait) {
        var timer = null;
        function debounced() {
            clearTimeout(timer);
            timer = setTimeout(fn, wait);
        }
        debounced.cancel = function () {
            clearTimeout(timer);
        };
        return debounced;
    }

    document.querySelectorAll('.js-prediction-form').forEach(function (form) {
        var ctx = {
            form: form,
            status: form.querySelector('.save-status'),
            btn: form.querySelector('button')
        };

        function autosave() {
            if (isComplete(form)) {
                save(ctx, false);
            }
        }

        // Kort na het typen opslaan, óók als de speler het veld niet verlaat en niet
        // wegnavigeert. Zo gaat een wijziging niet verloren als je gewoon blijft staan.
        var typedSave = debounce(autosave, 900);

        // Expliciet opslaan blijft mogelijk (Enter in een veld verstuurt het formulier).
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            typedSave.cancel();
            save(ctx, false);
        });

        form.querySelectorAll('input, select').forEach(function (field) {
            field.addEventListener('input', typedSave);
            // Veld verlaten of een keuze in de dropdown: meteen opslaan (geen wachttijd).
            field.addEventListener('change', function () {
                typedSave.cancel();
                autosave();
            });
        });
    });

    if (modalConfirm) {
        modalConfirm.addEventListener('click', function () {
            if (!pending) {
                return;
            }
            var ctx = pending;
            pending = null;
            if (modal) {
                modal.hide();
            }
            save(ctx, true);
        });
    }

    // Geannuleerd (kruisje, Annuleren of buiten de modal klikken): niets opslaan,
    // de status terugzetten zodat de speler opnieuw kan kiezen.
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (pending) {
                setStatus(pending.status, '', 'text-muted');
                pending = null;
            }
        });
    }
})();
