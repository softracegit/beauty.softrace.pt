@extends('partials.layouts.main')
@section('title', 'Negócios | Imobiliária')
@section('page-heading-title', 'Negócios')
@section('page-heading-sub-title', 'Negócios Fechados')
@section('content')

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Lista de Negócios</h5>
            </div>
            <div class="card-body">
                @if($deals->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Referência</th>
                                <th>Oportunidade</th>
                                <th>Imóvel</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Data Fecho</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deals as $deal)
                            <tr>
                                <td>
                                    <a href="{{ route('deals.show', $deal) }}" class="fw-medium">{{ $deal->reference }}</a>
                                </td>
                                <td>
                                    <a href="{{ route('opportunities.show', $deal->opportunity) }}">{{ $deal->opportunity->reference ?? '—' }}</a>
                                </td>
                                <td>{{ $deal->property_title }}</td>
                                <td>{{ $deal->client->name ?? '—' }}</td>
                                <td class="fw-semibold text-success">{{ $deal->formatted_final_price }}</td>
                                <td>{{ $deal->closed_at->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $deal->status_color }}-subtle text-{{ $deal->status_color }}">
                                        {{ \App\Models\Deal::statuses()[$deal->status] ?? $deal->status }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('deals.show', $deal) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ph ph-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    {{ $deals->links() }}
                </div>
                @else
                <p class="text-muted text-center py-5">Nenhum negócio fechado registado.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
