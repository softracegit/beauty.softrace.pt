@extends('partials.layouts.main')
@section('title', 'Editar Imóvel | Imobiliária')
@section('page-heading-title', 'Editar Imóvel')
@section('page-heading-sub-title', 'Imóveis')
@section('content')

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Dados do Imóvel</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('properties.update', $property) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#identificacao" type="button" role="tab">Identificação</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#negocio" type="button" role="tab">Negócio</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#localizacao" type="button" role="tab">Localização</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#caracteristicas" type="button" role="tab">Características</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#detalhes" type="button" role="tab">Detalhes</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Identificação -->
                        <div class="tab-pane fade show active" id="identificacao" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                        @foreach($statuses as $value => $label)
                                            <option value="{{ $value }}" {{ old('status', $property->status) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="property_condition_id" class="form-label">Condição</label>
                                    <select name="property_condition_id" id="property_condition_id" class="form-select @error('property_condition_id') is-invalid @enderror">
                                        <option value="">— Selecionar —</option>
                                        @foreach($propertyConditions as $condition)
                                            <option value="{{ $condition->id }}" {{ old('property_condition_id', $property->property_condition_id) == $condition->id ? 'selected' : '' }}>{{ $condition->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('property_condition_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                </div>
                                <div class="col-12">
                                    <label for="description" class="form-label">Descrição</label>
                                    <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $property->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Negócio -->
                        <div class="tab-pane fade" id="negocio" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="transaction_type_id" class="form-label">Negócio <span class="text-danger">*</span></label>
                                    <select name="transaction_type_id" id="transaction_type_id" class="form-select @error('transaction_type_id') is-invalid @enderror" required>
                                        <option value="">— Selecionar —</option>
                                        @foreach($transactionTypes as $id => $name)
                                            <option value="{{ $id }}" {{ old('transaction_type_id', $property->transaction_type_id) == $id ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('transaction_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="property_type_id" class="form-label">Tipo de Imóvel</label>
                                    <select name="property_type_id" id="property_type_id" class="form-select @error('property_type_id') is-invalid @enderror">
                                        <option value="">— Selecionar —</option>
                                        @foreach($propertyTypes as $propertyType)
                                            <option value="{{ $propertyType->id }}" {{ old('property_type_id', $property->property_type_id) == $propertyType->id ? 'selected' : '' }}>{{ $propertyType->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('property_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="price" class="form-label">Preço (€)</label>
                                    <input type="number" name="price" id="price" step="0.01" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $property->price) }}">
                                    @error('price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="condominium_fee" class="form-label">Condomínio (€)</label>
                                    <input type="number" name="condominium_fee" id="condominium_fee" step="0.01" class="form-control @error('condominium_fee') is-invalid @enderror" value="{{ old('condominium_fee', $property->condominium_fee) }}">
                                    @error('condominium_fee')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="imi_value" class="form-label">IMI (€)</label>
                                    <input type="number" name="imi_value" id="imi_value" step="0.01" class="form-control @error('imi_value') is-invalid @enderror" value="{{ old('imi_value', $property->imi_value) }}">
                                    @error('imi_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="commission_percentage" class="form-label">Comissão (%)</label>
                                    <input type="number" name="commission_percentage" id="commission_percentage" step="0.01" class="form-control @error('commission_percentage') is-invalid @enderror" value="{{ old('commission_percentage', $property->commission_percentage) }}">
                                    @error('commission_percentage')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="commission_value" class="form-label">Comissão (€)</label>
                                    <input type="number" name="commission_value" id="commission_value" step="0.01" class="form-control @error('commission_value') is-invalid @enderror" value="{{ old('commission_value', $property->commission_value) }}" readonly>
                                    <small class="text-muted">Calculado automaticamente</small>
                                    @error('commission_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="agent_id" class="form-label">Angariador</label>
                                    <select name="agent_id" id="agent_id" class="form-select @error('agent_id') is-invalid @enderror">
                                        <option value="">— Selecionar —</option>
                                        @foreach($agents ?? [] as $agent)
                                            <option value="{{ $agent->id }}" {{ old('agent_id', $property->agent_id) == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Agente que angariou o imóvel</small>
                                    @error('agent_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Localização -->
                        <div class="tab-pane fade" id="localizacao" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="country" class="form-label">País</label>
                                    <input type="text" name="country" id="country" class="form-control @error('country') is-invalid @enderror" value="{{ old('country', $property->country) }}">
                                    @error('country')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="address" class="form-label">Morada</label>
                                    <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror" placeholder="Ex: Rua das Flores, 123" value="{{ old('address', $property->address) }}">
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="door" class="form-label">Porta</label>
                                    <input type="text" name="door" id="door" class="form-control @error('door') is-invalid @enderror" placeholder="Ex: 1A" value="{{ old('door', $property->door) }}" maxlength="10">
                                    @error('door')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="floor_address" class="form-label">Andar</label>
                                    <input type="text" name="floor_address" id="floor_address" class="form-control @error('floor_address') is-invalid @enderror" placeholder="Ex: 2º" value="{{ old('floor_address', $property->floor_address) }}" maxlength="10">
                                    @error('floor_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="side" class="form-label">Lado</label>
                                    <input type="text" name="side" id="side" class="form-control @error('side') is-invalid @enderror" placeholder="Ex: Esq." value="{{ old('side', $property->side) }}" maxlength="10">
                                    @error('side')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="postal_code" class="form-label">Código Postal</label>
                                    <input type="text" name="postal_code" id="postal_code" class="form-control @error('postal_code') is-invalid @enderror" placeholder="Ex: 3800-000" value="{{ old('postal_code', $property->postal_code) }}" maxlength="20">
                                    @error('postal_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="locality" class="form-label">Localidade</label>
                                    <input type="text" name="locality" id="locality" class="form-control @error('locality') is-invalid @enderror" placeholder="Ex: Aveiro" value="{{ old('locality', $property->locality) }}">
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
                                <div class="col-12 col-md-3">
                                    <label for="latitude" class="form-label">Latitude</label>
                                    <input type="number" name="latitude" id="latitude" step="0.00000001" class="form-control @error('latitude') is-invalid @enderror" value="{{ old('latitude', $property->latitude) }}">
                                    @error('latitude')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="longitude" class="form-label">Longitude</label>
                                    <input type="number" name="longitude" id="longitude" step="0.00000001" class="form-control @error('longitude') is-invalid @enderror" value="{{ old('longitude', $property->longitude) }}">
                                    @error('longitude')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Características -->
                        <div class="tab-pane fade" id="caracteristicas" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="property_typology_id" class="form-label">Tipologia</label>
                                    <select name="property_typology_id" id="property_typology_id" class="form-select @error('property_typology_id') is-invalid @enderror">
                                        <option value="">— Selecionar —</option>
                                        @foreach($propertyTypologies as $typology)
                                            <option value="{{ $typology->id }}" {{ old('property_typology_id', $property->property_typology_id) == $typology->id ? 'selected' : '' }}>{{ $typology->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('property_typology_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="energy_certificate" class="form-label">Certificado Energético</label>
                                    <select name="energy_certificate" id="energy_certificate" class="form-select @error('energy_certificate') is-invalid @enderror">
                                        <option value="">— Selecionar —</option>
                                        @foreach($energyCertificates as $value => $label)
                                            <option value="{{ $value }}" {{ old('energy_certificate', $property->energy_certificate) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('energy_certificate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="area_total" class="form-label">Área Total (m²)</label>
                                    <input type="number" name="area_total" id="area_total" step="0.01" class="form-control @error('area_total') is-invalid @enderror" value="{{ old('area_total', $property->area_total) }}">
                                    @error('area_total')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="area_private" class="form-label">Área Privada (m²)</label>
                                    <input type="number" name="area_private" id="area_private" step="0.01" class="form-control @error('area_private') is-invalid @enderror" value="{{ old('area_private', $property->area_private) }}">
                                    @error('area_private')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="bathrooms" class="form-label">Casas de Banho</label>
                                    <input type="number" name="bathrooms" id="bathrooms" min="0" class="form-control @error('bathrooms') is-invalid @enderror" value="{{ old('bathrooms', $property->bathrooms) }}">
                                    @error('bathrooms')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="garages" class="form-label">Garagens</label>
                                    <input type="number" name="garages" id="garages" min="0" class="form-control @error('garages') is-invalid @enderror" value="{{ old('garages', $property->garages) }}">
                                    @error('garages')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="parking_spaces" class="form-label">Lugares de Estacionamento</label>
                                    <input type="number" name="parking_spaces" id="parking_spaces" min="0" class="form-control @error('parking_spaces') is-invalid @enderror" value="{{ old('parking_spaces', $property->parking_spaces) }}">
                                    @error('parking_spaces')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="floor" class="form-label">Andar do Imóvel</label>
                                    <input type="number" name="floor" id="floor" class="form-control @error('floor') is-invalid @enderror" value="{{ old('floor', $property->floor) }}">
                                    @error('floor')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="year_built" class="form-label">Ano de Construção</label>
                                    <input type="number" name="year_built" id="year_built" min="1000" max="{{ date('Y') + 1 }}" class="form-control @error('year_built') is-invalid @enderror" value="{{ old('year_built', $property->year_built) }}">
                                    @error('year_built')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <hr class="my-3">
                                    <h6 class="mb-3">Características</h6>
                                    <div class="row">
                                        @foreach($propertyFeatures as $feature)
                                            <div class="col-12 col-md-3 mb-2">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="features[]" id="feature_{{ $feature->id }}" value="{{ $feature->id }}" {{ in_array($feature->id, old('features', $property->features->pluck('id')->toArray())) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="feature_{{ $feature->id }}">
                                                        @if($feature->icon)
                                                            <i class="{{ $feature->icon }} me-1"></i>
                                                        @endif
                                                        {{ $feature->name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('features')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Detalhes -->
                        <div class="tab-pane fade" id="detalhes" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="orientation" class="form-label">Orientação</label>
                                    <select name="orientation" id="orientation" class="form-select @error('orientation') is-invalid @enderror">
                                        <option value="">— Selecionar —</option>
                                        @foreach($orientations as $value => $label)
                                            <option value="{{ $value }}" {{ old('orientation', $property->orientation) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('orientation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <a href="{{ route('properties.show', $property) }}" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk me-1"></i> Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@section('js')
<script>
    // Calcular comissão automaticamente
    document.getElementById('commission_percentage')?.addEventListener('input', function() {
        const price = parseFloat(document.getElementById('price').value) || 0;
        const percentage = parseFloat(this.value) || 0;
        const commissionValue = (price * percentage) / 100;
        document.getElementById('commission_value').value = commissionValue.toFixed(2);
    });

    document.getElementById('price')?.addEventListener('input', function() {
        const price = parseFloat(this.value) || 0;
        const percentage = parseFloat(document.getElementById('commission_percentage').value) || 0;
        const commissionValue = (price * percentage) / 100;
        document.getElementById('commission_value').value = commissionValue.toFixed(2);
    });

    // Selects dependentes - Distrito, Concelho, Freguesia
    const districtSelect = document.getElementById('id_district');
    const citySelect = document.getElementById('id_city');
    const parishSelect = document.getElementById('id_parish');

    // Quando o distrito muda
    districtSelect?.addEventListener('change', function() {
        const districtId = this.value;

        // Limpar e desabilitar concelho e freguesia
        citySelect.innerHTML = '<option value="">— Selecionar Concelho —</option>';
        citySelect.disabled = !districtId;
        
        parishSelect.innerHTML = '<option value="">— Selecionar Freguesia —</option>';
        parishSelect.disabled = true;

        // Se um distrito foi selecionado, buscar concelhos
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
                })
                .catch(error => {
                    console.error('Erro ao carregar concelhos:', error);
                });
        }
    });

    // Quando o concelho muda
    citySelect?.addEventListener('change', function() {
        const cityId = this.value;

        // Limpar e desabilitar freguesia
        parishSelect.innerHTML = '<option value="">— Selecionar Freguesia —</option>';
        parishSelect.disabled = !cityId;

        // Se um concelho foi selecionado, buscar freguesias
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
                })
                .catch(error => {
                    console.error('Erro ao carregar freguesias:', error);
                });
        }
    });

    // Carregar valores existentes ao carregar a página
    @if($selectedDistrict)
        const currentDistrictId = {{ $selectedDistrict }};
        if (currentDistrictId && districtSelect) {
            districtSelect.value = currentDistrictId;
            districtSelect.dispatchEvent(new Event('change'));
            setTimeout(() => {
                const currentCityId = {{ $selectedCity ?? 0 }};
                if (currentCityId && citySelect) {
                    citySelect.value = currentCityId;
                    citySelect.dispatchEvent(new Event('change'));
                    setTimeout(() => {
                        const currentParishId = {{ $selectedParish ?? 0 }};
                        if (currentParishId && parishSelect) {
                            parishSelect.value = currentParishId;
                        }
                    }, 500);
                }
            }, 500);
        }
    @endif

    // Se houver valores antigos (old), carregar os selects
    @if(old('id_district'))
        const oldDistrictId = {{ old('id_district') }};
        if (oldDistrictId && districtSelect) {
            districtSelect.value = oldDistrictId;
            districtSelect.dispatchEvent(new Event('change'));
            setTimeout(() => {
                const oldCityId = {{ old('id_city', 0) }};
                if (oldCityId && citySelect) {
                    citySelect.value = oldCityId;
                    citySelect.dispatchEvent(new Event('change'));
                    setTimeout(() => {
                        const oldParishId = {{ old('id_parish', 0) }};
                        if (oldParishId && parishSelect) {
                            parishSelect.value = oldParishId;
                        }
                    }, 500);
                }
            }, 500);
        }
    @endif
</script>
@endsection

@endsection
