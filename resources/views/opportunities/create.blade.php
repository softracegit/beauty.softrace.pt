@extends('partials.layouts.main')
@section('title', 'Nova Oportunidade | Beauty CRM')
@section('content')

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Dados da Oportunidade</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('opportunities.store') }}" method="POST">
                    @csrf
                    
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#identificacao" type="button" role="tab">Identificação</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#preferencias" type="button" role="tab">Preferências de Imóvel</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#relacoes" type="button" role="tab">Relações</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Identificação -->
                        <div class="tab-pane fade show active" id="identificacao" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                        @foreach($statuses as $value => $label)
                                            <option value="{{ $value }}" {{ old('status', $prefilledData['status'] ?? 'por_tratar') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="type" class="form-label">Tipo <span class="text-danger">*</span></label>
                                    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                                        @foreach($types as $value => $label)
                                            <option value="{{ $value }}" {{ old('type', $prefilledData['type'] ?? '') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="priority" class="form-label">Prioridade <span class="text-danger">*</span></label>
                                    <select name="priority" id="priority" class="form-select @error('priority') is-invalid @enderror" required>
                                        @foreach($priorities as $value => $label)
                                            <option value="{{ $value }}" {{ old('priority', $prefilledData['priority'] ?? 'medium') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('priority')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Preferências de Imóvel -->
                        <div class="tab-pane fade" id="preferencias" role="tabpanel">
                            @php
                                $preference = $preference ?? null; // Para o partial funcionar
                            @endphp
                            @include('opportunities.partials.preference-form')
                        </div>

                        <!-- Relações -->
                        <div class="tab-pane fade" id="relacoes" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                    <select name="client_id" id="client_id" class="form-select @error('client_id') is-invalid @enderror" required>
                                        <option value="">— Selecionar Cliente —</option>
                                        @foreach($clients as $client)
                                            <option value="{{ $client->id }}" {{ old('client_id', $prefilledData['client_id'] ?? '') == $client->id ? 'selected' : '' }}>{{ $client->name }} ({{ $client->email }})</option>
                                        @endforeach
                                    </select>
                                    @error('client_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="agent_id" class="form-label">Agente Responsável</label>
                                    <select name="agent_id" id="agent_id" class="form-select @error('agent_id') is-invalid @enderror">
                                        <option value="">— Sem Agente —</option>
                                        @foreach($agents as $agent)
                                            <option value="{{ $agent->id }}" {{ old('agent_id', $prefilledData['agent_id'] ?? '') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('agent_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Notas</label>
                                    <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $prefilledData['notes'] ?? '') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <a href="{{ route('opportunities.index') }}" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
    // Múltiplas localizações da Preferência - Linhas dinâmicas
    let oppPreferenceLocationRowIndex = 0;
    const oppPreferenceDistricts = @json($districts);

    // Função para criar uma nova linha de localização da preferência
    function addOppPreferenceLocationRow(locationData = null) {
        const container = document.getElementById('opp-preference-locations-container');
        if (!container) return;
        
        const rowIndex = oppPreferenceLocationRowIndex++;
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 opp-preference-location-row';
        row.dataset.index = rowIndex;
        
        row.innerHTML = `
            <div class="col-12 col-md-3">
                <select name="preference_locations[${rowIndex}][id_district]" class="form-select opp-preference-location-district" data-index="${rowIndex}">
                    <option value="">Selecione...</option>
                    ${oppPreferenceDistricts.map(d => `<option value="${d.id}" ${locationData?.id_district == d.id ? 'selected' : ''}>${d.name}</option>`).join('')}
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="preference_locations[${rowIndex}][id_city]" class="form-select opp-preference-location-city" data-index="${rowIndex}" ${locationData?.id_district ? '' : 'disabled'}>
                    <option value="">Selecione...</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="preference_locations[${rowIndex}][id_parish]" class="form-select opp-preference-location-parish" data-index="${rowIndex}" ${locationData?.id_city ? '' : 'disabled'}>
                    <option value="">Selecione...</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger opp-remove-preference-location-row" disabled>
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        `;
        
        container.appendChild(row);
        
        // Event listeners para esta linha
        const districtSelect = row.querySelector('.opp-preference-location-district');
        const citySelect = row.querySelector('.opp-preference-location-city');
        const parishSelect = row.querySelector('.opp-preference-location-parish');
        const removeBtn = row.querySelector('.opp-remove-preference-location-row');
        
        // Quando distrito muda
        districtSelect.addEventListener('change', function() {
            const districtId = this.value;
            citySelect.innerHTML = '<option value="">Selecione...</option>';
            citySelect.disabled = !districtId;
            parishSelect.innerHTML = '<option value="">Selecione...</option>';
            parishSelect.disabled = true;
            
            if (districtId) {
                fetch(`{{ route('properties.getCities') }}?district_id=${districtId}`)
                    .then(response => response.json())
                    .then(cities => {
                        cities.forEach(city => {
                            const option = document.createElement('option');
                            option.value = city.id;
                            option.textContent = city.name;
                            citySelect.appendChild(option);
                        });
                        citySelect.disabled = false;
                        
                        if (locationData?.id_city) {
                            setTimeout(() => {
                                citySelect.value = locationData.id_city;
                                citySelect.dispatchEvent(new Event('change'));
                            }, 100);
                        }
                    })
                    .catch(error => console.error('Erro ao carregar concelhos:', error));
            }
        });
        
        // Quando concelho muda
        citySelect.addEventListener('change', function() {
            const cityId = this.value;
            parishSelect.innerHTML = '<option value="">Selecione...</option>';
            parishSelect.disabled = !cityId;
            
            if (cityId) {
                fetch(`{{ route('properties.getParishes') }}?city_id=${cityId}`)
                    .then(response => response.json())
                    .then(parishes => {
                        parishes.forEach(parish => {
                            const option = document.createElement('option');
                            option.value = parish.id;
                            option.textContent = parish.name;
                            parishSelect.appendChild(option);
                        });
                        parishSelect.disabled = false;
                        
                        if (locationData?.id_parish) {
                            setTimeout(() => {
                                parishSelect.value = locationData.id_parish;
                            }, 100);
                        }
                    })
                    .catch(error => console.error('Erro ao carregar freguesias:', error));
            }
        });
        
        // Remover linha
        removeBtn.addEventListener('click', function() {
            row.remove();
            updateOppPreferenceRemoveButtons();
        });
        
        // Se houver dados pré-carregados, disparar eventos
        if (locationData?.id_district) {
            setTimeout(() => {
                districtSelect.dispatchEvent(new Event('change'));
            }, 50);
        }
        
        updateOppPreferenceRemoveButtons();
    }
    
    // Atualizar estado dos botões de remover
    function updateOppPreferenceRemoveButtons() {
        const container = document.getElementById('opp-preference-locations-container');
        if (!container) return;
        const rows = container.querySelectorAll('.opp-preference-location-row');
        rows.forEach(row => {
            const removeBtn = row.querySelector('.opp-remove-preference-location-row');
            if (removeBtn) {
                removeBtn.disabled = rows.length === 1;
            }
        });
    }
    
    // Aguardar DOM estar pronto
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('opp-preference-locations-container');
        const addBtn = document.getElementById('opp-add-preference-location-row');
        
        if (!container || !addBtn) {
            console.error('Elementos de preferência não encontrados!');
            return;
        }
        
        // Adicionar primeira linha ao carregar
        addOppPreferenceLocationRow();
        
        // Se houver preferência pré-carregada da lead, carregar localizações
        @if(isset($preference) && $preference && $preference->preferenceLocations->count() > 0)
            const existingLocations = @json($preference->preferenceLocations);
            // Remover primeira linha vazia
            container.innerHTML = '';
            oppPreferenceLocationRowIndex = 0;
            // Adicionar linhas com dados existentes
            existingLocations.forEach(location => {
                addOppPreferenceLocationRow({
                    id_district: location.id_district,
                    id_city: location.id_city,
                    id_parish: location.id_parish
                });
            });
        @else
            // Adicionar primeira linha vazia
            addOppPreferenceLocationRow();
        @endif
        
        // Botão adicionar linha
        addBtn.addEventListener('click', function() {
            addOppPreferenceLocationRow();
        });
    });
</script>
@endsection
