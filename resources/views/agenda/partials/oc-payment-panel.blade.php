{{--
  Layer de pagamento sobre a coluna direita (slide from right, sem header).
  O conteúdo do #paymentModal.modal-content é montado aqui em runtime.
--}}
<div
  class="agenda-oc-payment-layer"
  id="eventDetailOcPaymentPanel"
  aria-hidden="true"
  aria-label="Pagamento"
>
  <button type="button" class="btn-close agenda-oc-payment-layer__close" id="eventDetailOcPaymentPanelClose" aria-label="Fechar"></button>
  <div class="agenda-oc-payment-layer__body" id="eventDetailOcPaymentPanelHost"></div>
</div>
