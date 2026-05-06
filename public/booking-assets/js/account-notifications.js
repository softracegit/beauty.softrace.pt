(function () {
    'use strict';

    function getCsrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('booking-notification-preferences-form');
        if (!form) {
            return;
        }

        var url = form.getAttribute('action') || '';
        var submitBtn = document.getElementById('booking-notification-preferences-submit');
        var errorBox = document.getElementById('booking-notification-preferences-error');
        var successBox = document.getElementById('booking-notification-preferences-success');

        function showError(message) {
            if (!errorBox) return;
            errorBox.textContent = message || '';
            errorBox.classList.toggle('d-none', !message);
        }

        function showSuccess(message) {
            if (!successBox) return;
            successBox.textContent = message || '';
            successBox.classList.toggle('d-none', !message);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!url) {
                return;
            }

            showError('');
            showSuccess('');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            var fd = new FormData(form);
            var payload = {
                notify_email_booking_updates: fd.get('notify_email_booking_updates') ? 1 : 0,
                notify_email_booking_reminders: fd.get('notify_email_booking_reminders') ? 1 : 0,
                notify_sms_booking_reminders: fd.get('notify_sms_booking_reminders') ? 1 : 0,
            };

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            var message = (data && data.message) || 'Não foi possível guardar as preferências.';
                            if (data && data.errors) {
                                var firstKey = Object.keys(data.errors)[0];
                                if (firstKey && data.errors[firstKey] && data.errors[firstKey][0]) {
                                    message = data.errors[firstKey][0];
                                }
                            }
                            throw new Error(message);
                        }

                        return data || {};
                    });
                })
                .then(function (data) {
                    showSuccess((data && data.message) || 'Preferências guardadas.');
                })
                .catch(function (error) {
                    showError((error && error.message) || 'Não foi possível guardar as preferências.');
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });
    });
})();
