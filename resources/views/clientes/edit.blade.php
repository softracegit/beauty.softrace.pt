@extends('partials.layouts.main')
@section('title', 'Editar Cliente | Imobiliária')
@section('page-heading-title', 'Editar Cliente')
@section('page-heading-sub-title', 'Real Estate')
@section('content')

<div class="row">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Editar {{ $cliente->name }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('clientes.update', $cliente) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="name" class="form-label">Nome completo <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="Ex: Maria Silva" value="{{ old('name', $cliente->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" placeholder="email@exemplo.com" value="{{ old('email', $cliente->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">Telefone</label>
                            <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="+351 912 345 678" value="{{ old('phone', $cliente->phone) }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="nif" class="form-label">NIF</label>
                            <input type="text" name="nif" id="nif" class="form-control @error('nif') is-invalid @enderror" placeholder="123456789" value="{{ old('nif', $cliente->nif) }}" maxlength="20">
                            @error('nif')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="birth_date" class="form-label">Data de Nascimento</label>
                            <input type="date" name="birth_date" id="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', $cliente->birth_date?->format('Y-m-d')) }}" max="{{ date('Y-m-d', strtotime('-18 years')) }}">
                            @error('birth_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gender" class="form-label">Género</label>
                            <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Client::genders() as $value => $label)
                                    <option value="{{ $value }}" {{ old('gender', $cliente->gender) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="nationality" class="form-label">Nacionalidade</label>
                            <input type="text" name="nationality" id="nationality" class="form-control @error('nationality') is-invalid @enderror" placeholder="Ex: Portuguesa" value="{{ old('nationality', $cliente->nationality) }}">
                            @error('nationality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="marital_status" class="form-label">Estado Civil</label>
                            <select name="marital_status" id="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Client::maritalStatuses() as $value => $label)
                                    <option value="{{ $value }}" {{ old('marital_status', $cliente->marital_status) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('marital_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <hr class="my-3">
                            <h6 class="mb-3">Morada</h6>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Morada</label>
                            <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror" placeholder="Ex: Rua das Flores, 123" value="{{ old('address', $cliente->address) }}">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="door" class="form-label">Porta</label>
                            <input type="text" name="door" id="door" class="form-control @error('door') is-invalid @enderror" placeholder="Ex: 1A" value="{{ old('door', $cliente->door) }}" maxlength="10">
                            @error('door')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="floor" class="form-label">Andar</label>
                            <input type="text" name="floor" id="floor" class="form-control @error('floor') is-invalid @enderror" placeholder="Ex: 2º" value="{{ old('floor', $cliente->floor) }}" maxlength="10">
                            @error('floor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="side" class="form-label">Lado</label>
                            <input type="text" name="side" id="side" class="form-control @error('side') is-invalid @enderror" placeholder="Ex: Esq." value="{{ old('side', $cliente->side) }}" maxlength="10">
                            @error('side')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="postal_code" class="form-label">Código Postal</label>
                            <input type="text" name="postal_code" id="postal_code" class="form-control @error('postal_code') is-invalid @enderror" placeholder="Ex: 3800-000" value="{{ old('postal_code', $cliente->postal_code) }}" maxlength="20">
                            @error('postal_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="locality" class="form-label">Localidade</label>
                            <input type="text" name="locality" id="locality" class="form-control @error('locality') is-invalid @enderror" placeholder="Ex: Aveiro" value="{{ old('locality', $cliente->locality) }}">
                            @error('locality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-2">
                            <label for="id_district" class="form-label">Distrito</label>
                            <select name="id_district" id="id_district" class="form-select @error('id_district') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach($districts as $district)
                                    <option value="{{ $district['id'] }}" {{ old('id_district', $cliente->id_district) == $district['id'] ? 'selected' : '' }}>
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
                                    <option value="{{ $city['id'] }}" {{ old('id_city', $cliente->id_city) == $city['id'] ? 'selected' : '' }}>
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
                                    <option value="{{ $parish['id'] }}" {{ old('id_parish', $cliente->id_parish) == $parish['id'] ? 'selected' : '' }}>
                                        {{ $parish['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_parish')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach(\App\Models\Client::statusLabels() as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $cliente->status) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" placeholder="Observações sobre o cliente...">{{ old('notes', $cliente->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <a href="{{ route('clientes.index') }}" class="btn btn-light">Cancelar</a>
                        <a href="{{ route('clientes.show', $cliente) }}" class="btn btn-outline-primary">Ver</a>
                        <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk me-1"></i> Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    // Selects dependentes do Cliente - Distrito, Concelho, Freguesia
    const districtSelect = document.getElementById('id_district');
    const citySelect = document.getElementById('id_city');
    const parishSelect = document.getElementById('id_parish');

    // Quando o distrito muda, carregar concelhos
    districtSelect?.addEventListener('change', function() {
        const districtId = this.value;
        
        // Limpar e desabilitar selects dependentes
        citySelect.innerHTML = '<option value="">— Selecionar —</option>';
        citySelect.disabled = !districtId;
        parishSelect.innerHTML = '<option value="">— Selecionar —</option>';
        parishSelect.disabled = true;

        if (districtId) {
            fetch(`{{ route('properties.getCities') }}?district_id=${districtId}`)
                .then(response => response.json())
                .then(data => {
                    citySelect.innerHTML = '<option value="">— Selecionar —</option>';
                    data.forEach(city => {
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

    // Quando o concelho muda, carregar freguesias
    citySelect?.addEventListener('change', function() {
        const cityId = this.value;
        
        // Limpar e desabilitar select dependente
        parishSelect.innerHTML = '<option value="">— Selecionar —</option>';
        parishSelect.disabled = !cityId;

        if (cityId) {
            fetch(`{{ route('properties.getParishes') }}?city_id=${cityId}`)
                .then(response => response.json())
                .then(data => {
                    parishSelect.innerHTML = '<option value="">— Selecionar —</option>';
                    data.forEach(parish => {
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
</script>
@endsection
