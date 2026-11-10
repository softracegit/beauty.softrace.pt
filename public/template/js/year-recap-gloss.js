/* Year Recap — Gloss: tipografia cinética (carregar ANTES de year-recap.js).
   Ouve os eventos `yr:slide` e `yr:shown` emitidos por year-recap.js. */
(function () {
  'use strict';

  var root = document.getElementById('yearRecap');
  if (!root || !root.classList.contains('yr--gloss')) return;

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var slides = Array.prototype.slice.call(root.querySelectorAll('.yr__slide'));
  var SHADES = {
    cherry: 'Cereja',
    lime: 'Lima',
    cobalt: 'Cobalto',
    fuchsia: 'Fúcsia',
    butter: 'Manteiga',
    tangerine: 'Tangerina',
    lilac: 'Lilás',
    plum: 'Ameixa'
  };
  var SHAPES = 5;
  var countRaf = null;

  /* ---- Palavras com máscara ---- */

  function wrapUnit(text, i) {
    var outer = document.createElement('span');
    outer.className = 'yr__w';
    var inner = document.createElement('span');
    inner.style.setProperty('--i', i);
    inner.textContent = text;
    outer.appendChild(inner);
    return outer;
  }

  function split(el) {
    var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
    if (!text) return;

    var isNumber = el.classList.contains('yr__value') && !el.classList.contains('yr__value--text');
    var isCoverTitle = el.classList.contains('yr__title') && text.length <= 6 && text.indexOf(' ') === -1;
    var units;

    if (isNumber) {
      units = [text];
    } else if (isCoverTitle) {
      units = text.split('');
    } else {
      units = text.split(' ');
    }

    if (isNumber || isCoverTitle) el.setAttribute('data-fill', '');
    el.textContent = '';
    units.forEach(function (unit, i) {
      var node = wrapUnit(unit, i);
      if (isNumber) node.firstChild.setAttribute('data-count', unit);
      el.appendChild(node);
      if (!isNumber && !isCoverTitle && i < units.length - 1) {
        el.appendChild(document.createTextNode(' '));
      }
    });
  }

  /* ---- Ajustar o tamanho para caber na largura do cartão ---- */

  function fit(el) {
    el.style.removeProperty('font-size');
    var size = parseFloat(window.getComputedStyle(el).fontSize);
    var guard = 0;

    // Números e ano da capa: crescem até encher a largura do cartão
    if (el.hasAttribute('data-fill') && el.clientWidth > 0) {
      var natural = 0;
      Array.prototype.forEach.call(el.querySelectorAll('.yr__w'), function (w) {
        natural += w.offsetWidth - size * 0.1; // desconta o padding da máscara
      });
      if (natural > 0) {
        var cap = Math.min(window.innerHeight * (window.innerHeight < 700 ? 0.22 : 0.3), size * 3);
        size = Math.min(size * ((el.clientWidth * 0.97) / natural), cap);
        el.style.setProperty('font-size', size + 'px', 'important');
      }
    }

    while (el.scrollWidth > el.clientWidth + 1 && size > 28 && guard < 40) {
      size *= 0.95;
      el.style.setProperty('font-size', size + 'px', 'important');
      guard++;
    }
  }

  function fitAll() {
    Array.prototype.forEach.call(root.querySelectorAll('.yr__value, .yr__title'), fit);
  }

  /* ---- Contagem dos números ---- */

  function resetCounts() {
    if (countRaf) {
      window.cancelAnimationFrame(countRaf);
      countRaf = null;
    }
    Array.prototype.forEach.call(root.querySelectorAll('[data-count]'), function (node) {
      node.textContent = node.getAttribute('data-count');
    });
  }

  function countUp(node) {
    var finalText = node.getAttribute('data-count');
    var m = finalText.match(/^(\D*)(\d[\d\s  ]*)(.*)$/);
    // Decimais ("4,8") ou formatos fora do comum: mostra o valor final sem contar
    if (!m || /^[.,]\d/.test(m[3])) return;

    var sepMatch = m[2].match(/[\s  ]/);
    var sep = sepMatch ? sepMatch[0] : '';
    var numPart = m[2].replace(/[\s  ]+$/, '');
    var tail = m[2].slice(numPart.length) + m[3];
    var target = parseInt(numPart.replace(/\D/g, ''), 10);
    if (!target) return;

    var duration = 1300;
    var start = null;

    function format(n) {
      var s = String(n);
      return sep ? s.replace(/\B(?=(\d{3})+(?!\d))/g, sep) : s;
    }

    function step(ts) {
      if (start === null) start = ts;
      var t = Math.min(1, (ts - start) / duration);
      var eased = 1 - Math.pow(1 - t, 4);
      node.textContent = m[1] + format(Math.round(target * eased)) + tail;
      if (t < 1) {
        countRaf = window.requestAnimationFrame(step);
      } else {
        node.textContent = finalText;
        countRaf = null;
      }
    }

    node.textContent = m[1] + format(0) + tail;
    countRaf = window.requestAnimationFrame(step);
  }

  /* ---- Palavra gigante em contorno ---- */

  var ghost = document.createElement('div');
  ghost.className = 'yr__ghost';
  ghost.setAttribute('aria-hidden', 'true');
  var ghostTrack = document.createElement('div');
  ghostTrack.className = 'yr__ghost-track';
  ghost.appendChild(ghostTrack);
  var fx = root.querySelector('.yr__fx');
  if (fx && fx.nextSibling) {
    root.insertBefore(ghost, fx.nextSibling);
  } else {
    root.appendChild(ghost);
  }

  function setGhost(word) {
    ghostTrack.textContent = '';
    if (!word) return;
    var half = '';
    for (var i = 0; i < 4; i++) half += word + ' ✦ ';
    for (var j = 0; j < 2; j++) {
      var span = document.createElement('span');
      span.textContent = half;
      ghostTrack.appendChild(span);
    }
  }

  /* ---- Eventos ---- */

  root.addEventListener('yr:slide', function (e) {
    var index = e.detail.index;
    var slide = slides[index];
    var bg = root.getAttribute('data-bg') || 'cherry';
    var kicker = slide ? slide.querySelector('.yr__kicker') : null;

    resetCounts();
    root.setAttribute('data-shape', String(index % SHAPES));
    root.setAttribute('data-shade', SHADES[bg] || bg);
    root.setAttribute('data-shade-n', (index + 1 < 10 ? '0' : '') + (index + 1));
    setGhost(kicker ? kicker.textContent.trim() : '');
  });

  root.addEventListener('yr:shown', function (e) {
    if (reduced) return;
    var slide = slides[e.detail.index];
    var node = slide ? slide.querySelector('[data-count]') : null;
    if (node) countUp(node);
  });

  Array.prototype.forEach.call(root.querySelectorAll('.yr__value, .yr__title'), split);
  fitAll();
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(fitAll);
  }

  var resizeTimer = null;
  window.addEventListener('resize', function () {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(fitAll, 150);
  });
})();
