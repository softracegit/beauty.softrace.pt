@extends('partials.layouts.main')
@section('title', 'Novo Cliente | Beauty CRM')
@section('content')

<form action="{{ route('clientes.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('clientes.partials.form', [
        'districts' => $districts,
        'cities' => $cities ?? collect(),
        'parishes' => $parishes ?? collect(),
    ])
</form>

@endsection

@section('js')
<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
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
@endsection
