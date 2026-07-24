@extends('partials.layouts.main')
@section('title', 'Notificações | Beauty CRM')

@section('content')
<div class="uview-grid">
  <div>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title mb-3">Todas as notificações</h5>
        @if($notifications->isEmpty())
          <p class="text-muted mb-0">Não tem notificações.</p>
        @else
          <div class="list-group list-group-flush">
            @foreach($notifications as $n)
              @php
                $data = is_array($n->data) ? $n->data : [];
                $title = $data['title'] ?? 'Notificação';
                $body = $data['body'] ?? '';
                $url = $data['url'] ?? route('agenda.index');
              @endphp
              <a href="{{ $url }}"
                 class="list-group-item list-group-item-action d-flex justify-content-between align-items-start {{ $n->read_at ? '' : 'fw-semibold' }}">
                <div class="me-3">
                  <div>{!! strip_tags($title, '<br><strong><b>') !!}</div>
                  @if($body !== '')
                    <small class="text-muted d-block">{!! strip_tags($body, '<br><strong><b>') !!}</small>
                  @endif
                  <small class="text-muted">{{ $n->created_at?->diffForHumans() }}</small>
                </div>
                @if($n->read_at === null)
                  <span class="badge bg-primary rounded-pill">Nova</span>
                @endif
              </a>
            @endforeach
          </div>
          @if($notifications->hasPages())
            <div class="mt-3 d-flex justify-content-center">
              {{ $notifications->links() }}
            </div>
          @endif
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
