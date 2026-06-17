(function () {
    'use strict';

    var config = document.getElementById('dashboard-predictions-config');
    if (!config) {
        return;
    }

    var dashboardErrorText = config.dataset.errorText || 'Error';

    document.querySelectorAll('.js-prediction-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var status = form.querySelector('.save-status');
            var btn = form.querySelector('button');
            if (btn) {
                btn.disabled = true;
            }
            if (status) {
                status.textContent = '...';
                status.className = 'save-status small text-muted';
            }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
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
            }).catch(function () {
                if (!status) {
                    return;
                }

                status.textContent = dashboardErrorText;
                status.className = 'save-status small text-danger';
            }).finally(function () {
                if (btn) {
                    btn.disabled = false;
                }
            });
        });
    });
})();
