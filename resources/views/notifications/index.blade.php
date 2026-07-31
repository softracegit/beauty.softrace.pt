@extends('partials.layouts.main')
@section('title', 'Notificações | Beauty CRM')

@section('content')
@php
  use App\Support\CrmNotificationPresentation;
@endphp
<div class="uview-grid">
  <div>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title mb-3">Todas as notificações</h5>
        @if($notifications->isEmpty())
          <p class="text-muted mb-0">Não tem notificações.</p>
        @else
          <div class="notification-list notifications-page-list">
            @foreach($notifications as $n)
              @php
                $data = is_array($n->data) ? $n->data : [];
                $title = $data['title'] ?? 'Notificação';
                $body = $data['body'] ?? '';
                $url = $data['url'] ?? route('agenda.index');
                $icon = CrmNotificationPresentation::iconForType($data['type'] ?? null);
                $unread = $n->read_at === null;
              @endphp
              <a href="{{ $url }}"
                 class="notification-item{{ $unread ? ' unread' : '' }}">
                <div class="notification-icon {{ $icon['class'] }}">
                  <i class="bi {{ $icon['icon'] }}"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">{!! strip_tags($title, '<br><strong><b>') !!}</div>
                  @if($body !== '')
                    <div class="notification-text">{!! strip_tags($body, '<br><strong><b>') !!}</div>
                  @endif
                  <div class="notification-time">
                    <i class="bi bi-clock"></i>
                    {{ $n->created_at?->diffForHumans() }}
                  </div>
                </div>
                @if($unread)
                  <span class="notification-dot"></span>
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
