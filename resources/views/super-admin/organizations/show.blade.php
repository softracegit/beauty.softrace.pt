@extends('partials.layouts.super-admin')
@section('title', $organization->name)
@section('content')
  <div class="mb-4">
    <a href="{{ route('super-admin.organizations.index') }}" class="text-decoration-none small">← Organizações</a>
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mt-2">
      <div>
        <h1 class="h3 mb-1">{{ $organization->name }}</h1>
        <p class="text-muted small mb-0">
          Slug: <code>{{ $organization->slug ?? '—' }}</code>
          @if (strtolower((string) $organization->status) === 'active')
            <span class="badge bg-success ms-2">Activa</span>
          @else
            <span class="badge bg-secondary ms-2">Suspensa</span>
          @endif
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('super-admin.organizations.stores.create', $organization) }}" class="btn btn-primary btn-sm">Nova loja</a>
        <a href="{{ route('super-admin.organizations.edit', $organization) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
        @if ($organization->stores->isEmpty())
          <form method="POST" action="{{ route('super-admin.organizations.destroy', $organization) }}" onsubmit="return confirm('Eliminar esta organização?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
          </form>
        @endif
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Lojas</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Nome</th>
              <th>Slug (URL marcação)</th>
              <th>Cidade</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($organization->stores as $store)
              <tr>
                <td>{{ $store->name }}</td>
                <td><code class="small">{{ $store->slug }}</code></td>
                <td>{{ $store->city ?? '—' }}</td>
                <td class="text-end">
                  <a href="{{ route('booking.index', ['store' => $store->slug]) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Marcação</a>
                  <a href="{{ route('super-admin.organizations.stores.edit', [$organization, $store]) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                  <form method="POST" action="{{ route('super-admin.organizations.stores.destroy', [$organization, $store]) }}" class="d-inline" onsubmit="return confirm('Eliminar esta loja? Só é permitido sem dados.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-4">Sem lojas. Crie a primeira loja desta organização.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
