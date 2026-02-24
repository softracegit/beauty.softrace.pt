@extends('partials.layouts.main')
@section('title', 'Editar Membro | Beauty CRM')
@section('content')

<form action="{{ route('equipa.update', $agente) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    
    <div class="uedit-grid">
        <!-- Left Sidebar -->
        <div class="uedit-sidebar">
            <!-- Profile Image -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-avatar-wrap">
                        @php
                            $avatarNum = ($agente->id % 9) + 1;
                            $avatarSrc = $agente->avatar 
                                ? asset('storage/' . $agente->avatar)
                                : asset("template/img/avatars/avatar-{$avatarNum}.webp");
                        @endphp
                        <img src="{{ $avatarSrc }}" alt="Profile" class="uedit-avatar" id="profilePreview">
                        <div class="mt-3">
                            <input type="file" name="avatar" class="form-control" id="profileImage" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <div class="small text-muted mt-2 text-center">
                            Permitido: JPG, PNG, WEBP. Máx. 2MB
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Status -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Estado da Conta</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Estado <span class="text-danger">*</span></label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach(\App\Models\Agent::statusLabels() as $value => $label)
                                <option value="{{ $value }}" {{ old('status', $agente->status) == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="uedit-danger-zone">
                <div class="uedit-danger-header">
                    <i class="ph ph-warning me-1"></i> Zona de Perigo
                </div>
                <div class="uedit-danger-body">
                    <p class="text-muted small mb-3">Uma vez eliminado um membro, não há volta atrás. Por favor, tenha a certeza.</p>
                    <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteAgentModal">
                        <i class="ph ph-trash me-1"></i> Eliminar Membro
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Main Area -->
        <div>
            <!-- Dados gerais -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-section">
                        <div class="uedit-section-title">Dados gerais</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $agente->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $agente->user->email ?? '') }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Membro <span class="text-danger">*</span></label>
                                <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                                    @foreach(\App\Models\User::roles() as $value => $label)
                                        <option value="{{ $value }}" {{ old('role', $agente->user->role ?? 'prestador') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Especialização</label>
                                <input type="text" name="specialization" class="form-control @error('specialization') is-invalid @enderror" value="{{ old('specialization', $agente->specialization) }}" placeholder="Ex: Manicure, Barbeiro, Tratamento Pets">
                                @error('specialization')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Taxa de Comissão (%)</label>
                                <input type="number" name="commission_rate" class="form-control @error('commission_rate') is-invalid @enderror" value="{{ old('commission_rate', $agente->commission_rate) }}" placeholder="Ex: 5.00" step="0.01" min="0" max="100">
                                @error('commission_rate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dados Pessoais -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-section">
                        <div class="uedit-section-title">Dados Pessoais</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $agente->phone) }}" placeholder="+351 912 345 678">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIF</label>
                                <input type="text" name="nif" class="form-control @error('nif') is-invalid @enderror" value="{{ old('nif', $agente->nif) }}" placeholder="123456789" maxlength="20">
                                @error('nif')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', $agente->birth_date?->format('Y-m-d')) }}" max="{{ date('Y-m-d', strtotime('-18 years')) }}">
                                @error('birth_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Género</label>
                                <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                                    <option value="">— Selecionar —</option>
                                    @foreach(\App\Models\Agent::genders() as $value => $label)
                                        <option value="{{ $value }}" {{ old('gender', $agente->gender) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nacionalidade</label>
                                <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ old('nationality', $agente->nationality) }}" placeholder="Ex: Portuguesa">
                                @error('nationality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado Civil</label>
                                <select name="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                                    <option value="">— Selecionar —</option>
                                    @foreach(\App\Models\Agent::maritalStatuses() as $value => $label)
                                        <option value="{{ $value }}" {{ old('marital_status', $agente->marital_status) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('marital_status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Morada -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-section">
                        <div class="uedit-section-title">Morada</div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Morada</label>
                                <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $agente->address) }}" placeholder="Ex: Rua das Flores, 123">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Porta</label>
                                <input type="text" name="door" class="form-control @error('door') is-invalid @enderror" value="{{ old('door', $agente->door) }}" placeholder="Ex: 1A" maxlength="10">
                                @error('door')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Andar</label>
                                <input type="text" name="floor" class="form-control @error('floor') is-invalid @enderror" value="{{ old('floor', $agente->floor) }}" placeholder="Ex: 2º" maxlength="10">
                                @error('floor')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Lado</label>
                                <input type="text" name="side" class="form-control @error('side') is-invalid @enderror" value="{{ old('side', $agente->side) }}" placeholder="Ex: Esq." maxlength="10">
                                @error('side')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Código Postal</label>
                                <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $agente->postal_code) }}" placeholder="Ex: 3800-000" maxlength="20">
                                @error('postal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Localidade</label>
                                <input type="text" name="locality" class="form-control @error('locality') is-invalid @enderror" value="{{ old('locality', $agente->locality) }}" placeholder="Ex: Aveiro">
                                @error('locality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-section">
                        <div class="uedit-section-title">Alterar Password</div>
                        <div class="alert alert-info d-flex align-items-center mb-3">
                            <i class="ph ph-info me-2"></i>
                            <div>Deixe em branco para manter a password atual.</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nova Password</label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Introduza a nova password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirmar Password</label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Confirme a nova password">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="uedit-form-actions">
                <a href="{{ route('equipa.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-check me-1"></i> Guardar Alterações
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Delete Agent Modal -->
<div class="modal fade" id="deleteAgentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="ph ph-warning me-2"></i>Eliminar Membro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem a certeza que deseja eliminar <strong>{{ $agente->name }}</strong>?</p>
                <p class="text-muted mb-0">Esta ação não pode ser desfeita. Todos os dados do membro serão permanentemente removidos do sistema.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('equipa.destroy', $agente) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar Membro</button>
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
                document.getElementById('profilePreview').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection
