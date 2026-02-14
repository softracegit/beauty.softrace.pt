@extends('partials.layouts.main')
@section('title', 'Oportunidades - Kanban | Imobiliária')
@section('page-heading-title', 'Oportunidades - Kanban')
@section('page-heading-sub-title', 'Real Estate')
@section('css')
    <!-- Dragula Css -->
    <link rel="stylesheet" href="{{ asset('template/vendor/dragula/dragula.min.css') }}">
    <style>
        .kanban-container {
            display: flex;
            overflow-x: auto;
            gap: 1rem;
            cursor: grab;
        }
        .kanban-container.scroll-dragging {
            cursor: grabbing;
        }
        .kanban-list {
            flex-shrink: 0;
        }
        @media (min-width: 1400px) {
            .kanban-list {
                min-width: calc((100% - 4rem) / 5);
                max-width: calc((100% - 4rem) / 5);
            }
        }
        @media (max-width: 1399px) {
            .kanban-list {
                min-width: 280px;
                max-width: 280px;
            }
        }
        .kanban-card {
            cursor: move;
            transition: all 0.2s;
        }
        .kanban-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .gu-mirror {
            opacity: 0.8;
        }
        .gu-transit {
            opacity: 0.2;
        }
        .kanban-column {
            min-height: 500px;
            padding: 10px 0;
        }
        .kanban-column-empty {
            min-height: 500px;
            display: block;
        }
        /* Indicador de mais conteúdo à direita */
        .kanban-wrapper {
            position: relative;
        }
        .kanban-scroll-indicator {
            position: absolute;
            top: 0;
            right: 0;
            width: 48px;
            height: 100%;
            pointer-events: none;
            background: linear-gradient(to right, transparent, rgba(var(--bs-body-bg-rgb, 255, 255, 255), 0.95));
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 5;
        }
        .kanban-scroll-indicator.visible {
            opacity: 1;
            pointer-events: auto;
            cursor: pointer;
        }
        .kanban-scroll-indicator-inner {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(108, 117, 125, 0.9);
            color: white;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .kanban-scroll-indicator-inner::before {
            content: '';
            width: 16px;
            height: 16px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='white' viewBox='0 0 24 24'%3E%3Cpath d='M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z'/%3E%3C/svg%3E") center/contain no-repeat;
        }
<｜tool▁sep｜>path
D:\xampp\htdocs\workspace\Projetos\Admin\resources\views\opportunities\kanban.blade.php
    </style>
@endsection
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div class="d-flex gap-2 align-items-center">
            <select id="filterTransactionType" class="form-select" style="width: auto;">
                <option value="">Todos os Tipos</option>
                @foreach(\App\Models\Opportunity::transactionTypes() as $value => $label)
                    <option value="{{ $value }}" {{ request('transaction_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select id="filterAgent" class="form-select" style="width: auto;">
                <option value="">Todos os Agentes</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('opportunities.create') }}" class="btn btn-primary"><i class="ph ph-plus me-1"></i> Nova Oportunidade</a>
            <a href="{{ route('opportunities.index') }}" class="btn btn-light"><i class="ph ph-list me-1"></i> Lista</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body kanban-wrapper">
        <div class="kanban-scroll-indicator" id="kanbanScrollRight" aria-hidden="true">
            <span class="kanban-scroll-indicator-inner" id="kanbanScrollRightCount"></span>
        </div>
        <div class="kanban-container pb-3" id="kanbanContainer" style="min-height: 600px;">
            @foreach($statuses as $status)
                @php
                    $statusOpportunities = $opportunities->where('status', $status);
                    $statusLabel = $statusLabels[$status] ?? $status;
                    $count = $statusOpportunities->count();
                @endphp
                <div class="kanban-list">
                    <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                        <h6 class="mb-0 fw-semibold">{{ $statusLabel }}</h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary-subtle text-secondary">{{ $count }}</span>
                        </div>
                    </div>
                    <div id="kanban-{{ $status }}" class="kanban-column">
                        @if($count > 0)
                            @foreach($statusOpportunities as $opportunity)
                                <div class="card kanban-card mb-3" data-opportunity-id="{{ $opportunity->id }}">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <a href="{{ route('opportunities.show', $opportunity) }}" class="text-body fw-semibold text-decoration-none">
                                                {{ $opportunity->reference }}
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-sm p-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="ph ph-dots-three"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="{{ route('opportunities.show', $opportunity) }}"><i class="ph ph-eye me-2"></i> Ver</a></li>
                                                    <li><a class="dropdown-item" href="{{ route('opportunities.edit', $opportunity) }}"><i class="ph ph-pencil-simple me-2"></i> Editar</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <span class="badge bg-{{ $opportunity->priority_color }}-subtle text-{{ $opportunity->priority_color }}">{{ \App\Models\Opportunity::priorities()[$opportunity->priority] ?? $opportunity->priority }}</span>
                                            <span class="badge bg-info-subtle text-info ms-1">{{ \App\Models\Opportunity::types()[$opportunity->type] ?? $opportunity->type }}</span>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted d-block">
                                                <i class="ph-duotone ph-user me-1"></i>{{ $opportunity->client->name }}
                                            </small>
                                            @if($opportunity->agent)
                                            <small class="text-muted d-block">
                                                <i class="ph-duotone ph-users me-1"></i>{{ $opportunity->agent->name }}
                                            </small>
                                            @endif
                                        </div>
                                        @php
                                            $preference = $opportunity->propertyPreferences->where('is_active', true)->first();
                                        @endphp
                                        @if($preference && ($preference->min_price || $preference->max_price))
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="ph-duotone ph-currency-eur me-1"></i>
                                                @if($preference->min_price && $preference->max_price)
                                                    {{ number_format($preference->min_price, 0, ',', '.') }}€ - {{ number_format($preference->max_price, 0, ',', '.') }}€
                                                @elseif($preference->min_price)
                                                    A partir de {{ number_format($preference->min_price, 0, ',', '.') }}€
                                                @elseif($preference->max_price)
                                                    Até {{ number_format($preference->max_price, 0, ',', '.') }}€
                                                @endif
                                            </small>
                                        </div>
                                        @endif
                                        @if($opportunity->properties->count() > 0)
                                        <div class="mt-2">
                                            <span class="badge bg-primary-subtle text-primary">
                                                <i class="ph-duotone ph-house me-1"></i>{{ $opportunity->properties->count() }} imóvel(is)
                                            </span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="kanban-column-empty"></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@endsection
@section('js')
    <!-- Dragula Js -->
    <script src="{{ asset('template/vendor/dragula/dragula.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ajustar largura das colunas dinamicamente
            function adjustKanbanWidth() {
                const container = document.querySelector('.kanban-container');
                const lists = document.querySelectorAll('.kanban-list');
                const count = lists.length;
                
                if (container && lists.length > 0) {
                    if (count <= 5 && window.innerWidth >= 1400) {
                        const gap = 16;
                        const totalGap = gap * (count - 1);
                        const availableWidth = container.offsetWidth - totalGap;
                        const widthPerColumn = availableWidth / count;
                        
                        lists.forEach(list => {
                            list.style.minWidth = widthPerColumn + 'px';
                            list.style.maxWidth = widthPerColumn + 'px';
                        });
                    } else {
                        lists.forEach(list => {
                            list.style.minWidth = '280px';
                            list.style.maxWidth = '280px';
                        });
                    }
                }
            }
            
            adjustKanbanWidth();
            window.addEventListener('resize', adjustKanbanWidth);
            
            // Indicador de scroll à direita: só mostrar se existirem cards não visíveis à direita
            function updateScrollIndicators() {
                const container = document.getElementById('kanbanContainer');
                const indicatorRight = document.getElementById('kanbanScrollRight');
                const countEl = document.getElementById('kanbanScrollRightCount');
                if (!container || !indicatorRight || !countEl) return;

                const canScrollRight = container.scrollLeft + container.clientWidth < container.scrollWidth - 2;
                const visibleRight = container.scrollLeft + container.clientWidth;
                const lists = container.querySelectorAll('.kanban-list');
                let hiddenCount = 0;
                lists.forEach(list => {
                    if (list.offsetLeft >= visibleRight) {
                        hiddenCount += list.querySelectorAll('.kanban-card').length;
                    }
                });

                const showIndicator = canScrollRight && hiddenCount > 0;
                indicatorRight.classList.toggle('visible', showIndicator);
                countEl.textContent = showIndicator ? hiddenCount : '';
            }

            function scrollToNextColumn() {
                const container = document.getElementById('kanbanContainer');
                if (!container) return;
                const visibleRight = container.scrollLeft + container.clientWidth;
                const lists = container.querySelectorAll('.kanban-list');
                for (let i = 0; i < lists.length; i++) {
                    const list = lists[i];
                    if (list.offsetLeft >= visibleRight - 2) {
                        const targetScroll = Math.max(0, list.offsetLeft - 24);
                        container.scrollTo({ left: targetScroll, behavior: 'smooth' });
                        return;
                    }
                }
            }
            
            const kanbanContainer = document.getElementById('kanbanContainer');
            const indicatorRight = document.getElementById('kanbanScrollRight');
            if (indicatorRight) {
                indicatorRight.addEventListener('click', scrollToNextColumn);
            }
            if (kanbanContainer) {
                kanbanContainer.addEventListener('scroll', updateScrollIndicators);
                setTimeout(updateScrollIndicators, 100);
                window.addEventListener('resize', () => setTimeout(updateScrollIndicators, 100));
            }
            
            // Arrastar para scroll (não interfere com drag de cards)
            let isScrollDragging = false;
            let scrollStartX = 0;
            let scrollStartLeft = 0;

            kanbanContainer?.addEventListener('mousedown', function(e) {
                if (e.target.closest('.kanban-card')) return;
                isScrollDragging = true;
                scrollStartX = e.pageX;
                scrollStartLeft = kanbanContainer.scrollLeft;
                kanbanContainer.classList.add('scroll-dragging');
                kanbanContainer.style.userSelect = 'none';
                e.preventDefault();
            });

            document.addEventListener('mousemove', function(e) {
                if (!isScrollDragging || !kanbanContainer) return;
                const delta = scrollStartX - e.pageX;
                kanbanContainer.scrollLeft = scrollStartLeft + delta;
            });

            document.addEventListener('mouseup', function() {
                if (isScrollDragging) {
                    isScrollDragging = false;
                    kanbanContainer?.classList.remove('scroll-dragging');
                    kanbanContainer.style.userSelect = '';
                }
            });

            kanbanContainer?.addEventListener('mouseleave', function() {
                if (isScrollDragging) {
                    isScrollDragging = false;
                    kanbanContainer.classList.remove('scroll-dragging');
                    kanbanContainer.style.userSelect = '';
                }
            });
            
            // Inicializar dragula
            const containers = [];
            @foreach($statuses as $status)
                const container{{ $loop->index }} = document.querySelector('#kanban-{{ $status }}');
                if (container{{ $loop->index }}) {
                    containers.push(container{{ $loop->index }});
                }
            @endforeach

            const drake = dragula(containers, {
                moves: function (el, source, handle, sibling) {
                    return el.classList.contains('kanban-card');
                },
                accepts: function (el, target, source, sibling) {
                    return true;
                },
                revertOnSpill: false,
                copy: false,
            });

            // Auto-scroll ao arrastar card para a beira do ecrã
            const EDGE_ZONE = 80;
            const SCROLL_SPEED = 12;
            let isCardDragging = false;
            let lastMouseX = 0;
            let autoScrollRafId = null;

            function autoScrollLoop() {
                if (!isCardDragging || !kanbanContainer) return;
                const rect = kanbanContainer.getBoundingClientRect();
                const canScrollLeft = kanbanContainer.scrollLeft > 0;
                const canScrollRight = kanbanContainer.scrollLeft + kanbanContainer.clientWidth < kanbanContainer.scrollWidth - 2;
                let delta = 0;
                if (lastMouseX < rect.left + EDGE_ZONE && canScrollLeft) {
                    const intensity = 1 - (lastMouseX - rect.left) / EDGE_ZONE;
                    delta = -SCROLL_SPEED * Math.max(0.3, intensity);
                } else if (lastMouseX > rect.right - EDGE_ZONE && canScrollRight) {
                    const intensity = 1 - (rect.right - lastMouseX) / EDGE_ZONE;
                    delta = SCROLL_SPEED * Math.max(0.3, intensity);
                }
                if (delta !== 0) {
                    kanbanContainer.scrollLeft += delta;
                    updateScrollIndicators();
                }
                autoScrollRafId = requestAnimationFrame(autoScrollLoop);
            }

            drake.on('drag', function(el, source) {
                isCardDragging = true;
                autoScrollRafId = requestAnimationFrame(autoScrollLoop);
            });

            drake.on('dragend', function() {
                isCardDragging = false;
                if (autoScrollRafId) {
                    cancelAnimationFrame(autoScrollRafId);
                    autoScrollRafId = null;
                }
            });

            document.addEventListener('mousemove', function(e) {
                lastMouseX = e.clientX;
            }, { passive: true });

            drake.on('drop', function(el, target, source, sibling) {
                const opportunityId = el.dataset.opportunityId;
                const newStatus = target.id.replace('kanban-', '');

                // Remover placeholder se existir
                const emptyPlaceholder = target.querySelector('.kanban-column-empty');
                if (emptyPlaceholder) {
                    emptyPlaceholder.remove();
                }

                fetch(`/opportunities/${opportunityId}/update-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateColumnCount(target);
                        updateColumnCount(source);
                        
                        // Adicionar placeholder de volta se a coluna de destino ficou vazia
                        if (target.querySelectorAll('.kanban-card').length === 0 && !target.querySelector('.kanban-column-empty')) {
                            const placeholder = document.createElement('div');
                            placeholder.className = 'kanban-column-empty';
                            target.appendChild(placeholder);
                        }
                        
                        // Adicionar placeholder de volta se a coluna de origem ficou vazia
                        if (source && source.querySelectorAll('.kanban-card').length === 0 && !source.querySelector('.kanban-column-empty')) {
                            const placeholder = document.createElement('div');
                            placeholder.className = 'kanban-column-empty';
                            source.appendChild(placeholder);
                        }
                    } else {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    location.reload();
                });
            });

            // Filtros
            document.getElementById('filterTransactionType')?.addEventListener('change', function() {
                const type = this.value;
                const url = new URL(window.location);
                if (type) {
                    url.searchParams.set('transaction_type', type);
                } else {
                    url.searchParams.delete('transaction_type');
                }
                window.location.href = url.toString();
            });

            document.getElementById('filterAgent')?.addEventListener('change', function() {
                const agentId = this.value;
                const url = new URL(window.location);
                if (agentId) {
                    url.searchParams.set('agent_id', agentId);
                } else {
                    url.searchParams.delete('agent_id');
                }
                window.location.href = url.toString();
            });

            function updateColumnCount(column) {
                const count = column.querySelectorAll('.kanban-card').length;
                const header = column.closest('.kanban-list');
                if (header) {
                    const badge = header.querySelector('.badge');
                    if (badge) {
                        badge.textContent = count;
                    }
                }
            }
        });
    </script>
@endsection
