(function () {
    'use strict';

    var config = document.getElementById('dashboard-predictions-config');
    if (!config) {
        return;
    }

    var dashboardErrorText = config.dataset.errorText || 'Error';

    document.querySelectorAll('.js-prediction-form').forEach(function (form) {
        var status = form.querySelector('.save-status');
        var btn = form.querySelector('button');

        function post(confirmInconsistent) {
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

        function showResult(result) {
            if (!status) {
                return;
            }

            if (result.ok && result.data.ok) {
                status.textContent = result.data.message;
                status.className = 'save-status small rise';
                return;
            }

            status.textContent = result.data.message || dashboardErrorText;
            status.className = 'save-status small text-danger';
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (btn) {
                btn.disabled = true;
            }
            if (status) {
                status.textContent = '...';
                status.className = 'save-status small text-muted';
            }

            post(false).then(function (result) {
                // Tegenstrijdige voorspelling: pas opslaan na bevestiging.
                if (result.ok && result.data && result.data.needsConfirmation) {
                    if (window.confirm(result.data.message)) {
                        return post(true).then(showResult);
                    }

                    if (status) {
                        status.textContent = '';
                        status.className = 'save-status small text-muted';
                    }
                    return undefined;
                }

                showResult(result);
                return undefined;
            }).catch(function () {
                if (status) {
                    status.textContent = dashboardErrorText;
                    status.className = 'save-status small text-danger';
                }
            }).finally(function () {
                if (btn) {
                    btn.disabled = false;
                }
            });
        });
    });
})();
