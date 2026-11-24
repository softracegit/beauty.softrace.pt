@extends('partials.layouts.main')
@section('title', 'Serviços | Beauty CRM')

@section('css')
<link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('template/vendor/dragula/dragula.min.css') }}">
<style>
/* Services Top Bar - seguindo template SmartAdmin apps-contacts */
.services-top-bar.contacts-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 0.5rem 0.4rem 0.5rem;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 0 !important;
    background: var(--surface-color);
    border-radius: var(--bs-border-radius-lg) var(--bs-border-radius-lg) 0 0;
}
.services-top-bar .contacts-search {
    flex: 1;
    max-width: none;
}
.services-list-container {
    padding: 1.5rem 1.25rem 1.25rem 2.25rem;
}
#servicesList, .services-group-list {
    margin-left: -15px !important;
}
.service-empty-placeholder .service-item::before {
    display: none;
}
.service-empty-placeholder .service-item:hover {
    box-shadow: none;
}
.service-empty-placeholder .service-item .card-body {
    padding-top: 1rem;
    padding-bottom: 1rem;
}
.category-color-choice .ri-circle-fill {
    font-size: 1rem;
}
/* Editar categoria — ícone junto ao título / lista */
.services-category-title-edit,
.contacts-group-item .services-category-sidebar-edit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    padding: 0;
    border: 0;
    border-radius: 0.35rem;
    background: transparent;
    color: var(--muted-color, #6b7280);
    text-decoration: none !important;
    box-shadow: none;
    line-height: 1;
    transition: color 0.15s ease, background 0.15s ease, opacity 0.15s ease;
}
.services-category-title-edit,
.services-category-options-btn {
    flex-shrink: 0;
    opacity: 0.7;
    /* Alinhamento óptico com o texto do h5 */
    transform: translateY(2px);
}
.services-category-title-edit {
    margin-left: 0.85rem;
}
.services-category-options-btn {
    display: inline-flex;
    align-items: center;
    height: 1.5rem;
    margin-left: 0.55rem;
    padding: 0 0.35rem;
    border: 0;
    border-radius: 0.35rem;
    background: transparent;
    color: var(--muted-color, #6b7280);
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1;
    box-shadow: none;
    text-decoration: none !important;
    transition: color 0.15s ease, background 0.15s ease, opacity 0.15s ease;
}
.services-category-options-btn.dropdown-toggle::after {
    margin-left: 0.3rem;
    vertical-align: 0.15em;
}
.services-category-header .d-flex.align-items-center.min-w-0 {
    align-items: center;
}
.services-category-header .services-category-title {
    line-height: 1.35;
    margin-bottom: 0;
}
.services-category-title-edit:hover,
.services-category-title-edit:focus,
.services-category-options-btn:hover,
.services-category-options-btn:focus {
    opacity: 1;
    color: var(--accent-color, #5b8def);
    background: color-mix(in srgb, var(--accent-color, #5b8def) 12%, transparent);
    text-decoration: none !important;
}
[data-category-block] + [data-category-block] {
    margin-top: 3.25rem;
}
.contacts-group-item .services-category-sidebar-edit {
    margin-left: auto;
    opacity: 0;
    pointer-events: none;
}
.contacts-group-item:hover .services-category-sidebar-edit,
.contacts-group-item:focus-within .services-category-sidebar-edit {
    opacity: 1;
    pointer-events: auto;
}
.contacts-group-item .services-category-sidebar-edit:hover,
.contacts-group-item .services-category-sidebar-edit:focus {
    color: var(--accent-color, #5b8def);
    background: color-mix(in srgb, var(--accent-color, #5b8def) 12%, transparent);
}
.contacts-group-item .services-category-sidebar-edit + .badge {
    margin-left: 0;
}
/* Offcanvas serviços / categorias (mesmo look da agenda) */
.services-offcanvas {
    --bs-offcanvas-width: 50vw !important;
    width: 50vw !important;
    max-width: 100vw;
}
.services-offcanvas.offcanvas-end {
    border-left: 1px solid var(--border-color, rgba(0, 0, 0, 0.1));
    border-radius: 20px 0 0 20px;
    overflow: hidden;
}
/* Foco no contentor ao abrir: sem anel azul do tema (igual à agenda) */
.services-offcanvas:focus,
.services-offcanvas:focus-visible {
    outline: none;
    box-shadow: none;
}
.services-offcanvas__form {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
.services-offcanvas .offcanvas-header {
    padding: 1rem 1.75rem;
}
.services-offcanvas .offcanvas-body {
    flex: 1 1 auto;
    padding: 1.75rem;
    overflow-y: auto;
}
.services-offcanvas .offcanvas-body .form-label:not(.small) {
    color: #9ca3af !important;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: 0.25rem !important;
}
.services-offcanvas .offcanvas-body .form-label:not(.small) .text-danger {
    letter-spacing: 0;
    text-transform: none;
}
.services-offcanvas .offcanvas-body .form-label:not(.small) .text-muted {
    letter-spacing: 0;
    text-transform: none;
    font-weight: 400;
}
.services-offcanvas__check {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
}
.services-offcanvas__check--booking {
    margin-top: 2.5rem;
    margin-bottom: 2.5rem;
}
.services-offcanvas__extras {
    margin-top: 2.25rem;
}
.services-offcanvas__danger-zone {
    margin-top: 2.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color-light, #eef0f3);
}
.services-pricing-block {
    margin-top: 2.25rem;
}
.services-pricing-block > .form-label {
    color: #9ca3af !important;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: 0.75rem !important;
}
.service-variants-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}
.service-variant-row {
    position: relative;
    display: block;
    margin-bottom: 0;
}
/* Handle fora do alinhamento do título/card; só no hover (como na lista de serviços) */
.service-variant-row .service-drag-handle {
    position: absolute;
    left: -1.15rem;
    top: 0;
    bottom: 0;
    padding-right: 0;
    opacity: 0;
    z-index: 2;
}
.service-variant-row:hover .service-drag-handle {
    opacity: 1;
}
.service-variant-row .service-item {
    width: 100%;
    margin-bottom: 0;
    cursor: pointer;
    position: relative;
    --service-category-color: var(--bs-secondary, #6c757d);
}
.service-variant-row .service-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: var(--service-category-color);
    border-radius: 4px 0 0 4px;
}
.service-variant-row .service-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.service-variant-row .service-item > .card-body {
    padding-top: 0.9rem !important;
    padding-bottom: 0.9rem !important;
}
.service-variant-add-btn {
    border-radius: 999px;
    padding-left: 0.9rem;
    padding-right: 0.9rem;
}
/* Modais por cima do offcanvas empilhado (tema fixa .modal em 1055) */
#serviceVariantModal.modal,
#deleteServiceConfirmModal.modal,
#clinicalFieldCreateMaterialModal.modal,
#deleteCategoryConfirmModal.modal,
#deleteServiceVariantConfirmModal.modal {
    --bs-modal-zindex: 1085;
    z-index: 1085;
}
body:has(#serviceVariantModal.show) .modal-backdrop,
body:has(#deleteServiceConfirmModal.show) .modal-backdrop,
body:has(#clinicalFieldCreateMaterialModal.show) .modal-backdrop,
body:has(#deleteCategoryConfirmModal.show) .modal-backdrop,
body:has(#deleteServiceVariantConfirmModal.show) .modal-backdrop {
    z-index: 1080;
}
.services-offcanvas.services-offcanvas--stack {
    /* Mais largo que o anterior, mas com faixa clara do painel de trás (~3.5rem) */
    --bs-offcanvas-width: calc(50vw - 3.5rem) !important;
    width: calc(50vw - 3.5rem) !important;
    z-index: 1065;
    box-shadow: -8px 0 28px rgba(15, 23, 42, 0.08);
}
.services-offcanvas.services-offcanvas--stack:focus,
.services-offcanvas.services-offcanvas--stack:focus-visible {
    outline: none;
}
/* Overlay no offcanvas de trás enquanto o stack está aberto */
.services-offcanvas__stack-scrim {
    display: none;
    position: absolute;
    inset: 0;
    z-index: 20;
    background: rgba(15, 23, 42, 0.32);
    border-radius: inherit;
    cursor: pointer;
}
.services-offcanvas.services-offcanvas--under-stack .services-offcanvas__stack-scrim {
    display: block;
}
@media (max-width: 991.98px) {
    .services-offcanvas.services-offcanvas--stack {
        --bs-offcanvas-width: calc(100vw - 1.25rem) !important;
        width: calc(100vw - 1.25rem) !important;
    }
}
.services-offcanvas__footer {
    flex-shrink: 0;
    padding: 1rem 1.75rem;
    box-sizing: border-box;
    background: var(--bs-body-bg, #fff);
}
.services-offcanvas__footer .btn {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.5;
    padding: var(--spacing-sm) var(--spacing-lg);
    box-sizing: border-box;
    height: calc(0.875rem * 1.5 + var(--spacing-sm) * 2 + 2px);
    min-height: calc(0.875rem * 1.5 + var(--spacing-sm) * 2 + 2px);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.clinical-field-card {
    position: relative;
    display: block;
    margin-bottom: 0;
}
.clinical-field-card .service-drag-handle {
    position: absolute;
    left: -1.15rem;
    top: 0;
    bottom: 0;
    padding-right: 0;
    opacity: 0;
    z-index: 2;
}
.clinical-field-card:hover .service-drag-handle,
.gu-mirror.clinical-field-card .service-drag-handle {
    opacity: 1;
}
.clinical-field-card__body {
    width: 100%;
    border: 1px solid var(--border-color-light, #e5e7eb);
    border-radius: 0.55rem;
    background: #fff;
    padding: 0.7rem 0.8rem;
    cursor: pointer;
}
.clinical-field-card:hover .clinical-field-card__body {
    border-color: color-mix(in srgb, var(--accent-color, #5b8def) 35%, #e5e7eb);
}
.clinical-field-material-swatch {
    width: 1.1rem;
    height: 1.1rem;
    border-radius: 0.3rem;
    border: 1px solid rgba(0,0,0,0.12);
    flex-shrink: 0;
    background-size: cover;
    background-position: center;
}
.clinical-field-materials-search {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #fff;
    padding-bottom: 0.5rem;
    margin-bottom: 0.25rem;
}
.clinical-field-material-row.is-filtered-out,
.clinical-field-material-cat-block.is-filtered-out {
    display: none !important;
}
.clinical-field-status {
    font-weight: 600;
}
.clinical-field-status--active {
    color: #15803d;
}
.clinical-field-status--inactive {
    color: var(--muted-color, #6b7280);
}
.clinical-field-card__summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.clinical-field-ghost-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    border: 0;
    border-radius: 0.35rem;
    background: transparent;
    color: var(--muted-color, #6b7280);
    box-shadow: none;
    text-decoration: none !important;
    font-size: 0.8125rem;
    line-height: 1.2;
    padding: 0.2rem 0.4rem;
    min-height: 1.5rem;
}
.clinical-field-ghost-btn:hover,
.clinical-field-ghost-btn:focus {
    background: rgba(0, 0, 0, 0.04);
    color: var(--heading-color, #111827);
}
a.clinical-field-ghost-btn {
    width: 1.5rem;
    padding-left: 0;
    padding-right: 0;
}
@media (max-width: 767.98px) {
    .services-offcanvas {
        --bs-offcanvas-width: 100% !important;
        width: 100% !important;
        height: 100% !important;
        max-height: 100% !important;
        min-width: 0;
    }
    .services-offcanvas.offcanvas-end {
        border-radius: 0 !important;
        border-left: none;
        top: 0;
        bottom: 0;
    }
}
/* Handle de drag à esquerda: 6 pontos (3 lado a lado), visível só ao passar o rato */
.service-item-row {
    display: flex;
    align-items: stretch;
    margin-bottom: 1rem;
}
.service-drag-handle {
    display: flex;
    align-items: center;
    padding-right: 0.5rem;
    cursor: grab;
    color: var(--bs-secondary-color, #6c757d);
    user-select: none;
    opacity: 0;
    transition: opacity 0.2s ease;
}
.service-item-row:hover .service-drag-handle {
    opacity: 1;
}
.service-drag-handle:active {
    cursor: grabbing;
}
.service-drag-dots {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: repeat(3, 1fr);
    gap: 6px;
    width: 8px;
    height: 23px;
}
.service-drag-dots span {
    width: 2px;
    height: 2px;
    border-radius: 50%;
    background: currentColor;
}
.service-item-row .service-item {
    flex-grow: 1;
    margin-bottom: 0;
    cursor: pointer;
    transition: box-shadow 0.2s;
    position: relative;
    --service-category-color: var(--bs-secondary, #6c757d);
}
/* Padding vertical único (SSR + AJAX) — alinhado com extras */
.service-item-row .service-item > .card-body {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
}
/* Borda esquerda com a cor da categoria; barra arredondada (não reta) */
.service-item-row .service-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0rem;
    bottom: 0rem;
    width: 6px;
    background: var(--service-category-color);
    border-radius: 4px 0 0 4px;
}
.service-item-row .service-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.gu-mirror {
    opacity: 0.8;
    z-index: 1100 !important;
}
.gu-mirror.service-variant-row {
    display: block;
}
.gu-mirror.service-variant-row .service-drag-handle {
    opacity: 1;
}
.gu-mirror .service-drag-handle {
    opacity: 1;
}
.gu-transit {
    opacity: 0.2;
}
.services-category-title {
    font-weight: 600;
}
/* Preço alinhado à direita, mesma tipografia do nome do serviço */
.service-item-name,
.service-item-price {
    font-size: 1rem;
    font-weight: 600;
    color: var(--heading-color, #1e293b);
}
.service-item-price {
    white-space: nowrap;
    margin-right: 0.85rem;
}
/* Botão dos 3 pontinhos: ícone maior e mais escuro */
.service-item .btn-icon {
    color: var(--default-color, #334155);
}
.service-item .btn-icon i {
    font-size: 1.25rem;
}
/* Tipografia e espaçamento interno — iguais em SSR e AJAX */
.service-item-left {
    flex: 1 1 0;
    line-height: 1.4;
    min-width: 0;
}
.service-item-name {
    display: block;
    line-height: 1.35;
    margin-bottom: 0.55rem !important;
}
.service-item-header .service-item-name {
    margin-bottom: 0 !important;
    min-width: 0;
}
.service-item-badges {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.4rem;
}
.service-item-badges .badge {
    font-weight: 500;
    font-size: 0.75rem;
    color: var(--heading-color, #1e293b) !important;
    background-color: var(--background-color, #f1f5f9) !important;
    border: 1px solid var(--border-color, #e5e7eb);
}
.service-item-members-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
    max-width: 100%;
}
.service-item-members-trigger:hover .badge {
    border-color: color-mix(in srgb, var(--accent-color, #0d6efd), transparent 40%);
}
.service-item-members-trigger:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--accent-color, #0d6efd), transparent 50%);
    outline-offset: 2px;
    border-radius: 0.35rem;
}
.service-item-members {
    margin-top: 0;
    flex-shrink: 0;
}
.service-item-members.avatar-group .avatar,
.service-item-members .avatar-more {
    width: 22px;
    height: 22px;
    margin-left: -6px;
}
.service-item-members.avatar-group .avatar:first-child {
    margin-left: 0;
}
.service-item-members .avatar {
    position: relative;
}
.service-item-members-trigger:hover .avatar {
    z-index: 1;
}
.service-item-member-initial {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--background-color, #f1f5f9);
    color: var(--heading-color, #1e293b);
    font-size: 0.625rem;
    font-weight: 600;
    line-height: 1;
    user-select: none;
}
.service-members-modal-store {
    margin-bottom: 1.15rem;
}
.service-members-modal-store:last-child {
    margin-bottom: 0;
}
.service-members-modal-store-title {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--muted-color, #6b7280);
    margin-bottom: 0.55rem;
}
.service-members-modal-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.service-members-modal-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.service-members-modal-item .avatar,
.service-members-modal-item .service-item-member-initial {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    object-fit: cover;
    flex-shrink: 0;
}
.service-members-modal-item .service-item-member-initial {
    border: 1px solid var(--border-color, #e5e7eb);
}
.service-item-desde-duration {
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-size: 1rem;
    font-weight: 600;
    color: var(--heading-color, #1e293b);
    line-height: 1.35;
}
.service-item-duration {
    margin-bottom: 0 !important;
    line-height: 1.4;
}
.service-item-left > .service-item-duration:first-of-type {
    margin-top: 0;
}
.service-item-body--has-options {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.service-item-header,
.service-item-variant-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto 8.5rem;
    column-gap: 1rem;
    align-items: start;
    width: 100%;
}
.catalog-item-short {
    margin-top: 0.2rem;
    font-size: 0.8125rem;
    color: var(--muted-color, #6b7280);
    line-height: 1.35;
}
.service-item-body--has-options > .service-item-header {
    margin-bottom: 0.45rem;
}
.service-item-header-meta:empty {
    width: 0;
}
.service-item-variants {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    width: 100%;
}
.service-item-variant-row {
    padding: 0.2rem 0;
    font-size: 0.8125rem;
    line-height: 1.35;
}
.service-item-variant-copy {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}
.service-item-variant-copy .catalog-item-short {
    margin-top: 0;
    padding-left: 0.9rem;
}
.service-item-variant-name {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    min-width: 0;
    font-weight: 500;
    font-size: 0.8125rem;
    color: var(--heading-color, #1e293b);
}
.service-item-variant-name::before {
    content: '•';
    flex-shrink: 0;
    color: inherit;
}
.service-item-variant-duration {
    font-size: 0.8125rem;
    color: var(--muted-color, #6b7280);
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
}
.service-item-variant-price,
.service-item-header .service-item-price {
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
    margin-right: 0.85rem;
}
.service-item-variant-price {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--heading-color, #1e293b);
}
@media (max-width: 575.98px) {
    .service-item-header,
    .service-item-variant-row {
        grid-template-columns: minmax(0, 1fr) auto 7.25rem;
        column-gap: 0.65rem;
    }
}
.service-item-price {
    line-height: 1.35;
}
.service-item-right {
    justify-content: flex-end;
}
.service-options-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}
.service-options-actions .btn-link {
    text-decoration: none;
}
.contacts-groups-list {
    padding: var(--spacing-sm) var(--spacing-sm) !important;
}
.contacts-groups {
    padding: 0 !important;
    border-bottom: none !important;
}
.contacts-groups-footer {
    padding: 0.35rem var(--spacing-sm) var(--spacing-sm);
    border-top: 1px solid var(--border-color, #e5e7eb);
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.contacts-groups-footer .contacts-group-item {
    border-radius: var(--radius-md) !important;
}
.contacts-groups-footer .contacts-group-footer-icon {
    width: 0.75rem;
    font-size: 0.95rem;
    color: var(--muted-color, #6b7280);
    flex-shrink: 0;
}
.service-item-row--archived .service-drag-handle {
    visibility: hidden;
    pointer-events: none;
}
.service-item-row--archived .service-item {
    cursor: default;
}
.service-archive-restore-btn,
.category-archive-restore-btn {
    flex-shrink: 0;
}
.archive-category-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: var(--radius-md, 0.5rem);
    margin-bottom: 0.5rem;
    background: var(--bs-body-bg, #fff);
}
.archive-category-row__meta {
    min-width: 0;
}
.archive-category-row__name {
    font-weight: 600;
    margin: 0;
}
.archive-category-row__count {
    font-size: 0.85rem;
    color: var(--muted-color, #6b7280);
}
.contacts-groups-list .contacts-group-item {
    border-radius: var(--radius-md) !important;
    cursor: grab;
}
.contacts-groups-list .contacts-group-item[data-category-id="all"] {
    cursor: default;
}
.contacts-groups-list .contacts-group-item:hover {
    color: var(--accent-color) !important;
    background: transparent !important;
}
.contacts-groups-list .contacts-group-item.active:hover {
    background: color-mix(in srgb, var(--accent-color), transparent 90%) !important;
}
.gu-mirror.contacts-group-item {
    cursor: grabbing;
    opacity: 0.92;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
}
.contacts-groups-header {
    padding: 0.3rem 1.25rem 1.3rem !important;
    font-size: 1.2rem !important;
    text-transform: none !important;
    letter-spacing: 0.05em !important;
    color: #333333 !important;
    font-weight: 600 !important;
    border-bottom: 1px solid var(--border-color) !important;
}
</style>
@endsection
@section('content')
@php
    $canDeleteCatalog = auth()->user()?->canDeleteCatalogServicesAndCategories() ?? false;
@endphp

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Services Container -->
<div class="contacts-container">
    <!-- Mobile Sidebar Overlay -->
    <div class="contacts-sidebar-overlay" id="servicesSidebarOverlay"></div>

    <!-- Services Sidebar (Categories) -->
    <div class="contacts-sidebar" id="servicesSidebar">
        <!-- Mobile Close Button -->
        <button class="contacts-sidebar-close" id="servicesSidebarClose" aria-label="Close sidebar">
            <i class="ph ph-x"></i>
        </button>

        <div class="contacts-groups">
            <div class="contacts-groups-list" id="categoriesList" data-sortable="categories">
                <a href="#" class="contacts-group-item {{ !$selectedCategory ? 'active' : '' }}" data-category-id="all" data-category-name="Todas as categorias">
                    <span class="contacts-group-dot" style="background: var(--bs-secondary);"></span>
                    <span>Todas as categorias</span>
                    <span class="badge">{{ $categories->sum(fn($c) => $c->services_count ?? 0) }}</span>
                </a>
                @forelse($categories as $category)
                    <a href="#" class="contacts-group-item {{ $selectedCategory && $selectedCategory->id === $category->id ? 'active' : '' }}" 
                       data-category-id="{{ $category->id }}" 
                       data-category-name="{{ $category->name }}"
                       data-category-color="{{ $category->color }}"
                       data-hidden-from-booking="{{ $category->hidden_from_booking ? '1' : '0' }}">
                        <span class="contacts-group-dot" style="background: {{ $category->color }};"></span>
                        <span class="contacts-group-item__name">{{ $category->name }}</span>
                        @if($category->hidden_from_booking)
                            <i class="ph ph-eye-slash text-muted small services-category-hidden-booking-icon" title="Oculta no booking" aria-hidden="true"></i>
                        @endif
                        <button type="button" class="services-category-sidebar-edit category-header-edit-btn" title="Editar categoria" aria-label="Editar categoria">
                            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                        </button>
                        <span class="badge">{{ $category->services_count ?? $category->services()->count() }}</span>
                    </a>
                @empty
                @endforelse
                @if($categories->isEmpty())
                    <div class="text-center text-muted py-3">
                        <p class="small mb-0">Nenhuma categoria criada ainda.</p>
                    </div>
                @endif
            </div>
            <div class="contacts-groups-footer">
                <a href="#" class="contacts-group-item" id="sidebarCreateCategoryBtn" data-action="create-category">
                    <i class="ph ph-plus-circle contacts-group-footer-icon" aria-hidden="true"></i>
                    <span>Nova categoria</span>
                </a>
                <a href="#" class="contacts-group-item" data-category-id="archive" data-category-name="Arquivo">
                    <i class="ph ph-archive contacts-group-footer-icon" aria-hidden="true"></i>
                    <span>Arquivo</span>
                    <span class="badge" id="archivedServicesBadge">{{ (int) ($archivedServicesCount ?? 0) }}</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Services Main -->
    <div class="contacts-main">
        <!-- Services Top Bar: Pesquisa à esquerda, Botão Criar à direita -->
        <div class="services-top-bar contacts-header d-flex align-items-center gap-2 mb-3">
            <!-- Mobile Sidebar Toggle -->
            <button class="contacts-sidebar-toggle d-lg-none" type="button" id="servicesSidebarToggle" aria-label="Abrir lista de categorias">
                <i class="ph ph-list"></i>
            </button>
            
            <!-- Pesquisa à esquerda -->
            <div class="contacts-search flex-grow-1">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" class="form-control" placeholder="Pesquisar serviços..." id="serviceSearch">
            </div>
            
            <!-- Exportar PDF + Criar -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle text-nowrap" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ph ph-file-pdf me-1"></i>Exportar PDF
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="{{ route('services.export_pdf', ['scope' => 'all']) }}" target="_blank" rel="noopener">
                            Todos
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('services.export_pdf', ['scope' => 'visible']) }}" target="_blank" rel="noopener">
                            Só visíveis
                        </a>
                    </li>
                </ul>
            </div>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ph ph-plus me-2"></i>Criar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" id="openCreateCategoryBtn"><i class="ph ph-folder me-2"></i>Nova Categoria</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="offcanvas" data-bs-target="#addServiceModal" id="pageHeaderAddService"><i class="ph ph-package me-2"></i>Novo Serviço</a></li>
                </ul>
            </div>
        </div>

        <!-- Services List -->
        <div class="services-list-container" id="servicesListContainer" data-selected-category-id="{{ $selectedCategory ? $selectedCategory->id : 'all' }}" data-can-delete-catalog="{{ $canDeleteCatalog ? '1' : '0' }}">
            @if($selectedCategory)
                <div class="services-category-header mb-3 d-flex align-items-center flex-wrap gap-2" data-category-id="{{ $selectedCategory->id }}">
                    <div class="d-flex align-items-center min-w-0">
                        <h5 class="mb-0 services-category-title text-truncate">{{ $selectedCategory->name }}</h5>
                        <button type="button" class="services-category-title-edit category-header-edit-btn" title="Editar categoria" aria-label="Editar categoria">
                            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                        </button>
                        <div class="dropdown">
                            <button class="services-category-options-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Opções
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item category-header-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar categoria</a></li>
                                <li><a class="dropdown-item category-header-add-service-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar serviço</a></li>
                                @if($canDeleteCatalog)
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item category-header-delete-btn text-danger" href="#"><i class="ph ph-archive me-2"></i>Arquivar categoria</a></li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
                <div id="servicesList" data-sortable="services" data-category-id="{{ $selectedCategory->id }}">
                    @include('services.partials.services-list', ['services' => $selectedCategory->services, 'category' => $selectedCategory])
                </div>
            @else
                @forelse($categories as $cat)
                    <div class="mb-4" data-category-block="{{ $cat->id }}">
                        <div class="services-category-header mb-3 d-flex align-items-center flex-wrap gap-2" data-category-id="{{ $cat->id }}">
                            <div class="d-flex align-items-center min-w-0">
                                <h5 class="mb-0 services-category-title text-truncate">{{ $cat->name }}</h5>
                                <button type="button" class="services-category-title-edit category-header-edit-btn" title="Editar categoria" aria-label="Editar categoria">
                                    <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="services-category-options-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item category-header-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar categoria</a></li>
                                        <li><a class="dropdown-item category-header-add-service-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar serviço</a></li>
                                        @if($canDeleteCatalog)
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item category-header-delete-btn text-danger" href="#"><i class="ph ph-archive me-2"></i>Arquivar categoria</a></li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="services-group-list" data-sortable="services" data-category-id="{{ $cat->id }}">
                            @include('services.partials.services-list', ['services' => $cat->services, 'category' => $cat])
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5">
                        <i class="ph-duotone ph-package" style="font-size: 4rem; color: var(--muted-color);"></i>
                        <p class="text-muted mt-3">Nenhum serviço criado ainda.</p>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</div>

<!-- Criar / editar categoria — offcanvas (mesmo padding da agenda) -->
<div class="offcanvas offcanvas-end services-offcanvas" tabindex="-1" id="editCategoryModal" aria-labelledby="editCategoryOffcanvasLabel" data-bs-scroll="true" data-mode="edit">
    <div class="services-offcanvas__stack-scrim" id="editCategoryStackScrim" aria-hidden="true" title="Fechar campo"></div>
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="editCategoryOffcanvasLabel">Editar categoria</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="editCategoryForm" class="services-offcanvas__form">
        @csrf
        <input type="hidden" name="_method" id="editCategoryMethod" value="PUT">
        <input type="hidden" name="category_id" id="editCategoryId" value="">
        <div class="offcanvas-body">
            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label fw-semibold" for="editCategoryName">Nome <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="editCategoryName" required>
                </div>
                <div class="col-md-5">
                    <label for="editCategoryColorSelect" class="form-label fw-semibold">Cor <span class="text-danger">*</span></label>
                    <select class="form-select" id="editCategoryColorSelect" name="color" required>
                        <option value="">Selecionar cor...</option>
                        <option value="#bfdbfe">Azul Céu</option>
                        <option value="#93c5fd">Azul Claro</option>
                        <option value="#a5b4fc">Azul Índigo</option>
                        <option value="#c7d2fe">Azul Lavanda</option>
                        <option value="#ddd6fe">Lavanda</option>
                        <option value="#e9d5ff">Lilás</option>
                        <option value="#f3e8ff">Roxo Pastel</option>
                        <option value="#fbcfe8">Rosa Pastel</option>
                        <option value="#fecdd3">Rosa Claro</option>
                        <option value="#fda4af">Coral Suave</option>
                        <option value="#fed7aa">Laranja Pastel</option>
                        <option value="#fde68a">Âmbar Claro</option>
                        <option value="#fef9c3">Amarelo Pastel</option>
                        <option value="#d9f99d">Verde Lima</option>
                        <option value="#bbf7d0">Verde Menta</option>
                        <option value="#99f6e4">Verde Água</option>
                        <option value="#a5f3fc">Ciano Claro</option>
                        <option value="#bae6fd">Azul Gelo</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="editCategoryDescription">Descrição</label>
                <textarea class="form-control" name="description" id="editCategoryDescription" rows="2"></textarea>
            </div>
            <div class="services-offcanvas__check services-offcanvas__check--booking">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="hidden_from_booking" value="1" id="editCategoryHiddenFromBooking">
                    <label class="form-check-label" for="editCategoryHiddenFromBooking">Ocultar no Booking</label>
                </div>
                <div class="form-text">A categoria e os seus serviços deixam de aparecer na marcação online.</div>
            </div>

            <hr class="my-3">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <div>
                    <h6 class="mb-0">Ficha técnica</h6>
                    <div class="form-text mb-0">Campos técnicos da categoria — arraste para ordenar</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="clinicalFieldAddBtn"><i class="ph ph-plus me-1" aria-hidden="true"></i>Adicionar campo</button>
            </div>
            <div id="clinicalFieldsList" class="d-flex flex-column gap-2 mb-2"></div>
            <div id="clinicalFieldsEmpty" class="text-muted small">Ainda não há campos. Sem schema, a agenda usa os textareas técnicos genéricos.</div>
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="editCategorySubmitBtn">Guardar</button>
        </div>
    </form>
</div>

<!-- Novo serviço — offcanvas -->
<div class="offcanvas offcanvas-end services-offcanvas" tabindex="-1" id="addServiceModal" aria-labelledby="addServiceOffcanvasLabel" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="addServiceOffcanvasLabel">Novo serviço</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="addServiceForm" class="services-offcanvas__form">
        @csrf
        <input type="hidden" name="category_id" id="addServiceCategoryId" value="{{ $selectedCategory->id ?? '' }}">
        <div class="offcanvas-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="addServiceCategorySelect">Categoria <span class="text-danger">*</span></label>
                    <select class="form-select" name="category_id" id="addServiceCategorySelect" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" data-color="{{ $cat->color }}" {{ $selectedCategory && $selectedCategory->id === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="addServiceName">Nome <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="addServiceName" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="addServiceShortDescription">Descrição curta</label>
                <input type="text" class="form-control" name="short_description" id="addServiceShortDescription" maxlength="255" placeholder="Frase breve sob o nome no booking">
            </div>
            <div class="mb-3">
                <label class="form-label" for="addServiceDescription">Descrição</label>
                <textarea class="form-control" name="description" id="addServiceDescription" rows="3"></textarea>
                <div class="form-text">No booking aparece atrás de «O que está incluído?».</div>
            </div>
            @include('services.partials.service-pricing-block', ['prefix' => 'add'])
            <div class="services-offcanvas__check services-offcanvas__check--booking">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="hidden_from_booking" value="1" id="addServiceHiddenFromBooking">
                    <label class="form-check-label" for="addServiceHiddenFromBooking">Ocultar no Booking</label>
                </div>
                <div class="form-text">O serviço deixa de aparecer na marcação online (mantém-se no catálogo e na agenda).</div>
            </div>
            @if(isset($extraCategories) && $extraCategories->isNotEmpty())
            <div class="mb-3 services-offcanvas__extras">
                @include('services.partials.service-extras-association', [
                    'extraCategories' => $extraCategories,
                    'selectedExtraIds' => [],
                    'inputIdPrefix' => 'addService',
                ])
            </div>
            @endif
            @if(isset($fees) && $fees->isNotEmpty())
            <div class="mb-3">
                @include('services.partials.service-fees-association', [
                    'fees' => $fees,
                    'selectedFeeIds' => [],
                    'inputIdPrefix' => 'addService',
                ])
            </div>
            @endif
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="addServiceSubmitBtn">Criar</button>
        </div>
    </form>
</div>

<!-- Editar serviço — offcanvas -->
<div class="offcanvas offcanvas-end services-offcanvas" tabindex="-1" id="editServiceModal" aria-labelledby="editServiceOffcanvasLabel" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="editServiceOffcanvasLabel">Editar serviço</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="editServiceForm" class="services-offcanvas__form">
        @csrf
        @method('PUT')
        <input type="hidden" name="service_id" id="editServiceId">
        <div class="offcanvas-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="editServiceCategoryId">Categoria <span class="text-danger">*</span></label>
                    <select class="form-select" name="category_id" id="editServiceCategoryId" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" data-color="{{ $cat->color }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="editServiceName">Nome <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="editServiceName" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="editServiceShortDescription">Descrição curta</label>
                <input type="text" class="form-control" name="short_description" id="editServiceShortDescription" maxlength="255" placeholder="Frase breve sob o nome no booking">
            </div>
            <div class="mb-3">
                <label class="form-label" for="editServiceDescription">Descrição</label>
                <textarea class="form-control" name="description" id="editServiceDescription" rows="3"></textarea>
                <div class="form-text">No booking aparece atrás de «O que está incluído?».</div>
            </div>
            @include('services.partials.service-pricing-block', ['prefix' => 'edit'])
            <div class="services-offcanvas__check services-offcanvas__check--booking">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="hidden_from_booking" value="1" id="editServiceHiddenFromBooking">
                    <label class="form-check-label" for="editServiceHiddenFromBooking">Ocultar no Booking</label>
                </div>
                <div class="form-text">O serviço deixa de aparecer na marcação online (mantém-se no catálogo e na agenda).</div>
            </div>
            @if(isset($extraCategories) && $extraCategories->isNotEmpty())
            <div class="mb-3 services-offcanvas__extras" id="editServiceExtrasWrap">
                @include('services.partials.service-extras-association', [
                    'extraCategories' => $extraCategories,
                    'selectedExtraIds' => [],
                    'inputIdPrefix' => 'editService',
                ])
            </div>
            @endif
            @if(isset($fees) && $fees->isNotEmpty())
            <div class="mb-3" id="editServiceFeesWrap">
                @include('services.partials.service-fees-association', [
                    'fees' => $fees,
                    'selectedFeeIds' => [],
                    'inputIdPrefix' => 'editService',
                ])
            </div>
            @endif
            @if($canDeleteCatalog)
            <div class="services-offcanvas__danger-zone">
                <button type="button" class="btn btn-outline-danger" id="editServiceDeleteBtn">
                    <i class="ph ph-archive me-1"></i>Arquivar serviço
                </button>
            </div>
            @endif
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="editServiceSubmitBtn">Guardar</button>
        </div>
    </form>
</div>

<!-- Campo da ficha técnica — offcanvas em stack sobre a categoria -->
<div class="offcanvas offcanvas-end services-offcanvas services-offcanvas--stack" tabindex="-1" id="clinicalFieldOffcanvas" aria-labelledby="clinicalFieldOffcanvasLabel" data-bs-scroll="true" data-bs-backdrop="false">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="clinicalFieldOffcanvasLabel">Adicionar campo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="clinicalFieldForm" class="services-offcanvas__form">
        <input type="hidden" id="clinicalFieldEditId" value="">
        <div class="offcanvas-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="clinicalFieldLabel">Label <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="clinicalFieldLabel" maxlength="255" placeholder="Ex: Formato" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="clinicalFieldKey">Chave</label>
                    <input type="text" class="form-control" id="clinicalFieldKey" maxlength="64" placeholder="formato" pattern="[a-z][a-z0-9_]*" autocomplete="off">
                </div>
                <div class="col-12">
                    <label class="form-label" for="clinicalFieldType">Tipo</label>
                    <select class="form-select" id="clinicalFieldType">
                        <option value="text">Texto</option>
                        <option value="textarea">Texto longo</option>
                        <option value="select">Seleção única</option>
                        <option value="multiselect">Seleção múltipla</option>
                        <option value="number">Número</option>
                        <option value="bool">Sim / Não</option>
                        <option value="material">Material</option>
                    </select>
                </div>
                <div class="col-12" id="clinicalFieldOptionsWrap">
                    <label class="form-label" for="clinicalFieldOptions">Opções <span class="text-muted fw-normal">(uma por linha)</span></label>
                    <textarea class="form-control" id="clinicalFieldOptions" rows="3" placeholder="Amêndoa&#10;Oval&#10;Quadrado"></textarea>
                </div>
                <div class="col-12 d-none" id="clinicalFieldMaterialsWrap">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <label class="form-label mb-0">Materiais associados</label>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="clinical-field-ghost-btn" id="clinicalFieldMaterialCreateToggle">Criar material…</button>
                            <a href="{{ route('materials.index') }}" class="clinical-field-ghost-btn" target="_blank" rel="noopener" title="Gerir materiais" aria-label="Gerir materiais">
                                <i class="ph ph-gear" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                    <div class="border rounded p-3" style="max-height: 260px; overflow-y: auto;" id="clinicalFieldMaterialsList">
                        @php
                            $materialsList = $materials ?? collect();
                            $materialsByCategory = $materialsList->groupBy(fn ($m) => $m->materialCategory?->name ?: 'Sem categoria');
                            $materialsSelectableCount = $materialsList->where('is_active', true)->count();
                        @endphp
                        @forelse($materialsByCategory as $catName => $catMaterials)
                            @if($loop->first)
                                <div class="clinical-field-materials-search">
                                    <label class="visually-hidden" for="clinicalFieldMaterialsSearch">Pesquisar materiais</label>
                                    <input
                                        type="search"
                                        class="form-control form-control-sm"
                                        id="clinicalFieldMaterialsSearch"
                                        placeholder="Pesquisar nome, referência, marca…"
                                        autocomplete="off"
                                    >
                                </div>
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="clinicalFieldMaterialsSelectAll"
                                        data-clinical-field-materials-select-all
                                        aria-label="Seleccionar todos os materiais"
                                    >
                                    <label class="form-check-label fw-semibold" for="clinicalFieldMaterialsSelectAll">
                                        Todos os materiais
                                        <span class="badge bg-secondary ms-2" data-clinical-field-materials-count>{{ $materialsSelectableCount }}</span>
                                    </label>
                                </div>
                            @endif
                            @php
                                $catSlug = \Illuminate\Support\Str::slug($catName) ?: 'sem-categoria';
                                $catSelectable = $catMaterials->where('is_active', true)->count();
                            @endphp
                            <div class="clinical-field-material-cat-block mb-3" data-category-name="{{ $catName }}">
                                <div class="form-check mb-2 mt-2 clinical-field-material-cat" data-category-name="{{ $catName }}">
                                    <input
                                        type="checkbox"
                                        class="form-check-input clinical-field-material-select-cat"
                                        id="clinicalFieldMaterialsCat{{ $catSlug }}"
                                        data-category-name="{{ $catName }}"
                                        aria-label="Seleccionar todos em {{ $catName }}"
                                        @disabled($catSelectable === 0)
                                    >
                                    <label class="form-check-label fw-semibold" for="clinicalFieldMaterialsCat{{ $catSlug }}">
                                        {{ $catName }}
                                        <span class="badge bg-secondary ms-2 clinical-field-material-cat-count">{{ $catSelectable }}</span>
                                    </label>
                                </div>
                                @foreach($catMaterials as $material)
                                    @php
                                        $matMeta = array_values(array_filter([
                                            $material->code,
                                            $material->brand,
                                            \App\Models\Material::finishLabel($material->finish),
                                        ]));
                                        $photoUrl = $material->photoUrl();
                                        $hex = $material->hex;
                                        if ($photoUrl) {
                                            $swatchStyle = 'background-image:url('.e($photoUrl).');';
                                        } elseif ($hex) {
                                            $swatchStyle = 'background:'.e($hex).';';
                                        } else {
                                            $swatchStyle = 'background:transparent;border-style:dashed;';
                                        }
                                        $searchBlob = mb_strtolower(trim(implode(' ', array_filter([
                                            $material->name,
                                            $material->code,
                                            $material->brand,
                                            \App\Models\Material::finishLabel($material->finish),
                                            $catName,
                                        ]))));
                                    @endphp
                                    <div
                                        class="form-check ms-3 mb-2 clinical-field-material-row"
                                        data-material-category-name="{{ $catName }}"
                                        data-material-search="{{ $searchBlob }}"
                                    >
                                        <input
                                            class="form-check-input clinical-field-material-cb"
                                            type="checkbox"
                                            value="{{ $material->id }}"
                                            id="clinicalFieldMaterial{{ $material->id }}"
                                            data-material-category-name="{{ $catName }}"
                                            @disabled(! $material->is_active)
                                        >
                                        <label class="form-check-label d-flex align-items-center gap-2 w-100" for="clinicalFieldMaterial{{ $material->id }}">
                                            <span class="clinical-field-material-swatch" style="{{ $swatchStyle }}" aria-hidden="true"></span>
                                            <span class="flex-grow-1">{{ $material->name }}</span>
                                            @if($matMeta)
                                                <span class="text-muted small">{{ implode(' · ', $matMeta) }}</span>
                                            @endif
                                            @unless($material->is_active)
                                                <span class="badge bg-secondary">Inactivo</span>
                                            @endunless
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <p class="text-muted small mb-0" id="clinicalFieldMaterialsEmpty">Ainda não há materiais. Use «Criar material…» acima.</p>
                        @endforelse
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="clinicalFieldRequired">
                        <label class="form-check-label" for="clinicalFieldRequired">Obrigatório</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="clinicalFieldActive" checked>
                        <label class="form-check-label" for="clinicalFieldActive">Activo</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas" id="clinicalFieldCancelBtn">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="clinicalFieldSaveBtn">Guardar campo</button>
        </div>
    </form>
</div>

<!-- Modal criar material (a partir do campo) -->
<div class="modal fade" id="clinicalFieldCreateMaterialModal" tabindex="-1" aria-labelledby="clinicalFieldCreateMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="clinicalFieldCreateMaterialModalLabel">Criar material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="clinicalFieldNewMaterialName">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="clinicalFieldNewMaterialName" maxlength="255" placeholder="Ex: Ruby Red">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="clinicalFieldNewMaterialCategory">Categoria <span class="text-danger">*</span></label>
                        <select class="form-select" id="clinicalFieldNewMaterialCategory">
                            <option value="">— Selecionar —</option>
                            @foreach(($materialCategories ?? collect()) as $matCat)
                                <option value="{{ $matCat->id }}">{{ $matCat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="clinicalFieldNewMaterialBrand">Marca</label>
                        <input type="text" class="form-control" id="clinicalFieldNewMaterialBrand" maxlength="128" placeholder="Ex: Gelish">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="clinicalFieldNewMaterialCode">Referência</label>
                        <input type="text" class="form-control" id="clinicalFieldNewMaterialCode" maxlength="64" placeholder="RR-01">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="clinicalFieldNewMaterialHex">Cor <span class="text-muted fw-normal">(opcional)</span></label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="form-control form-control-color" id="clinicalFieldNewMaterialHexPicker" value="#bfdbfe" aria-label="Cor">
                            <input type="text" class="form-control" id="clinicalFieldNewMaterialHex" maxlength="7" placeholder="#RRGGBB">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="clinicalFieldNewMaterialFinish">Acabamento</label>
                        <select class="form-select" id="clinicalFieldNewMaterialFinish">
                            <option value="">— Sem acabamento —</option>
                            @foreach(\App\Models\Material::finishOptions() as $finishKey)
                                <option value="{{ $finishKey }}">{{ \App\Models\Material::finishLabel($finishKey) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="clinicalFieldNewMaterialStock">Stock</label>
                        <input type="number" class="form-control" id="clinicalFieldNewMaterialStock" min="0" max="999999" step="1" placeholder="Quantidade">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="clinicalFieldNewMaterialPhoto">Foto</label>
                        <input type="file" class="form-control" id="clinicalFieldNewMaterialPhoto" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>
                @if(($materialCategories ?? collect())->isEmpty())
                    <p class="text-muted small mt-3 mb-0">Ainda não há categorias de materiais. Crie uma em <a href="{{ route('materials.index') }}" target="_blank" rel="noopener">Catálogo → Materiais</a>.</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="clinicalFieldMaterialCreateCancel">Cancelar</button>
                <button type="button" class="btn btn-primary" id="clinicalFieldMaterialCreateSave">Criar e seleccionar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal arquivar categoria de serviço -->
<div class="modal fade" id="deleteCategoryConfirmModal" tabindex="-1" aria-labelledby="deleteCategoryConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="deleteCategoryConfirmModalLabel">Arquivar categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">A categoria e todos os seus serviços passam para o Arquivo. Deixam de aparecer no painel e nas marcações novas; o histórico mantém-se.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="deleteCategoryConfirmBtn">Arquivar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal confirmar eliminação de serviço -->
<div class="modal fade" id="deleteServiceConfirmModal" tabindex="-1" aria-labelledby="deleteServiceConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="deleteServiceConfirmModalLabel">Arquivar serviço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">O serviço e as suas variantes passam para o Arquivo. Marcações e vendas antigas mantêm-se ligadas a este serviço.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="deleteServiceConfirmBtn">Arquivar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal eliminar variante (rascunho no formulário) -->
<div class="modal fade" id="deleteServiceVariantConfirmModal" tabindex="-1" aria-labelledby="deleteServiceVariantConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="deleteServiceVariantConfirmModalLabel">Eliminar variante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Tem a certeza que deseja eliminar esta variante?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="deleteServiceVariantConfirmBtn">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal criar / editar variante -->
<div class="modal fade" id="serviceVariantModal" tabindex="-1" aria-labelledby="serviceVariantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="serviceVariantModalLabel">Adicionar variante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="serviceVariantForm">
                <input type="hidden" id="serviceVariantPrefix" value="">
                <input type="hidden" id="serviceVariantEditKey" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="serviceVariantName">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="serviceVariantName" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="serviceVariantShortDescription">Descrição curta</label>
                        <input type="text" class="form-control" id="serviceVariantShortDescription" maxlength="255" placeholder="Frase breve sob o nome no booking">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="serviceVariantDescription">Descrição</label>
                        <textarea class="form-control" id="serviceVariantDescription" rows="2"></textarea>
                        <div class="form-text">No booking aparece atrás de «O que está incluído».</div>
                    </div>
                    <div class="mb-3" id="serviceVariantTypeWrap">
                        <label class="form-label fw-semibold" for="serviceVariantType">Tipo</label>
                        <select class="form-select" id="serviceVariantType">
                            <option value="choice">Escolha única — a cliente escolhe uma das variantes</option>
                            <option value="addon">Adicional — soma-se à variante escolhida</option>
                        </select>
                        <div class="form-text" id="serviceVariantTypeHelp">Uma variante adicional (ex.: Reparação) aparece no Booking como interruptor, e o preço e a duração somam-se aos da variante escolhida.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold" for="serviceVariantDuration">Duração (min) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="serviceVariantDuration" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold" for="serviceVariantPrice">Preço normal (€) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="serviceVariantPrice" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold" for="serviceVariantOnlinePrice">Preço online (€) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="serviceVariantOnlinePrice" step="0.01" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="serviceVariantSaveBtn">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Membros do serviço (todas as lojas) --}}
<div class="modal fade" id="serviceMembersModal" tabindex="-1" aria-labelledby="serviceMembersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="serviceMembersModalLabel">Membros — <span id="serviceMembersModalServiceName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="serviceMembersModalBody"></div>
            <div class="modal-footer justify-content-between">
                <a href="{{ route('services.tecnicos') }}" class="btn btn-link px-0">Gerir em Serviços por equipa</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script src="{{ asset('template/vendor/dragula/dragula.min.js') }}"></script>
<script src="{{ static_asset('js/services.js') }}"></script>
@endsection
