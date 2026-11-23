@php
    $schemaCats = $clinicalPreferenceSchema['categories'] ?? [];
    $valuesByCat = $clinicalPreferenceValues['by_category'] ?? [];
    $s = $stats ?? null;

    $formatPrefValue = static function (mixed $val): string {
        if ($val === null || $val === '' || $val === []) {
            return '—';
        }
        if (is_bool($val)) {
            return $val ? 'Sim' : 'Não';
        }
        if (is_array($val)) {
            if (! array_is_list($val) && (isset($val['name']) || isset($val['id']))) {
                return app(\App\Services\ClinicalSheetService::class)->formatDisplayValue($val);
            }

            return implode(', ', array_map(fn ($item) => is_scalar($item) || $item === null ? (string) $item : '', $val));
        }

        return (string) $val;
    };

    $hasAnyValue = false;
    foreach ($schemaCats as $cat) {
        $cid = (string) ($cat['category_id'] ?? '');
        foreach (($cat['fields'] ?? []) as $field) {
            $val = $valuesByCat[$cid][$field['key'] ?? ''] ?? null;
            if ($val !== null && $val !== '' && $val !== []) {
                $hasAnyValue = true;
                break 2;
            }
        }
    }
    $topServico = ($s->topServicos ?? collect())->first();
@endphp

<div class="row g-4 align-items-start">
    <div class="col-12 col-xl-8">
        <div class="csheet-section-head">
            <div>
                <h5 class="mb-0">Preferências do cliente</h5>
                <p class="text-muted small mb-0">Calculadas automaticamente a partir das fichas das marcações — em cada campo, o valor que o cliente escolhe mais vezes.</p>
            </div>
        </div>

        @if(empty($schemaCats))
            <div class="csheet-card">
                <p class="csheet-empty">Ainda não há campos de ficha técnica configurados nas categorias (Serviços → editar categoria → Ficha clínica).</p>
            </div>
        @else
            @if(! $hasAnyValue)
                <div class="csheet-hint mb-3">
                    <i class="ph ph-info" aria-hidden="true"></i>
                    <span>Ainda sem dados — as preferências aparecem depois de preencher a ficha técnica nas marcações deste cliente.</span>
                </div>
            @endif

            @foreach($schemaCats as $i => $cat)
                @php
                    $cid = (string) ($cat['category_id'] ?? '');
                    $catValues = $valuesByCat[$cid] ?? [];
                @endphp
                <section class="csheet-card">
                    <h6 class="csheet-card__title">
                        <span class="csheet-card__num">{{ $i + 1 }}</span>
                        {{ $cat['category_name'] ?? ('Categoria '.$cid) }}
                    </h6>
                    <div class="csheet-grid">
                        @foreach(($cat['fields'] ?? []) as $field)
                            @php
                                $key = $field['key'] ?? '';
                                $val = $catValues[$key] ?? null;
                                $display = $formatPrefValue($val);
                            @endphp
                            <div class="csheet-tile{{ $display === '—' ? ' csheet-tile--empty' : '' }}{{ ($field['input_type'] ?? '') === 'textarea' ? ' csheet-tile--wide' : '' }}">
                                <div class="csheet-tile__label">{{ $field['label'] ?? $key }}</div>
                                <div class="csheet-tile__value">
                                    @if(is_array($val) && $val !== [] && ! array_is_list($val) && (isset($val['name']) || isset($val['id'])))
                                        @include('partials.clinical-material-value', ['value' => $val])
                                    @elseif(is_array($val) && $val !== [])
                                        <div class="csheet-chips">
                                            @foreach($val as $chip)
                                                @if(is_scalar($chip) || $chip === null)
                                                    <span class="csheet-chip">{{ $chip }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @elseif(is_bool($val))
                                        <span class="csheet-bool csheet-bool--{{ $val ? 'yes' : 'no' }}">
                                            <i class="ph {{ $val ? 'ph-check' : 'ph-x' }}" aria-hidden="true"></i> {{ $display }}
                                        </span>
                                    @else
                                        <span class="text-break">{{ $display }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>

    @if($s)
        <div class="col-12 col-xl-4">
            <aside class="csheet-card csheet-summary">
                <h6 class="csheet-summary__title"><i class="ph ph-sparkle" aria-hidden="true"></i> Resumo rápido</h6>
                <div class="csheet-summary__item">
                    <span class="csheet-summary__icon"><i class="ph ph-calendar-check" aria-hidden="true"></i></span>
                    <div>
                        <div class="csheet-summary__label">Total de marcações</div>
                        <div class="csheet-summary__value">{{ (int) ($s->totalMarcacoes ?? 0) }}</div>
                    </div>
                </div>
                <div class="csheet-summary__item">
                    <span class="csheet-summary__icon"><i class="ph ph-user" aria-hidden="true"></i></span>
                    <div>
                        <div class="csheet-summary__label">Técnico preferido</div>
                        <div class="csheet-summary__value">{{ $s->tecnicoPreferido ?? '—' }}</div>
                    </div>
                </div>
                <div class="csheet-summary__item">
                    <span class="csheet-summary__icon"><i class="ph ph-scissors" aria-hidden="true"></i></span>
                    <div>
                        <div class="csheet-summary__label">Serviço mais frequente</div>
                        <div class="csheet-summary__value">
                            @if($topServico)
                                {{ $topServico->service_name }} <span class="text-muted fw-normal">({{ $topServico->total }})</span>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
                <div class="csheet-summary__item">
                    <span class="csheet-summary__icon"><i class="ph ph-arrows-clockwise" aria-hidden="true"></i></span>
                    <div>
                        <div class="csheet-summary__label">Intervalo médio entre visitas</div>
                        <div class="csheet-summary__value">{{ $s->intervaloMedioDias !== null ? number_format((float) $s->intervaloMedioDias, 0, ',', ' ').' dias' : '—' }}</div>
                    </div>
                </div>
            </aside>
        </div>
    @endif
</div>
