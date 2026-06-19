@extends('partials.layouts.main')
@section('title', 'Editar Cliente | Beauty CRM')

@section('css')
@include('clientes.partials.intl-phone-css')
@endsection

@section('content')

<form action="{{ route('clientes.update', $cliente) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('clientes.partials.form', [
        'cliente' => $cliente,
        'districts' => $districts,
        'cities' => $cities,
        'parishes' => $parishes,
        'selectedDistrict' => $selectedDistrict,
        'selectedCity' => $selectedCity,
        'selectedParish' => $selectedParish,
    ])
</form>

<!-- Delete Client Modal -->
<div class="modal fade" id="deleteClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="ph ph-warning me-2"></i>Eliminar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem a certeza que deseja eliminar <strong>{{ $cliente->name }}</strong>?</p>
                <p class="text-muted mb-0">Esta ação não pode ser desfeita. Todos os dados do cliente serão permanentemente removidos do sistema.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar Cliente</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('profilePreview');
                if (!preview) {
                    return;
                }
                if (preview.tagName === 'DIV') {
                    const img = document.createElement('img');
                    img.id = 'profilePreview';
                    img.className = 'uedit-avatar';
                    img.alt = 'Avatar';
                    img.src = e.target.result;
                    preview.replaceWith(img);
                } else {
                    preview.src = e.target.result;
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    // Selects dependentes - Distrito, Concelho, Freguesia
    (function() {
        const districtSelect = document.getElementById('id_district');
        const citySelect = document.getElementById('id_city');
        const parishSelect = document.getElementById('id_parish');
        if (!districtSelect || !citySelect || !parishSelect) return;

        districtSelect.addEventListener('change', function() {
            const districtId = this.value;
            citySelect.innerHTML = '<option value="">— Selecionar —</option>';
            citySelect.disabled = !districtId;
            parishSelect.innerHTML = '<option value="">— Selecionar —</option>';
            parishSelect.disabled = true;
            if (districtId) {
                fetch(`{{ route('properties.getCities') }}?district_id=${districtId}`)
                    .then(r => r.json())
                    .then(data => {
                        data.forEach(city => {
                            const opt = document.createElement('option');
                            opt.value = city.id;
                            opt.textContent = city.name;
                            citySelect.appendChild(opt);
                        });
                        citySelect.disabled = false;
                    });
            }
        });
        citySelect.addEventListener('change', function() {
            const cityId = this.value;
            parishSelect.innerHTML = '<option value="">— Selecionar —</option>';
            parishSelect.disabled = !cityId;
            if (cityId) {
                fetch(`{{ route('properties.getParishes') }}?city_id=${cityId}`)
                    .then(r => r.json())
                    .then(data => {
                        data.forEach(parish => {
                            const opt = document.createElement('option');
                            opt.value = parish.id;
                            opt.textContent = parish.name;
                            parishSelect.appendChild(opt);
                        });
                        parishSelect.disabled = false;
                    });
            }
        });
    })();
</script>
@include('clientes.partials.intl-phone-init', ['phoneInputId' => 'clientPhone'])
@endsection
