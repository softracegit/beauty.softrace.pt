@extends('partials.layouts.main')
@section('title', 'Editar Lead | Beauty CRM')
@section('content')

<div class="row">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Editar {{ $lead->name }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('leads.update', $lead) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="type" class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Lead::types() as $value => $label)
                                    <option value="{{ $value }}" {{ old('type', $lead->type) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach(\App\Models\Lead::statuses() as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $lead->status) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="scheduled_at" class="form-label">Data/hora agendada</label>
                            <input type="text" name="scheduled_at" id="scheduled_at" class="form-control @error('scheduled_at') is-invalid @enderror" value="{{ old('scheduled_at', $lead->scheduled_at ? $lead->scheduled_at->format('Y-m-d\TH:i') : '') }}" data-crm-datetime autocomplete="off" placeholder="dd/mm/aaaa HH:mm">
                            <small class="text-muted">Se preenchido, será criado um evento na Agenda.</small>
                            @error('scheduled_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="Ex: João Silva" value="{{ old('name', $lead->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" placeholder="email@exemplo.com" value="{{ old('email', $lead->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">Telefone</label>
                            <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="+351 912 345 678" value="{{ old('phone', $lead->phone) }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <hr class="my-3">
                            <h6 class="mb-3">Morada</h6>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Morada</label>
                            <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror" placeholder="Ex: Rua das Flores, 123" value="{{ old('address', $lead->address) }}">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="door" class="form-label">Porta</label>
                            <input type="text" name="door" id="door" class="form-control @error('door') is-invalid @enderror" placeholder="Ex: 1A" value="{{ old('door', $lead->door) }}" maxlength="10">
                            @error('door')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="floor" class="form-label">Andar</label>
                            <input type="text" name="floor" id="floor" class="form-control @error('floor') is-invalid @enderror" placeholder="Ex: 2º" value="{{ old('floor', $lead->floor) }}" maxlength="10">
                            @error('floor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="side" class="form-label">Lado</label>
                            <input type="text" name="side" id="side" class="form-control @error('side') is-invalid @enderror" placeholder="Ex: Esq." value="{{ old('side', $lead->side) }}" maxlength="10">
                            @error('side')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="postal_code" class="form-label">Código Postal</label>
                            <input type="text" name="postal_code" id="postal_code" class="form-control @error('postal_code') is-invalid @enderror" placeholder="Ex: 3800-000" value="{{ old('postal_code', $lead->postal_code) }}" maxlength="20">
                            @error('postal_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="locality" class="form-label">Localidade</label>
                            <input type="text" name="locality" id="locality" class="form-control @error('locality') is-invalid @enderror" placeholder="Ex: Aveiro" value="{{ old('locality', $lead->locality) }}">
                            @error('locality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-2">
                            <label for="id_district" class="form-label">Distrito</label>
                            <select name="id_district" id="id_district" class="form-select @error('id_district') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach($districts as $district)
                                    <option value="{{ $district['id'] }}" {{ old('id_district', $selectedDistrict) == $district['id'] ? 'selected' : '' }}>
                                        {{ $district['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_district')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-2">
                            <label for="id_city" class="form-label">Concelho</label>
                            <select name="id_city" id="id_city" class="form-select @error('id_city') is-invalid @enderror" {{ ($selectedDistrict && $cities->count() > 0) ? '' : 'disabled' }}>
                                <option value="">— Selecionar —</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city['id'] }}" {{ old('id_city', $selectedCity) == $city['id'] ? 'selected' : '' }}>
                                        {{ $city['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_city')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-2">
                            <label for="id_parish" class="form-label">Freguesia</label>
                            <select name="id_parish" id="id_parish" class="form-select @error('id_parish') is-invalid @enderror" {{ ($selectedCity && $parishes->count() > 0) ? '' : 'disabled' }}>
                                <option value="">— Selecionar —</option>
                                @foreach($parishes as $parish)
                                    <option value="{{ $parish['id'] }}" {{ old('id_parish', $selectedParish) == $parish['id'] ? 'selected' : '' }}>
                                        {{ $parish['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_parish')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="origin" class="form-label">Origem <span class="text-danger">*</span></label>
                            <select name="origin" id="origin" class="form-select @error('origin') is-invalid @enderror" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Lead::origins() as $value => $label)
                                    <option value="{{ $value }}" {{ old('origin', $lead->origin) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('origin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="priority" class="form-label">Prioridade <span class="text-danger">*</span></label>
                            <select name="priority" id="priority" class="form-select @error('priority') is-invalid @enderror" required>
                                @foreach(\App\Models\Lead::priorities() as $value => $label)
                                    <option value="{{ $value }}" {{ old('priority', $lead->priority) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('priority')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="property_reference" class="form-label">Referência do Imóvel</label>
                            <input type="text" name="property_reference" id="property_reference" class="form-control @error('property_reference') is-invalid @enderror" placeholder="Ex: REF-001" value="{{ old('property_reference', $lead->property_reference) }}">
                            @error('property_reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="agent_id" class="form-label">Responsável</label>
                            <select name="agent_id" id="agent_id" class="form-select @error('agent_id') is-invalid @enderror">
                                <option value="">— Sem responsável —</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ old('agent_id', $lead->agent_id) == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                            @error('agent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" placeholder="Observações sobre a lead...">{{ old('notes', $lead->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @include('leads.partials.preference-form')

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <a href="{{ route('leads.show', $lead) }}" class="btn btn-light">Cancelar</a>
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
    // Selects dependentes - Distrito, Concelho, Freguesia
    const districtSelect = document.getElementById('id_district');
    const citySelect = document.getElementById('id_city');
    const parishSelect = document.getElementById('id_parish');

    // Quando o distrito muda, carregar concelhos
    districtSelect?.addEventListener('change', function() {
            const districtId = this.value;
            
            // Limpar e desabilitar selects dependentes
            citySelect.innerHTML = '<option value="">— Selecionar —</option>';
            citySelect.disabled = true;
            parishSelect.innerHTML = '<option value="">— Selecionar —</option>';
            parishSelect.disabled = true;

            if (districtId) {
                fetch(`{{ route('properties.getCities') }}?district_id=${districtId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro na resposta da API');
                        }
                        return response.json();
                    })
                    .then(data => {
                        citySelect.innerHTML = '<option value="">— Selecionar —</option>';
                        if (data && Array.isArray(data)) {
                            data.forEach(city => {
                                const option = document.createElement('option');
                                option.value = city.id;
                                option.textContent = city.name;
                                citySelect.appendChild(option);
                            });
                        }
                        citySelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Erro ao carregar concelhos:', error);
                        citySelect.disabled = true;
                    });
        }
    });

    // Quando o concelho muda, carregar freguesias
    citySelect?.addEventListener('change', function() {
            const cityId = this.value;
            
            // Limpar e desabilitar select dependente
            parishSelect.innerHTML = '<option value="">— Selecionar —</option>';
            parishSelect.disabled = true;

            if (cityId) {
                fetch(`{{ route('properties.getParishes') }}?city_id=${cityId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro na resposta da API');
                        }
                        return response.json();
                    })
                    .then(data => {
                        parishSelect.innerHTML = '<option value="">— Selecionar —</option>';
                        if (data && Array.isArray(data)) {
                            data.forEach(parish => {
                                const option = document.createElement('option');
                                option.value = parish.id;
                                option.textContent = parish.name;
                                parishSelect.appendChild(option);
                            });
                        }
                        parishSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Erro ao carregar freguesias:', error);
                        parishSelect.disabled = true;
                    });
        }
    });

    // Carregar valores existentes ao carregar a página
    @if($selectedDistrict)
        const currentDistrictId = {{ $selectedDistrict }};
        if (districtSelect && currentDistrictId) {
            districtSelect.value = currentDistrictId;
            districtSelect.dispatchEvent(new Event('change'));
            setTimeout(() => {
                @if($selectedCity)
                    const currentCityId = {{ $selectedCity }};
                    if (citySelect && currentCityId) {
                        citySelect.value = currentCityId;
                        citySelect.dispatchEvent(new Event('change'));
                        setTimeout(() => {
                            @if($selectedParish)
                                const currentParishId = {{ $selectedParish }};
                                if (parishSelect && currentParishId) {
                                    parishSelect.value = currentParishId;
                                }
                            @endif
                        }, 500);
                    }
                @endif
            }, 500);
        }
    @endif

    // Múltiplas localizações da Preferência - Linhas dinâmicas
    let preferenceLocationRowIndex = 0;
    const preferenceDistricts = @json($districts);
    const existingPreferenceLocations = @json(isset($preference) && $preference->preferenceLocations ? $preference->preferenceLocations : []);

    // Função para criar uma nova linha de localização da preferência
    function addPreferenceLocationRow(locationData = null) {
        const container = document.getElementById('preference-locations-container');
        const rowIndex = preferenceLocationRowIndex++;
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 preference-location-row';
        row.dataset.index = rowIndex;
        
        row.innerHTML = `
            <div class="col-12 col-md-3">
                <select name="preference_locations[${rowIndex}][id_district]" class="form-select preference-location-district" data-index="${rowIndex}">
                    <option value="">Selecione...</option>
                    ${preferenceDistricts.map(d => `<option value="${d.id}" ${locationData?.id_district == d.id ? 'selected' : ''}>${d.name}</option>`).join('')}
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="preference_locations[${rowIndex}][id_city]" class="form-select preference-location-city" data-index="${rowIndex}" ${locationData?.id_district ? '' : 'disabled'}>
                    <option value="">Selecione...</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="preference_locations[${rowIndex}][id_parish]" class="form-select preference-location-parish" data-index="${rowIndex}" ${locationData?.id_city ? '' : 'disabled'}>
                    <option value="">Selecione...</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger remove-preference-location-row" disabled>
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        `;
        
        container.appendChild(row);
        
        // Event listeners para esta linha
        const districtSelect = row.querySelector('.preference-location-district');
        const citySelect = row.querySelector('.preference-location-city');
        const parishSelect = row.querySelector('.preference-location-parish');
        const removeBtn = row.querySelector('.remove-preference-location-row');
        
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
            updatePreferenceRemoveButtons();
        });
        
        // Se houver dados pré-carregados, disparar eventos
        if (locationData?.id_district) {
            setTimeout(() => {
                districtSelect.dispatchEvent(new Event('change'));
            }, 50);
        }
        
        updatePreferenceRemoveButtons();
    }
    
    // Atualizar estado dos botões de remover
    function updatePreferenceRemoveButtons() {
        const container = document.getElementById('preference-locations-container');
        const rows = container.querySelectorAll('.preference-location-row');
        rows.forEach(row => {
            const removeBtn = row.querySelector('.remove-preference-location-row');
            if (removeBtn) {
                removeBtn.disabled = rows.length === 1;
            }
        });
    }
    
    // Aguardar DOM estar pronto
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('preference-locations-container');
        const addBtn = document.getElementById('add-preference-location-row');
        
        if (!container || !addBtn) {
            console.error('Elementos de preferência não encontrados!');
            return;
        }
        
        // Carregar localizações existentes ou adicionar primeira linha vazia
        if (existingPreferenceLocations.length > 0) {
            existingPreferenceLocations.forEach(location => {
                addPreferenceLocationRow({
                    id_district: location.id_district,
                    id_city: location.id_city,
                    id_parish: location.id_parish
                });
            });
        } else {
            addPreferenceLocationRow();
        }
        
        // Botão adicionar linha
        addBtn.addEventListener('click', function() {
            addPreferenceLocationRow();
        });
    });
</script>
@endsection
