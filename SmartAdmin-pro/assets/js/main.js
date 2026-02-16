/**
 * =======================================================
 * Template Name: SmartAdmin - Bootstrap Admin Template
 * Template URL: https://bootstrapmade.com/smart-admin-clean-bootstrap-admin-template/
 * Updated: Jan 31, 2026
 * Author: BootstrapMade.com
 * License: https://bootstrapmade.com/license/
 * =======================================================
 */
/**
 * SmartAdmin - Main JavaScript
 * Handles two-panel sidebar, mobile menu, search, scroll to top
 */

(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initSearch();
    initBackToTop();
    initDropdowns();
    initTooltips();
  });

  /**
   * Two-Panel Sidebar
   *
   * Breakpoints:
   * - >= 1280px: Icon bar + panel visible (panel open by default)
   * - 768px - 1279px: Icon bar visible, panel hidden (toggle opens panel)
   * - < 768px: Everything hidden (hamburger opens full sidebar)
   */
  function initSidebar() {
    var body = document.body;
    var sidebarToggle = document.querySelector('.sidebar-toggle');
    var sidebarOverlay = document.querySelector('.sidebar-overlay');
    var iconbarItems = document.querySelectorAll('.iconbar-item[data-panel]');

    // Sidebar toggle button behavior depends on viewport
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', function(e) {
        e.preventDefault();

        if (window.innerWidth < 768) {
          // Mobile: toggle full sidebar visibility
          body.classList.toggle('sidebar-open');
        } else if (window.innerWidth < 1280) {
          // Tablet: toggle panel open/close
          body.classList.toggle('sidebar-panel-open');
        } else {
          // Desktop: toggle panel collapsed
          body.classList.toggle('sidebar-panel-collapsed');
          localStorage.setItem('sidebar-panel-collapsed', body.classList.contains('sidebar-panel-collapsed'));
        }
      });
    }

    // Close sidebar on overlay click (mobile)
    if (sidebarOverlay) {
      sidebarOverlay.addEventListener('click', function() {
        body.classList.remove('sidebar-open');
      });
    }

    // Panel close buttons (visible under 1280px)
    var panelCloseButtons = document.querySelectorAll('.sidebar-panel-close');
    panelCloseButtons.forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (window.innerWidth < 768) {
          body.classList.remove('sidebar-open');
        } else {
          body.classList.remove('sidebar-panel-open');
        }
      });
    });

    // Icon bar items: switch active panel section
    iconbarItems.forEach(function(item) {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        var panelId = this.getAttribute('data-panel');

        // Update active icon
        iconbarItems.forEach(function(btn) {
          btn.classList.remove('active');
        });
        this.classList.add('active');

        // Show corresponding panel section
        var sections = document.querySelectorAll('.sidebar-panel-section');
        sections.forEach(function(section) {
          section.classList.remove('active');
        });

        var targetSection = document.querySelector('[data-section="' + panelId + '"]');
        if (targetSection) {
          targetSection.classList.add('active');
        }

        // On tablet (768-1279), also open the panel if it's closed
        if (window.innerWidth >= 768 && window.innerWidth < 1280) {
          body.classList.add('sidebar-panel-open');
        }

        // On desktop, if panel is collapsed, uncollapse it
        if (window.innerWidth >= 1280 && body.classList.contains('sidebar-panel-collapsed')) {
          body.classList.remove('sidebar-panel-collapsed');
          localStorage.setItem('sidebar-panel-collapsed', 'false');
        }
      });
    });

    // Restore collapsed state from localStorage (desktop only)
    if (localStorage.getItem('sidebar-panel-collapsed') === 'true' && window.innerWidth >= 1280) {
      body.classList.add('sidebar-panel-collapsed');
    }

    // Handle window resize
    var resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        if (window.innerWidth >= 768) {
          body.classList.remove('sidebar-open');
        }
        if (window.innerWidth >= 1280) {
          body.classList.remove('sidebar-panel-open');
        }
      }, 250);
    });

    // Initialize panel nav groups (accordion)
    initPanelNavGroups();
  }

  /**
   * Panel Nav Groups - Accordion collapse/expand
   */
  function initPanelNavGroups() {
    var toggles = document.querySelectorAll('.panel-group-toggle');

    toggles.forEach(function(toggle) {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();

        var group = this.parentElement;
        var subnav = group.querySelector('.panel-subnav');
        var isOpen = group.classList.contains('open');

        // Close siblings
        var siblings = group.parentElement.querySelectorAll(':scope > .panel-nav-group.open');
        siblings.forEach(function(sibling) {
          if (sibling !== group) {
            sibling.classList.remove('open');
            var siblingLink = sibling.querySelector('.panel-group-toggle');
            if (siblingLink) siblingLink.setAttribute('aria-expanded', 'false');
            var siblingNav = sibling.querySelector('.panel-subnav');
            if (siblingNav) siblingNav.style.maxHeight = null;
          }
        });

        // Toggle current
        if (isOpen) {
          group.classList.remove('open');
          this.setAttribute('aria-expanded', 'false');
          if (subnav) subnav.style.maxHeight = null;
        } else {
          group.classList.add('open');
          this.setAttribute('aria-expanded', 'true');
          if (subnav) subnav.style.maxHeight = subnav.scrollHeight + 'px';
        }
      });
    });

    // Auto-expand groups containing active links
    var activeLinks = document.querySelectorAll('.panel-subnav .panel-link.active');
    activeLinks.forEach(function(link) {
      var group = link.closest('.panel-nav-group');
      if (group) {
        group.classList.add('open');
        var groupToggle = group.querySelector('.panel-group-toggle');
        if (groupToggle) groupToggle.setAttribute('aria-expanded', 'true');
        var subnav = group.querySelector('.panel-subnav');
        if (subnav) subnav.style.maxHeight = 'none';
      }
    });
  }

  /**
   * Search Bar Toggle (Mobile)
   */
  function initSearch() {
    var searchToggle = document.querySelector('.search-toggle');
    var mobileSearch = document.querySelector('.mobile-search');
    var mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    var mobileHeaderMenu = document.querySelector('.mobile-header-menu');
    var searchInput = mobileSearch ? mobileSearch.querySelector('input') : null;

    if (searchToggle && mobileSearch) {
      searchToggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (mobileHeaderMenu && mobileHeaderMenu.classList.contains('active')) {
          mobileHeaderMenu.classList.remove('active');
        }
        mobileSearch.classList.toggle('active');
        if (mobileSearch.classList.contains('active') && searchInput) {
          searchInput.focus();
        }
      });
    }

    if (mobileMenuToggle && mobileHeaderMenu) {
      mobileMenuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (mobileSearch && mobileSearch.classList.contains('active')) {
          mobileSearch.classList.remove('active');
        }
        mobileHeaderMenu.classList.toggle('active');
      });
    }

    document.addEventListener('click', function(e) {
      if (mobileSearch && searchToggle && !mobileSearch.contains(e.target) && !searchToggle.contains(e.target)) {
        mobileSearch.classList.remove('active');
      }
      if (mobileHeaderMenu && mobileMenuToggle && !mobileHeaderMenu.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
        mobileHeaderMenu.classList.remove('active');
      }
    });

    window.addEventListener('resize', function() {
      if (window.innerWidth >= 768) {
        if (mobileSearch) mobileSearch.classList.remove('active');
        if (mobileHeaderMenu) mobileHeaderMenu.classList.remove('active');
      }
    });
  }

  /**
   * Back to Top Button
   */
  function initBackToTop() {
    var backToTop = document.querySelector('.back-to-top');
    if (backToTop) {
      window.addEventListener('scroll', function() {
        if (window.scrollY > 100) {
          backToTop.classList.add('visible');
        } else {
          backToTop.classList.remove('visible');
        }
      });

      backToTop.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      });
    }
  }

  /**
   * Initialize Dropdowns (fallback if Bootstrap JS unavailable)
   */
  function initDropdowns() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
      return;
    }

    var dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    dropdownToggles.forEach(function(toggle) {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var parent = this.parentElement;
        var menu = parent.querySelector('.dropdown-menu');
        document.querySelectorAll('.dropdown-menu.show').forEach(function(openMenu) {
          if (openMenu !== menu) openMenu.classList.remove('show');
        });
        menu.classList.toggle('show');
      });
    });

    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
          menu.classList.remove('show');
        });
      }
    });
  }

  /**
   * Initialize Tooltips
   */
  function initTooltips() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
      tooltipTriggerList.forEach(function(el) {
        new bootstrap.Tooltip(el);
      });
    }
  }

  /**
   * Fullscreen Toggle
   */
  window.toggleFullscreen = function() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen();
      document.body.classList.add('fullscreen-active');
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen();
        document.body.classList.remove('fullscreen-active');
      }
    }
  };

  document.addEventListener('fullscreenchange', function() {
    if (!document.fullscreenElement) {
      document.body.classList.remove('fullscreen-active');
    }
  });

})();