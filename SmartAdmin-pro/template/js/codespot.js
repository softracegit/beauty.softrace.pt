(function () {
  "use strict";

  var header = document.querySelector(".cs-header");
  var menuToggle = document.querySelector(".cs-menu-toggle");
  var navLinks = document.querySelectorAll(".cs-nav-wrap a[href^='#']");

  function onScroll() {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 24);
  }

  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  if (menuToggle) {
    menuToggle.addEventListener("click", function () {
      document.body.classList.toggle("cs-menu-open");
      var open = document.body.classList.contains("cs-menu-open");
      menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  navLinks.forEach(function (link) {
    link.addEventListener("click", function () {
      document.body.classList.remove("cs-menu-open");
      if (menuToggle) menuToggle.setAttribute("aria-expanded", "false");
    });
  });

  function animateCount(el, target, suffix, duration) {
    suffix = suffix || "";
    duration = duration || 1600;
    var start = 0;
    var startTime = null;
    var isInt = Number.isInteger(target);

    function step(ts) {
      if (!startTime) startTime = ts;
      var p = Math.min((ts - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      var current = start + (target - start) * eased;
      el.textContent = (isInt ? Math.round(current) : current.toFixed(1)) + suffix;
      if (p < 1) requestAnimationFrame(step);
    }

    requestAnimationFrame(step);
  }

  var counts = document.querySelectorAll(".cs-count[data-target]");
  if (counts.length && "IntersectionObserver" in window) {
    var obs = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var el = entry.target;
          var raw = el.getAttribute("data-target");
          var target = raw.indexOf(".") >= 0 ? parseFloat(raw) : parseInt(raw, 10);
          var suffix = el.getAttribute("data-suffix") || "";
          if (!isNaN(target)) {
            animateCount(el, target, suffix);
            obs.unobserve(el);
          }
        });
      },
      { threshold: 0.35 }
    );
    counts.forEach(function (c) {
      obs.observe(c);
    });
  }
})();
