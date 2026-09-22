@extends('partials.layouts.main')

@section('title', 'Year Recap ' . $recap['year'] . ' | Beauty CRM')

@section('css')
<link rel="stylesheet" href="{{ asset('template/css/year-recap.css') }}?v={{ filemtime(public_path('template/css/year-recap.css')) }}">
<link rel="stylesheet" href="{{ asset('template/css/year-recap-aurora.css') }}?v={{ filemtime(public_path('template/css/year-recap-aurora.css')) }}">
<link rel="stylesheet" href="{{ asset('template/css/year-recap-lava.css') }}?v={{ filemtime(public_path('template/css/year-recap-lava.css')) }}">
@endsection

@section('content')
@php
  $slides = $recap['slides'] ?? [];
  $total = count($slides);
  $template = $recap['template'] ?? 'nocturne';
  $firstTheme = $slides[0]['theme'] ?? 'cover';
@endphp

<div
  class="yr yr--{{ $template }}"
  id="yearRecap"
  data-fx="{{ $firstTheme }}"
  @if($template === 'lava') data-bg="lilac" @endif
  role="region"
  aria-label="Year Recap {{ $recap['year'] }}"
>
  <div class="yr__fx" aria-hidden="true">
    <span class="yr__fx-blob yr__fx-blob--a"></span>
    <span class="yr__fx-blob yr__fx-blob--b"></span>
    <span class="yr__fx-blob yr__fx-blob--c"></span>
    <span class="yr__fx-ring yr__fx-ring--a"></span>
    <span class="yr__fx-ring yr__fx-ring--b"></span>
    <span class="yr__fx-dot yr__fx-dot--a"></span>
    <span class="yr__fx-dot yr__fx-dot--b"></span>
    <span class="yr__fx-dot yr__fx-dot--c"></span>
  </div>

  <div class="yr__chrome">
    <div class="yr__brand">
      <span class="yr__brand-dot" aria-hidden="true"></span>
      <span>Year Recap · {{ $recap['year'] }}</span>
      @if(!empty($recap['is_demo']))
        <span class="yr__demo-badge">Pré-visualização</span>
      @endif
      <nav class="yr__template-switch" aria-label="Template">
        <a href="{{ route('year-recap.show', ['template' => 'nocturne']) }}" class="{{ $template === 'nocturne' ? 'is-active' : '' }}">Nocturne</a>
        <a href="{{ route('year-recap.show', ['template' => 'aurora']) }}" class="{{ $template === 'aurora' ? 'is-active' : '' }}">Aurora</a>
        <a href="{{ route('year-recap.show', ['template' => 'lava']) }}" class="{{ $template === 'lava' ? 'is-active' : '' }}">Lava</a>
      </nav>
    </div>
    <div class="yr__chrome-actions">
      <button type="button" class="yr__icon-btn" data-yr-pause aria-label="Pausar" title="Pausar">
        <i class="ph-duotone ph-pause" data-yr-pause-icon aria-hidden="true"></i>
      </button>
      <a class="yr__close" href="{{ route('dashboard') }}" title="Sair">
        <i class="ph-duotone ph-x" aria-hidden="true"></i>
        Sair
      </a>
    </div>
  </div>

  <div class="yr__morph" aria-hidden="true">
    <span class="yr__morph-blob yr__morph-blob--1"></span>
    <span class="yr__morph-blob yr__morph-blob--2"></span>
    <span class="yr__morph-blob yr__morph-blob--3"></span>
    <span class="yr__morph-blob yr__morph-blob--4"></span>
  </div>

  <div class="yr__progress" aria-hidden="true">
    @foreach($slides as $i => $slide)
      <div class="yr__progress-seg {{ $i === 0 ? 'is-active' : '' }}" data-index="{{ $i }}">
        <span></span>
      </div>
    @endforeach
  </div>

  <div class="yr__stage">
    <button type="button" class="yr__hit yr__hit--prev" data-yr-hit-prev aria-label="Anterior"></button>
    <button type="button" class="yr__hit yr__hit--next" data-yr-hit-next aria-label="Seguinte"></button>

    @foreach($slides as $i => $slide)
      @php $id = $slide['id'] ?? ('slide-'.$i); @endphp
      <article
        class="yr__slide {{ $i === 0 ? 'is-active' : '' }}"
        data-id="{{ $id }}"
        data-theme="{{ $slide['theme'] ?? 'ink' }}"
        aria-hidden="{{ $i === 0 ? 'false' : 'true' }}"
      >
        <div class="yr__card">
          @if(!empty($slide['kicker']))
            <div class="yr__kicker">{{ $slide['kicker'] }}</div>
          @endif

          @if($id === 'cover')
            <h1 class="yr__title">{{ $slide['title'] ?? $recap['year'] }}</h1>
            @if(!empty($slide['subtitle']))
              <p class="yr__subtitle">{{ $slide['subtitle'] }}</p>
            @endif
            @if(!empty($slide['body']))
              <p class="yr__body">{{ $slide['body'] }}</p>
            @endif
            <div class="yr__actions-inline">
              <button type="button" class="yr__btn yr__btn--primary" data-yr-start>
                {{ $slide['cta'] ?? 'Começar' }}
              </button>
            </div>

          @elseif($id === 'finale')
            <h2 class="yr__title" style="font-size: clamp(2.2rem, 7vw, 3.5rem);">{{ $slide['title'] ?? 'Foi um ano cheio.' }}</h2>
            @if(!empty($slide['body']))
              <p class="yr__body">{{ $slide['body'] }}</p>
            @endif
            @if(!empty($slide['highlights']))
              <div class="yr__highlights">
                @foreach($slide['highlights'] as $h)
                  <div class="yr__highlight">
                    <span>{{ $h['label'] }}</span>
                    <strong>{{ $h['value'] }}</strong>
                  </div>
                @endforeach
              </div>
            @endif
            <div class="yr__actions-inline">
              <button type="button" class="yr__btn yr__btn--primary" data-yr-restart>
                {{ $slide['cta'] ?? 'Ver de novo' }}
              </button>
              <a class="yr__btn yr__btn--ghost" href="{{ route('dashboard') }}">
                {{ $slide['exit'] ?? 'Voltar ao dashboard' }}
              </a>
            </div>

          @elseif($id === 'canais')
            @if(!empty($slide['label']))
              <p class="yr__label">{{ $slide['label'] }}</p>
            @endif
            @if(!empty($slide['split']))
              <div class="yr__split">
                @foreach($slide['split'] as $row)
                  <div class="yr__split-row">
                    <div class="yr__split-meta">
                      <span>{{ $row['label'] }}</span>
                      <strong>{{ $row['value'] }}</strong>
                    </div>
                    <div class="yr__split-bar">
                      <span style="--pct: {{ (int) ($row['pct'] ?? 0) }}%;"></span>
                    </div>
                  </div>
                @endforeach
              </div>
            @endif
            @if(!empty($slide['body']))
              <p class="yr__body" style="margin-top: 1.5rem;">{{ $slide['body'] }}</p>
            @endif

          @else
            @if(!empty($slide['label']))
              <p class="yr__label">{{ $slide['label'] }}</p>
            @endif
            @if(!empty($slide['value']))
              @php
                $isTextValue = !preg_match('/^[\d\s\.\,\%€k\+\-]+$/u', trim((string) $slide['value']))
                  || mb_strlen((string) $slide['value']) > 12;
              @endphp
              <p class="yr__value {{ $isTextValue ? 'yr__value--text' : '' }}">{{ $slide['value'] }}</p>
            @endif
            @if(!empty($slide['body']))
              <p class="yr__body">{{ $slide['body'] }}</p>
            @endif
            @if(!empty($slide['footnote']))
              <div class="yr__footnote">
                <i class="ph-duotone ph-arrow-up" aria-hidden="true"></i>
                {{ $slide['footnote'] }}
              </div>
            @endif
            @if(!empty($slide['stat_secondary']))
              <div class="yr__secondary">
                <span class="yr__secondary-label">{{ $slide['stat_secondary']['label'] }}</span>
                <span class="yr__secondary-value">{{ $slide['stat_secondary']['value'] }}</span>
              </div>
            @endif
            @if(!empty($slide['list']))
              <ul class="yr__list">
                @foreach($slide['list'] as $row)
                  <li>
                    <span class="yr__list-name">{{ $row['name'] }}</span>
                    <span class="yr__list-count">{{ $row['count'] }}</span>
                  </li>
                @endforeach
              </ul>
            @endif
          @endif
        </div>
      </article>
    @endforeach
  </div>

  <div class="yr__nav">
    <div class="yr__nav-hint">← → laterais · Espaço pausa · Esc sair</div>
    <div class="yr__nav-btns">
      <button type="button" class="yr__nav-btn" data-yr-prev aria-label="Anterior" disabled>
        <i class="ph-duotone ph-arrow-left" aria-hidden="true"></i>
      </button>
      <button type="button" class="yr__nav-btn" data-yr-next aria-label="Seguinte">
        <i class="ph-duotone ph-arrow-right" aria-hidden="true"></i>
      </button>
    </div>
  </div>
</div>
@endsection

@section('js')
<script src="{{ asset('template/js/year-recap.js') }}?v={{ filemtime(public_path('template/js/year-recap.js')) }}"></script>
@endsection
