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
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
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

    document.querySelectorAll('.js-prediction-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            save({
                form: form,
                status: form.querySelector('.save-status'),
                btn: form.querySelector('button')
            }, false);
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
