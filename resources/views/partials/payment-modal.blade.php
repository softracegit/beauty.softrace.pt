@php
    $posGorjetaEnabled = $posGorjetaEnabled ?? \App\Models\CrmSetting::posGorjetaEnabled((int) current_store_id());
@endphp
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" data-bs-backdrop="static" role="dialog" aria-modal="true" data-pos-gorjeta-enabled="{{ ($posGorjetaEnabled ?? true) ? '1' : '0' }}">
    <div class="modal-dialog modal-dialog-centered modal-lg payment-pos-modal">
        <div class="modal-content">
            <div class="modal-header pb-2 border-bottom-0">
                <div>
                    <h5 class="modal-title mb-0 fw-semibold" id="paymentModalLabel">Caixa — pagamento</h5>
                </div>
                <button type="button" class="btn-close" id="paymentModalCloseBtn" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="payment-pos-total-hero py-3 px-3 mb-3 rounded-3 border bg-body-secondary bg-opacity-50">
                    <div class="row g-3 align-items-start">
                        <div class="col-md-6 payment-pos-hero-client">
                            <div class="d-flex gap-3 align-items-start">
                                <div class="payment-pos-hero-avatar-wrap flex-shrink-0">
                                    <img src="" alt="" class="payment-pos-hero-avatar rounded-circle border d-none" id="paymentModalHeroAvatar" width="56" height="56">
                                    <span class="payment-pos-hero-avatar-fallback rounded-circle border d-none" id="paymentModalHeroAvatarFallback" aria-hidden="true"></span>
                                </div>
                                <div class="payment-pos-hero-client-lines text-start flex-grow-1 min-w-0 small">
                                    <div class="fw-semibold text-body text-break payment-pos-hero-client-line" id="paymentModalHeroName">—</div>
                                    <div class="text-muted text-break payment-pos-hero-client-line" id="paymentModalHeroPhone">—</div>
                                    <div class="text-muted text-break payment-pos-hero-client-line" id="paymentModalHeroEmail">—</div>
                                    <div class="text-muted text-break payment-pos-hero-client-line" id="paymentModalHeroNif">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 payment-pos-hero-values text-md-end">
                            <div class="small payment-pos-hero-totals-stack mt-0 text-start text-md-end px-md-0 payment-pos-hero-caixa-stack" id="paymentCaixaHeroStack" aria-live="polite">
                                <div class="d-flex justify-content-between justify-content-md-end gap-2 flex-wrap align-items-baseline payment-pos-hero-amount-row" id="paymentLineServices">
                                    <span class="text-muted" id="paymentSubtotalLineLabel">Total 0 serviços:</span>
                                    <span class="fw-semibold text-body" id="paymentSubtotalDisplay">0,00 €</span>
                                </div>
                                <div id="paymentFeesLines" class="payment-pos-hero-fees-stack"></div>
                                <div class="d-flex justify-content-between justify-content-md-end gap-2 flex-wrap align-items-baseline payment-pos-hero-amount-row d-none" id="paymentLineCheckoutTotal">
                                    <span class="text-muted">Total a pagar:</span>
                                    <span class="fw-semibold text-body" id="paymentCheckoutTotalDisplay">0,00 €</span>
                                </div>
                                <div class="d-flex justify-content-between justify-content-md-end gap-2 flex-wrap align-items-baseline payment-pos-hero-amount-row d-none" id="paymentLinePrepagamentoPaid">
                                    <span class="text-muted" id="paymentPrepaidLineLabel">Pré-pagamento:</span>
                                    <span class="fw-semibold text-body" id="paymentOnlinePaidDisplay">-0,00 €</span>
                                </div>
                                <div class="d-flex justify-content-between justify-content-md-end gap-2 flex-wrap align-items-baseline payment-pos-hero-amount-row d-none" id="paymentLineDepositAmount">
                                    <span class="text-muted" id="paymentReservaPercentLabel">Pré-pagamento:</span>
                                    <span class="fw-semibold text-body" id="paymentReservaAmountDisplay">0,00 €</span>
                                </div>
                                <div class="mt-2 d-none text-start text-md-end" id="paymentReservaCustomWrap">
                                    <label for="paymentReservaCustomAmount" class="form-label small text-muted mb-1">Valor do pré-pagamento (€)</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-sm payment-pos-hero-reserva-custom-input text-end" id="paymentReservaCustomAmount" placeholder="0,00" autocomplete="off">
                                </div>
                                <div class="d-flex justify-content-between justify-content-md-end gap-2 flex-wrap align-items-baseline payment-pos-hero-amount-row d-none" id="paymentLineTotalDue">
                                    <span class="fw-semibold text-body">Total a pagar:</span>
                                    <span class="fw-semibold text-body" id="paymentTotalDueDisplay">0,00 €</span>
                                </div>
                                @if($posGorjetaEnabled ?? true)
                                <div class="d-flex justify-content-between justify-content-md-end gap-2 flex-wrap align-items-baseline payment-pos-hero-amount-row d-none" id="paymentGorjetaLine"><span class="text-muted">Gorjeta:</span><span class="fw-semibold text-body" id="paymentGorjetaDisplay">0,00 €</span></div>
                                @endif
                            </div>
                            @if($posGorjetaEnabled ?? true)
                            <div class="d-flex flex-wrap align-items-center justify-content-between justify-content-md-end gap-2 mt-2 pt-2 border-top payment-pos-hero-gorjeta-wrap">
                                <label for="paymentGorjeta" class="form-label small text-muted mb-0">Gorjeta (€)</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm payment-pos-hero-gorjeta-input text-end" id="paymentGorjeta" value="0" placeholder="0,00" title="Gorjeta">
                            </div>
                            @else
                            <input type="hidden" id="paymentGorjeta" value="0">
                            @endif
                        </div>
                    </div>
                    <div class="d-none mt-2 payment-consolidated-details-full" id="paymentConsolidatedDetailsWrap">
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none d-inline-flex align-items-center gap-1" id="paymentConsolidatedToggleBtn" aria-expanded="false">
                            <span>Ver as marcações do dia</span>
                            <i class="ph ph-caret-down" id="paymentConsolidatedToggleIcon" aria-hidden="true"></i>
                        </button>
                        <div class="d-none mt-2" id="paymentConsolidatedListWrap">
                            <div class="small text-muted border rounded-2 p-2 bg-body" id="paymentConsolidatedList"></div>
                        </div>
                    </div>
                </div>

                <p class="small fw-semibold text-uppercase text-muted mb-2" id="paymentFaturaSectionLegend">Fatura</p>
                <div class="tempo-pessoal-type-toggle-wrapper payment-pos-fatura-2x2 mb-2" role="group" aria-labelledby="paymentFaturaSectionLegend" id="paymentFaturaTilesGrid">
                    <button type="button" role="radio" class="tempo-pessoal-type-card btn border rounded-2 payment-invoice-fiscal-card active" data-fiscal-mode="consumer" aria-checked="true" id="paymentFiscalConsumerBtn">
                        <i class="ph ph-receipt tempo-pessoal-type-card-icon" aria-hidden="true"></i>
                        <span class="fw-semibold tempo-pessoal-type-card-name">Consumidor final</span>
                    </button>
                    <button type="button" role="radio" class="tempo-pessoal-type-card btn border rounded-2 payment-invoice-fiscal-card" data-fiscal-mode="with_nif" aria-checked="false" id="paymentFiscalWithNifBtn">
                        <i class="ph ph-identification-card tempo-pessoal-type-card-icon" aria-hidden="true"></i>
                        <span class="fw-semibold tempo-pessoal-type-card-name">Com NIF</span>
                    </button>
                    <button type="button" role="radio" class="tempo-pessoal-type-card btn border rounded-2 payment-invoice-delivery-card" data-invoice-delivery="email" aria-checked="false" id="paymentInvoiceDeliveryEmailBtn" title="Envia o PDF oficial da Vendus para o email do cliente">
                        <i class="ph ph-envelope-simple tempo-pessoal-type-card-icon" aria-hidden="true"></i>
                        <span class="fw-semibold tempo-pessoal-type-card-name">Fatura por email</span>
                    </button>
                    <button type="button" role="radio" class="tempo-pessoal-type-card btn border rounded-2 payment-invoice-delivery-card active" data-invoice-delivery="print" aria-checked="true" id="paymentInvoiceDeliveryPrintBtn" title="Abre o PDF para imprimir quando concluir">
                        <i class="ph ph-printer tempo-pessoal-type-card-icon" aria-hidden="true"></i>
                        <span class="fw-semibold tempo-pessoal-type-card-name">Imprimir fatura</span>
                    </button>
                </div>
                <input type="hidden" id="paymentInvoiceFiscalMode" value="consumer">
                <input type="hidden" id="paymentInvoiceDelivery" value="print">
                <div class="mb-3 d-none" id="paymentModalNifInlineWrap">
                    <label for="paymentModalBillingNif" class="form-label small mb-1">NIF nesta fatura (9 dígitos)</label>
                    <input type="text" class="form-control form-control-sm" id="paymentModalBillingNif" maxlength="9" inputmode="numeric" pattern="[0-9]*" placeholder="123456789" autocomplete="off">
                    <div class="form-text">Será guardado na ficha do cliente ao concluir o pagamento.</div>
                </div>

                <div class="payment-pos-wallet-apply-wrap d-none mb-3" id="paymentWalletApplyWrap">
                    <div class="form-check payment-pos-wallet-apply-check mb-0">
                        <input class="form-check-input" type="checkbox" id="paymentWalletApply" value="1" checked>
                        <label class="form-check-label" for="paymentWalletApply">
                            <span class="d-block fw-semibold text-body" id="paymentWalletApplyLabel">Usar créditos da carteira</span>
                        </label>
                    </div>
                </div>
                <div id="paymentWalletCoversReservaMsg" class="payment-pos-wallet-covers-msg d-none mb-3" role="status">
                    <div class="d-flex gap-2 align-items-start">
                        <i class="ph ph-check-circle text-success payment-pos-wallet-covers-msg__icon" aria-hidden="true"></i>
                        <div class="small">
                            <p class="fw-semibold text-body mb-1">Pagamento só com créditos</p>
                            <p class="text-muted mb-0">O pré-pagamento será debitado da carteira do cliente. Clique em <strong>Pré-pagamento</strong> para concluir.</p>
                        </div>
                    </div>
                </div>
                <div id="paymentWalletPartialSummary" class="d-none small text-muted mb-2" aria-live="polite">
                    <p class="mb-1 d-none" id="paymentWalletUsedLine">
                        Créditos a usar:
                        <strong class="text-success" id="paymentWalletUsedAmount">—</strong>
                    </p>
                    <p class="mb-0 d-none" id="paymentWalletStripeDueLine">
                        Valor a cobrar agora:
                        <strong class="text-body" id="paymentWalletStripeDueAmount">—</strong>
                    </p>
                </div>

                <p class="small fw-semibold text-uppercase text-muted mb-2 mt-3" id="paymentMethodSectionLegend">Pagamento</p>
                <div class="tempo-pessoal-type-toggle-wrapper" role="group" aria-labelledby="paymentMethodSectionLegend" id="paymentMethodToggleGroup">
                    <button type="button" class="tempo-pessoal-type-card btn border rounded-2 payment-method-card" data-method="dinheiro" aria-pressed="false">
                        <i class="ph ph-money tempo-pessoal-type-card-icon" aria-hidden="true"></i>
                        <span class="fw-semibold tempo-pessoal-type-card-name">Dinheiro</span>
                    </button>
                    <button type="button" class="tempo-pessoal-type-card btn border rounded-2 payment-method-card" data-method="cartao" aria-pressed="false" id="paymentMethodCartaoBtn">
                        <i class="ph ph-credit-card tempo-pessoal-type-card-icon" aria-hidden="true"></i>
                        <span class="fw-semibold tempo-pessoal-type-card-name">Cartão</span>
                        <span class="tempo-pessoal-type-card-sub small text-muted d-none" id="paymentMethodCartaoSubtitle"></span>
                    </button>
                    <button type="button" class="tempo-pessoal-type-card btn border rounded-2 payment-method-card" data-method="mbway" aria-pressed="false">
                        <i class="ph ph-device-mobile tempo-pessoal-type-card-icon" aria-hidden="true"></i>
                        <span class="fw-semibold tempo-pessoal-type-card-name">MB Way</span>
                    </button>
                    <button type="button" class="tempo-pessoal-type-card btn border rounded-2 payment-method-card" data-method="creditos_carteira" aria-pressed="false" id="paymentMethodCreditosCarteiraBtn">
                        <i class="ph ph-wallet tempo-pessoal-type-card-icon" aria-hidden="true"></i>
                        <span class="fw-semibold tempo-pessoal-type-card-name">Créditos</span>
                    </button>
                </div>
                <input type="hidden" id="paymentMethodValue" value="">

                <div class="mt-4 d-none" id="paymentMbwayPhoneWrap">
                    <label for="paymentMbwayPhone" class="form-label">Telemóvel MB WAY</label>
                    <input type="tel" class="form-control" id="paymentMbwayPhone" placeholder="+3519XXXXXXXX">
                    <div class="form-text">Se a ficha do cliente não tiver telemóvel, este número ficará guardado.</div>
                </div>
            </div>
            <div class="modal-footer pt-2 pb-3 border-top flex-nowrap gap-2 payment-pos-modal-footer">
                <button type="button" class="btn btn-light" id="paymentCancelBtn">Cancelar</button>
                <button type="button" class="btn btn-outline-secondary fw-semibold" id="paymentDraftBtn" disabled>Rascunho</button>
                <button type="button" class="btn btn-success fw-semibold py-2" id="paymentConfirmBtn" disabled>Pagar 0,00 €</button>
            </div>
        </div>
    </div>
</div>
