(function () {
    'use strict';

    var config = document.getElementById('dashboard-predictions-config');
    if (!config) {
        return;
    }

    var dashboardErrorText = config.dataset.errorText || 'Error';
    var invalidNumberText = config.dataset.invalidNumber || dashboardErrorText;
    var incompleteText = config.dataset.incomplete || '';

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
        // Serialiseer per formulier: bij een al lopende save de nieuwste wijziging
        // markeren en na afloop opnieuw opslaan. Zo kan een trage, oudere response
        // nooit een nieuwere voorspelling overschrijven (out-of-order race).
        if (ctx.saving) {
            ctx.queued = true;
            return;
        }
        ctx.saving = true;

        if (ctx.btn) {
            ctx.btn.disabled = true;
        }
        setStatus(ctx.status, '...', 'text-muted');

        return post(ctx.form, confirmInconsistent).then(function (result) {
            // Tegenstrijdige voorspelling: eerst bevestigen via de modal.
            if (!confirmInconsistent && result.ok && result.data && result.data.needsConfirmation) {
                // Alleen tonen als er nog geen andere bevestiging openstaat, anders
                // zou die verloren gaan; deze krijgt dan een inline melding.
                if (modal && !pending) {
                    if (modalMessage) {
                        modalMessage.textContent = result.data.message;
                    }
                    pending = ctx;
                    modal.show();
                } else {
                    setStatus(ctx.status, result.data.message, 'text-danger');
                }
                return;
            }

            showResult(ctx.status, result);
        }).catch(function () {
            setStatus(ctx.status, dashboardErrorText, 'text-danger');
        }).finally(function () {
            ctx.saving = false;
            if (ctx.btn) {
                ctx.btn.disabled = false;
            }
            // Kwam er tijdens deze save een nieuwere wijziging binnen? Sla die nu op.
            if (ctx.queued) {
                ctx.queued = false;
                save(ctx, false);
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

    function isPartiallyFilled(form) {
        return fieldValue(form, 'homeScore') !== ''
            || fieldValue(form, 'awayScore') !== ''
            || fieldValue(form, 'advancingSide') !== '';
    }

    function scoreFields(form) {
        return [
            form.querySelector('[name$="[homeScore]"]'),
            form.querySelector('[name$="[awayScore]"]')
        ];
    }

    // Ongeldige score-invoer: geen getal (badInput; bij een number-veld wordt de
    // waarde dan leeg) of buiten 0-99. checkValidity zou ook een leeg verplicht veld
    // als ongeldig zien, dus daar kijken we hier bewust niet naar.
    function hasInvalidScore(form) {
        return scoreFields(form).some(function (field) {
            return field && (field.validity.badInput || field.validity.rangeOverflow || field.validity.rangeUnderflow);
        });
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

        // Bepaalt op basis van de huidige invoer wat er moet gebeuren: melden bij een
        // ongeldig getal, opslaan als de voorspelling compleet is, of als waarschuwing
        // (oranje) melden dat er nog niets is opgeslagen omdat niet alles is ingevuld.
        function autosave() {
            if (hasInvalidScore(form)) {
                setStatus(ctx.status, invalidNumberText, 'text-danger');
                return;
            }
            if (isComplete(form)) {
                save(ctx, false);
            } else if (isPartiallyFilled(form) && incompleteText !== '') {
                setStatus(ctx.status, incompleteText, 'text-warning');
            }
        }

        // Kort na het typen reageren, óók als de speler het veld niet verlaat en niet
        // wegnavigeert. Zo gaat een wijziging niet verloren als je gewoon blijft staan,
        // en verschijnt de "nog niet opgeslagen"-melding ook zonder focusverlies.
        var typedSave = debounce(autosave, 900);

        // Expliciet opslaan blijft mogelijk (Enter in een veld verstuurt het formulier).
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            typedSave.cancel();
            autosave();
        });

        form.querySelectorAll('input, select').forEach(function (field) {
            field.addEventListener('input', function () {
                // Ongeldig getal meteen melden; anders kort na het typen afhandelen.
                if (hasInvalidScore(form)) {
                    typedSave.cancel();
                    setStatus(ctx.status, invalidNumberText, 'text-danger');
                    return;
                }
                typedSave();
            });
            // Veld verlaten of een keuze in de dropdown: meteen reageren (geen wachttijd).
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
