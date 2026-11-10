(function () {
  'use strict';

  var root = document.getElementById('yearRecap');
  if (!root) return;

  var slides = Array.prototype.slice.call(root.querySelectorAll('.yr__slide'));
  var segments = Array.prototype.slice.call(root.querySelectorAll('.yr__progress-seg'));
  var btnPrev = root.querySelector('[data-yr-prev]');
  var btnNext = root.querySelector('[data-yr-next]');
  var hitPrev = root.querySelector('[data-yr-hit-prev]');
  var hitNext = root.querySelector('[data-yr-hit-next]');
  var btnPause = root.querySelector('[data-yr-pause]');
  var pauseIcon = root.querySelector('[data-yr-pause-icon]');
  var autoMs = 10000;
  // Pico de cobertura ~62% de 1.05s ≈ 650ms; fim ≈ 1050ms
  var isLava = root.classList.contains('yr--lava');
  var isGloss = root.classList.contains('yr--gloss');
  // Gloss: a 3ª pincelada cobre o ecrã aos ~700ms e termina aos ~880ms
  var morphMs = isGloss ? 720 : 650;
  var morphEndMs = isGloss ? 920 : 1000;
  var index = 0;
  var timer = null;
  var morphTimer = null;
  var morphEndTimer = null;
  var paused = false;
  var busy = false;
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // Templates com fundo por slide (data-bg) e transição em overlay (.yr__morph)
  var hasBg = isLava || isGloss;
  var useLavaMorph = hasBg && !reduced;

  var morphVariants = isGloss
    ? ['swipe-r', 'swipe-up', 'swipe-l', 'swipe-down']
    : ['bloom', 'corner-tl', 'rise', 'side', 'corner-br', 'drip', 'splash'];
  var morphColors = isGloss
    ? ['lime', 'cobalt', 'fuchsia', 'butter', 'tangerine', 'lilac', 'cherry']
    : ['coral', 'violet', 'aqua', 'magenta', 'indigo', 'peach'];
  var lastMorph = -1;
  var pendingBg = null;

  function bgFor(i) {
    if (isGloss) {
      if (i === 0) return 'cherry';
      if (i === slides.length - 1) return 'plum';
      return morphColors[(i - 1) % morphColors.length];
    }
    return i === 0 ? 'lilac' : morphColors[i % morphColors.length];
  }

  function emit(name, i) {
    var ev;
    try {
      ev = new CustomEvent(name, { detail: { index: i } });
    } catch (err) {
      return;
    }
    root.dispatchEvent(ev);
  }

  function pickMorph(nextIndex) {
    var i = nextIndex % morphVariants.length;
    if (i === lastMorph) {
      i = (i + 1) % morphVariants.length;
    }
    lastMorph = i;
    pendingBg = bgFor(nextIndex);
    root.setAttribute('data-morph', morphVariants[i]);
    root.setAttribute('data-morph-color', pendingBg);
  }

  function commitBg() {
    if (pendingBg) {
      root.setAttribute('data-bg', pendingBg);
      pendingBg = null;
    }
  }

  function canAutoAdvance() {
    if (paused || reduced || busy) return false;
    var slide = slides[index];
    if (!slide) return false;
    var id = slide.getAttribute('data-id');
    return id !== 'cover' && id !== 'finale';
  }

  function syncPauseUi() {
    root.classList.toggle('is-paused', paused);
    if (btnPause) {
      btnPause.setAttribute('aria-label', paused ? 'Reproduzir' : 'Pausar');
      btnPause.setAttribute('title', paused ? 'Reproduzir' : 'Pausar');
    }
    if (pauseIcon) {
      pauseIcon.className = paused ? 'ph-duotone ph-play' : 'ph-duotone ph-pause';
      pauseIcon.setAttribute('data-yr-pause-icon', '');
    }
  }

  function clearTimer() {
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
  }

  function clearMorphTimers() {
    if (morphTimer) {
      clearTimeout(morphTimer);
      morphTimer = null;
    }
    if (morphEndTimer) {
      clearTimeout(morphEndTimer);
      morphEndTimer = null;
    }
  }

  function setPaused(next) {
    paused = !!next;
    syncPauseUi();
    if (paused) {
      clearTimer();
    } else {
      restartAuto();
    }
  }

  function togglePause() {
    setPaused(!paused);
  }

  function applySlide(nextIndex) {
    index = nextIndex;

    slides.forEach(function (slide, i) {
      var active = i === index;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
    });

    segments.forEach(function (seg, i) {
      seg.classList.remove('is-done', 'is-active');
      var fill = seg.firstElementChild;
      if (fill) fill.style.width = '';
      if (i < index) seg.classList.add('is-done');
      if (i === index) seg.classList.add('is-active');
    });

    if (btnPrev) btnPrev.disabled = index === 0;
    if (btnNext) btnNext.disabled = index === slides.length - 1;

    var activeSlide = slides[index];
    if (activeSlide) {
      root.setAttribute('data-fx', activeSlide.getAttribute('data-theme') || 'ink');
    }

    emit('yr:slide', index);
  }

  function setActive(nextIndex, opts) {
    opts = opts || {};
    if (nextIndex < 0 || nextIndex >= slides.length) return;
    if (nextIndex === index && !opts.force) return;
    if (busy && !opts.instant) return;

    var skipMorph = opts.instant || opts.keepTimer || !useLavaMorph;

    if (skipMorph) {
      if (hasBg) {
        root.setAttribute('data-bg', bgFor(nextIndex));
      }
      applySlide(nextIndex);
      emit('yr:shown', nextIndex);
      if (!opts.keepTimer) restartAuto();
      return;
    }

    busy = true;
    clearTimer();
    clearMorphTimers();

    pickMorph(nextIndex);

    // Force reflow so repeated same-class animation restarts cleanly
    root.classList.remove('is-morphing');
    void root.offsetWidth;
    root.classList.add('is-morphing');

    morphTimer = setTimeout(function () {
      commitBg();
      applySlide(nextIndex);
    }, morphMs);

    morphEndTimer = setTimeout(function () {
      root.classList.remove('is-morphing');
      busy = false;
      emit('yr:shown', nextIndex);
      restartAuto();
    }, morphEndMs);
  }

  function go(delta) {
    setActive(index + delta);
  }

  function restartAuto() {
    clearTimer();
    if (!canAutoAdvance()) return;
    timer = setTimeout(function () {
      if (!paused && !busy && index < slides.length - 1) go(1);
    }, autoMs);
  }

  function onKey(e) {
    if (e.key === ' ' || e.code === 'Space') {
      e.preventDefault();
      togglePause();
      return;
    }
    if (e.key === 'p' || e.key === 'P') {
      e.preventDefault();
      togglePause();
      return;
    }
    if (e.key === 'ArrowRight' || e.key === 'Enter') {
      e.preventDefault();
      go(1);
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      go(-1);
    } else if (e.key === 'Escape') {
      var close = root.querySelector('.yr__close');
      if (close && close.href) window.location.href = close.href;
    }
  }

  if (btnPrev) btnPrev.addEventListener('click', function () { go(-1); });
  if (btnNext) btnNext.addEventListener('click', function () { go(1); });
  if (hitPrev) hitPrev.addEventListener('click', function () { go(-1); });
  if (hitNext) hitNext.addEventListener('click', function () { go(1); });
  if (btnPause) btnPause.addEventListener('click', function (e) {
    e.stopPropagation();
    togglePause();
  });

  root.addEventListener('click', function (e) {
    var restart = e.target.closest('[data-yr-restart]');
    var start = e.target.closest('[data-yr-start]');
    if (restart || start) {
      e.preventDefault();
      setActive(restart ? 0 : 1);
    }
  });

  segments.forEach(function (seg, i) {
    seg.addEventListener('animationend', function () {
      if (paused || busy) return;
      if (i === index && index < slides.length - 1) go(1);
    });
  });

  document.addEventListener('keydown', onKey);

  syncPauseUi();
  if (hasBg) {
    root.setAttribute('data-bg', bgFor(0));
  }
  setActive(0, { keepTimer: true, instant: true, force: true });
  restartAuto();
})();
