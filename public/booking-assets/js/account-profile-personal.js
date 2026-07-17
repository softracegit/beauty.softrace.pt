(function () {
    'use strict';

    function getCsrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('booking-profile-personal-form');
        var modalEl = document.getElementById('booking-profile-personal-modal');
        if (!form || !modalEl) {
            return;
        }
        var url = form.getAttribute('action');
        var errorBox = document.getElementById('booking-profile-personal-error');
        var submitBtn = document.getElementById('booking-profile-personal-submit');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!url) {
                return;
            }
            if (errorBox) {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            var fd = new FormData(form);
            var body = {
                name: (fd.get('name') || '').toString().trim(),
                gender: (fd.get('gender') || '').toString(),
                birth_date: (fd.get('birth_date') || '').toString(),
            };

            function isClientFullNameValid(name) {
                var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
                if (parts.length < 2) {
                    return false;
                }
                for (var i = 0; i < parts.length; i++) {
                    var letters = parts[i].match(/\p{L}/gu);
                    if (!letters || letters.length < 2) {
                        return false;
                    }
                }
                return true;
            }

            if (!isClientFullNameValid(body.name)) {
                var fullNameMsg = (window.bookingI18n && window.bookingI18n.name_full_required)
                    || 'Por favor preencha Nome e Apelido';
                if (errorBox) {
                    errorBox.textContent = fullNameMsg;
                    errorBox.classList.remove('d-none');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                return;
            }

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            })
                .then(function (r) {
                    return r.json().then(function (data) {
                        if (!r.ok) {
                            var msg = (data && data.message) || 'Não foi possível guardar.';
                            if (data && data.errors) {
                                var keys = Object.keys(data.errors);
                                if (keys.length && data.errors[keys[0]][0]) {
                                    msg = data.errors[keys[0]][0];
                                }
                            }
                            throw new Error(msg);
                        }
                        return data || {};
                    });
                })
                .then(function () {
                    var inst = window.bootstrap && window.bootstrap.Modal.getInstance(modalEl);
                    if (inst) {
                        inst.hide();
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    if (errorBox) {
                        errorBox.textContent = err.message || 'Erro.';
                        errorBox.classList.remove('d-none');
                    }
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });
    });
})();
