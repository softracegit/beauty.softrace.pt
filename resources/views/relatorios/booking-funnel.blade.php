@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Funil Booking').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
@endsection

@section('content')
  @php
      use App\Models\BookingAuthCode;
      use App\Models\BookingSlotHold;
      use App\Models\User;
      use App\Services\BookingFunnelReportService;
      use App\Support\DateTimeDisplay;
      use App\Support\PhoneDisplay;

      $counts = $summaryCounts ?? ['sms_pending' => 0, 'otp_failed' => 0, 'accounts_without_booking' => 0, 'expired_holds' => 0];
      $tabs = [
          BookingFunnelReportService::TAB_SMS_PENDING => [
              'label' => 'OTP SMS sem resposta',
              'hint' => 'SMS de acesso enviado; código ainda não validado.',
              'count' => (int) ($counts['sms_pending'] ?? 0),
          ],
          BookingFunnelReportService::TAB_OTP_FAILED => [
              'label' => 'OTP com erros',
              'hint' => 'Código introduzido incorretamente e sessão não concluída.',
              'count' => (int) ($counts['otp_failed'] ?? 0),
          ],
          BookingFunnelReportService::TAB_ACCOUNTS => [
              'label' => 'Contas online sem marcação',
              'hint' => 'Utilizadores com role cliente que não têm marcações criadas nem agendadas (hora de início) após a criação da conta online. Marcações Zappy anteriores à conta não contam.',
              'count' => (int) ($counts['accounts_without_booking'] ?? 0),
          ],
          BookingFunnelReportService::TAB_HOLDS => [
              'label' => 'Horários abandonados',
              'hint' => 'Reserva temporária de horário expirada ou libertada sem conclusão.',
              'count' => (int) ($counts['expired_holds'] ?? 0),
          ],
      ];
  @endphp

  <div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
      <h2 class="dash-welcome-title mb-0">Funil Booking</h2>
      <p class="text-muted small mb-0 mt-1">
        Abandonos e bloqueios no fluxo público de marcação (OTP, contas online e horários temporários). Histórico completo.
      </p>
    </div>
  </div>

  <div class="dash-kpi-strip mb-4">
  @foreach($tabs as $tabKey => $tabMeta)
    @php
        $kpiIconClass = match ($tabKey) {
            BookingFunnelReportService::TAB_SMS_PENDING => 'warning',
            BookingFunnelReportService::TAB_OTP_FAILED => 'danger',
            BookingFunnelReportService::TAB_ACCOUNTS => 'info',
            default => 'primary',
        };
        $kpiPhIcon = match ($tabKey) {
            BookingFunnelReportService::TAB_HOLDS => 'clock-countdown',
            BookingFunnelReportService::TAB_ACCOUNTS => 'user-circle',
            default => 'chat-circle-text',
        };
    @endphp
    <div class="dash-kpi">
      <div class="dash-kpi-icon {{ $kpiIconClass }}">
        <i class="ph-duotone ph-{{ $kpiPhIcon }}"></i>
      </div>
      <div class="dash-kpi-body">
        <div class="dash-kpi-value">{{ number_format($tabMeta['count'], 0, ',', '.') }}</div>
        <div class="dash-kpi-label">{{ $tabMeta['label'] }}</div>
      </div>
    </div>
  @endforeach
  </div>

  <ul class="nav nav-tabs nav-tabs-bordered crm-segment-tabs mb-4" id="bookingFunnelTabs" role="tablist">
    @foreach($tabs as $tabKey => $tabMeta)
      <li class="nav-item" role="presentation">
        <a
          class="nav-link {{ ($activeTab ?? '') === $tabKey ? 'active' : '' }}"
          id="booking-funnel-tab-{{ $tabKey }}"
          href="{{ route('relatorios.booking-funnel', ['tab' => $tabKey]) }}"
          role="tab"
          aria-controls="booking-funnel-pane-{{ $tabKey }}"
          aria-selected="{{ ($activeTab ?? '') === $tabKey ? 'true' : 'false' }}"
        >{{ $tabMeta['label'] }} <span class="badge rounded-pill ms-1 {{ ($activeTab ?? '') === $tabKey ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ number_format($tabMeta['count'], 0, ',', '.') }}</span></a>
      </li>
    @endforeach
  </ul>

  <div class="tab-content mb-4" id="bookingFunnelTabContent">
    <div
      class="tab-pane fade show active"
      id="booking-funnel-pane-{{ $activeTab }}"
      role="tabpanel"
      aria-labelledby="booking-funnel-tab-{{ $activeTab }}"
    >
  <p class="text-muted small mb-3">{{ $tabs[$activeTab]['hint'] ?? '' }}</p>

  @if($rows->count() > 0)
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead>
          @if(in_array($activeTab, [BookingFunnelReportService::TAB_SMS_PENDING, BookingFunnelReportService::TAB_OTP_FAILED], true))
            <tr>
              <th>Pedido em</th>
              <th>Canal</th>
              <th>Contacto</th>
              <th>Cliente CRM</th>
              <th>Tentativas</th>
              <th>Expira</th>
              <th>Estado</th>
            </tr>
          @elseif($activeTab === BookingFunnelReportService::TAB_ACCOUNTS)
            <tr>
              <th>Conta online criada</th>
              <th>Ficha CRM criada</th>
              <th>Nome</th>
              <th>Email</th>
              <th>Telemóvel</th>
              <th>Ficha CRM</th>
            </tr>
          @else
            <tr>
              <th>Reserva em</th>
              <th>Data / hora</th>
              <th>Técnica</th>
              <th>Cliente (sessão)</th>
              <th>Expirou</th>
              <th>Motivo</th>
            </tr>
          @endif
        </thead>
        <tbody>
          @foreach($rows as $row)
            @if($row instanceof BookingAuthCode)
              @php
                  $identifier = trim((string) $row->email);
                  $isPhone = ! str_contains($identifier, '@');
                  $contactDisplay = $isPhone ? (PhoneDisplay::formatInternational($identifier) ?? $identifier) : $identifier;
                  $clientMatch = $authCodeClients[$row->id] ?? null;
              @endphp
              <tr>
                <td class="text-nowrap">{{ DateTimeDisplay::formatInstant($row->created_at, current_store_id()) }}</td>
                <td>{{ $funnelService->authCodeChannelLabel($row) }}</td>
                <td class="text-nowrap">{{ $contactDisplay }}</td>
                <td>
                  @if($clientMatch)
                    <a href="{{ route('clientes.show', $clientMatch) }}">{{ $clientMatch->name }}</a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ (int) $row->attempts }}</td>
                <td class="text-nowrap">{{ $row->expires_at ? DateTimeDisplay::formatInstant($row->expires_at, current_store_id()) : '—' }}</td>
                <td><span class="badge text-bg-light border">{{ $funnelService->authCodeStatusLabel($row) }}</span></td>
              </tr>
            @elseif($row instanceof User)
              @php
                  $client = $row->client;
                  $preExistingCrm = $funnelService->bookingUserHadPreExistingCrmClient($row);
              @endphp
              <tr>
                <td class="text-nowrap">{{ DateTimeDisplay::formatInstant($row->created_at, current_store_id()) }}</td>
                <td class="text-nowrap">
                  @if($client?->created_at)
                    {{ DateTimeDisplay::formatInstant($client->created_at, current_store_id()) }}
                    @if($preExistingCrm)
                      <span class="badge text-bg-light border ms-1">CRM pré-existente</span>
                    @endif
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ $row->name }}</td>
                <td>{{ $row->email }}</td>
                <td class="text-nowrap">{{ $client ? (PhoneDisplay::formatInternational((string) $client->phone) ?? $client->phone ?? '—') : '—' }}</td>
                <td>
                  @if($client)
                    <a href="{{ route('clientes.show', $client) }}">Ver cliente</a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @elseif($row instanceof BookingSlotHold)
              @php
                  $bookingClient = $row->bookingUser?->client;
                  $slotLabel = $row->slot_date
                      ? DateTimeDisplay::marcacao($row->slot_start_at, current_store_id(), 'd/m/Y H:i')
                      : '—';
              @endphp
              <tr>
                <td class="text-nowrap">{{ DateTimeDisplay::formatInstant($row->created_at, current_store_id()) }}</td>
                <td class="text-nowrap">{{ $slotLabel }}</td>
                <td>{{ $row->selectedUser?->name ?? '—' }}</td>
                <td>
                  @if($bookingClient)
                    <a href="{{ route('clientes.show', $bookingClient) }}">{{ $bookingClient->name }}</a>
                  @elseif($row->bookingUser)
                    {{ $row->bookingUser->name }}
                  @else
                    <span class="text-muted">Anónimo</span>
                  @endif
                </td>
                <td class="text-nowrap">{{ $row->expires_at ? DateTimeDisplay::formatInstant($row->expires_at, current_store_id()) : '—' }}</td>
                <td>
                  @if($funnelService->holdIsTimeExpired($row))
                    <span class="badge text-bg-warning">{{ $funnelService->holdReasonLabel($row) }}</span>
                  @else
                    <span class="badge text-bg-light border">{{ $funnelService->holdReasonLabel($row) }}</span>
                  @endif
                </td>
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>

    @include('relatorios.partials.pagination', ['paginator' => $rows])
  @else
    <div class="alert alert-light border text-center mb-0" role="status">
      <p class="fw-semibold mb-1">Sem registos</p>
      <p class="text-muted small mb-0">Não há entradas para «{{ $tabs[$activeTab]['label'] ?? '' }}».</p>
    </div>
  @endif
    </div>
  </div>
@endsection
