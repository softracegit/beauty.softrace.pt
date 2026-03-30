{{-- Requer: $phoneInputId. O input visível NÃO deve ter name="phone" — o E.164 vai num hidden criado aqui. Opcional: $phoneOptional. --}}
@php($phoneOptional = $phoneOptional ?? false)
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/intlTelInput.min.js"></script>
<script>
(function() {
    const phoneEl = document.getElementById('{{ $phoneInputId }}');
    if (!phoneEl || typeof window.intlTelInput !== 'function') return;
    const phoneOptional = @json($phoneOptional);

    const intlPtBase = 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/i18n/pt';

    (async function initIntlPhone() {
        let ptI18n = {};
        try {
            const [countriesMod, interfaceMod] = await Promise.all([
                import(intlPtBase + '/countries.js'),
                import(intlPtBase + '/interface.js'),
            ]);
            ptI18n = { ...countriesMod.default, ...interfaceMod.default };
        } catch (err) {
            console.warn('intl-tel-input: locale PT não carregado', err);
        }

        const iti = window.intlTelInput(phoneEl, {
            initialCountry: 'pt',
            countryOrder: ['pt', 'br', 'es', 'fr', 'gb', 'de'],
            separateDialCode: true,
            strictMode: true,
            validationNumberType: 'MOBILE',
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/utils.js',
            i18n: {
                ...ptI18n,
                searchPlaceholder: 'Pesquisar',
                zeroSearchResults: 'Nenhum resultado',
            },
        });

        function clearPhoneFeedback() {
            phoneEl.setCustomValidity('');
            phoneEl.classList.remove('is-invalid');
        }
        phoneEl.addEventListener('input', clearPhoneFeedback);
        phoneEl.addEventListener('countrychange', clearPhoneFeedback);

        const form = phoneEl.closest('form');
        if (!form) return;

        /** E.164 no POST sem alterar o input visível (evita o indicativo/número “colarem” no campo antes do envio). */
        function setFormPhoneE164Hidden(value) {
            var h = form.querySelector('input[type="hidden"][data-intl-tel-e164="1"]');
            if (!h) {
                h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'phone';
                h.setAttribute('data-intl-tel-e164', '1');
                form.appendChild(h);
            }
            h.value = value || '';
        }

        /** Envio nativo sem novo evento submit — evita duplo clique com requestSubmit() após preventDefault(). */
        function submitFormAfterPhoneOk() {
            form.removeEventListener('submit', onPhoneFormSubmit);
            form.submit();
        }

        function onPhoneFormSubmit(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
                return;
            }
            e.preventDefault();
            iti.promise.then(function() {
                var e164 = '';
                try {
                    e164 = iti.getNumber() || '';
                } catch (err) { e164 = ''; }
                if (phoneOptional && !e164) {
                    clearPhoneFeedback();
                    setFormPhoneE164Hidden('');
                    submitFormAfterPhoneOk();
                    return;
                }
                if (!iti.isValidNumber()) {
                    phoneEl.classList.add('is-invalid');
                    phoneEl.setCustomValidity('Indique um número de telemóvel válido para o país selecionado (ex.: 9 dígitos em Portugal).');
                    phoneEl.reportValidity();
                    return;
                }
                clearPhoneFeedback();
                try {
                    setFormPhoneE164Hidden(iti.getNumber());
                } catch (err) {
                    setFormPhoneE164Hidden('');
                }
                submitFormAfterPhoneOk();
            }).catch(function() {
                phoneEl.setCustomValidity('Não foi possível validar o número. Verifique a ligação e tente de novo.');
                phoneEl.reportValidity();
            });
        }

        form.addEventListener('submit', onPhoneFormSubmit);
    })();
})();
</script>
