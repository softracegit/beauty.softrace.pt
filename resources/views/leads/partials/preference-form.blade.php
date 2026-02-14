<!-- Preferência de Imóvel -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Preferência de Imóvel</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Tipo de Imóvel -->
            <div class="col-12 col-md-6">
                <label for="preference_property_type_id" class="form-label">Tipo de Imóvel</label>
                <select name="preference_property_type_id" id="preference_property_type_id" class="form-select @error('preference_property_type_id') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @foreach($propertyTypes as $type)
                        <option value="{{ $type->id }}" {{ old('preference_property_type_id', $preference->property_type_id ?? null) == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
                @error('preference_property_type_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Tipo de Transação -->
            <div class="col-12 col-md-6">
                <label for="preference_transaction_type_id" class="form-label">Tipo de Transação</label>
                <select name="preference_transaction_type_id" id="preference_transaction_type_id" class="form-select @error('preference_transaction_type_id') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type->id }}" {{ old('preference_transaction_type_id', $preference->transaction_type_id ?? null) == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
                @error('preference_transaction_type_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Condição do Imóvel -->
            <div class="col-12 col-md-6">
                <label for="preference_property_condition_id" class="form-label">Condição</label>
                <select name="preference_property_condition_id" id="preference_property_condition_id" class="form-select @error('preference_property_condition_id') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @foreach($conditions as $condition)
                        <option value="{{ $condition->id }}" {{ old('preference_property_condition_id', $preference->property_condition_id ?? null) == $condition->id ? 'selected' : '' }}>
                            {{ $condition->name }}
                        </option>
                    @endforeach
                </select>
                @error('preference_property_condition_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Preço Mínimo -->
            <div class="col-12 col-md-6">
                <label for="preference_min_price" class="form-label">Preço Mínimo (€)</label>
                <input type="number" name="preference_min_price" id="preference_min_price" class="form-control @error('preference_min_price') is-invalid @enderror" 
                       value="{{ old('preference_min_price', $preference->min_price ?? '') }}" min="0" step="0.01">
                @error('preference_min_price')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Preço Máximo -->
            <div class="col-12 col-md-6">
                <label for="preference_max_price" class="form-label">Preço Máximo (€)</label>
                <input type="number" name="preference_max_price" id="preference_max_price" class="form-control @error('preference_max_price') is-invalid @enderror" 
                       value="{{ old('preference_max_price', $preference->max_price ?? '') }}" min="0" step="0.01">
                @error('preference_max_price')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Tipologias -->
            <div class="col-12">
                <label class="form-label">Tipologias</label>
                <div class="row g-2">
                    @foreach($typologies as $typology)
                        <div class="col-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="preference_typologies[]" 
                                       value="{{ $typology->id }}" id="lead_pref_typology_{{ $typology->id }}"
                                       {{ in_array($typology->id, old('preference_typologies', $preference ? $preference->typologies->pluck('id')->toArray() : [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="lead_pref_typology_{{ $typology->id }}">
                                    {{ $typology->name }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('preference_typologies.*')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <!-- Localização - Múltiplas linhas de Distrito, Concelho, Freguesia -->
            <div class="col-12">
                <label class="form-label">Localização</label>
                <div id="preference-locations-container" class="mb-2">
                    <!-- Linhas serão adicionadas aqui via JavaScript -->
                </div>
                <div class="d-flex">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-preference-location-row" style="display: block !important; visibility: visible !important;">
                        <i class="ph ph-plus me-1"></i> Adicionar Localização
                    </button>
                </div>
                @error('preference_locations.*.id_district')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                @error('preference_locations.*.id_city')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                @error('preference_locations.*.id_parish')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <!-- Características -->
            <div class="col-12">
                <label class="form-label">Características</label>
                <div class="row g-2">
                    @foreach($features as $feature)
                        <div class="col-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="preference_features[]" 
                                       value="{{ $feature->id }}" id="lead_pref_feature_{{ $feature->id }}"
                                       {{ in_array($feature->id, old('preference_features', $preference ? $preference->features->pluck('id')->toArray() : [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="lead_pref_feature_{{ $feature->id }}">
                                    {{ $feature->name }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('preference_features.*')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <!-- Notas da Preferência -->
            <div class="col-12">
                <label for="preference_notes" class="form-label">Notas sobre a Preferência</label>
                <textarea name="preference_notes" id="preference_notes" class="form-control @error('preference_notes') is-invalid @enderror" 
                          rows="3" placeholder="Observações sobre a preferência de imóvel...">{{ old('preference_notes', $preference->notes ?? null) }}</textarea>
                @error('preference_notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>
