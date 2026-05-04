@extends('partials.layouts.super-admin')
@section('title', 'Organizações')
@section('content')
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <h1 class="h3 mb-0">Organizações</h1>
    <a href="{{ route('super-admin.organizations.create') }}" class="btn btn-primary">Nova organização</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Nome</th>
              <th>Slug</th>
              <th>Estado</th>
              <th class="text-end">Lojas</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($organizations as $org)
              <tr>
                <td class="fw-medium">{{ $org->name }}</td>
                <td><code class="small">{{ $org->slug ?? '—' }}</code></td>
                <td>
                  @if (strtolower((string) $org->status) === 'active')
                    <span class="badge bg-success">Activa</span>
                  @else
                    <span class="badge bg-secondary">Suspensa</span>
                  @endif
                </td>
                <td class="text-end">{{ $org->stores_count }}</td>
                <td class="text-end">
                  <a href="{{ route('super-admin.organizations.show', $org) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                  <a href="{{ route('super-admin.organizations.edit', $org) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-4">Sem organizações.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if ($organizations->hasPages())
      <div class="card-footer">{{ $organizations->links() }}</div>
    @endif
  </div>
@endsection
