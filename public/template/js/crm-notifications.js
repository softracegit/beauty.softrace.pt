/**
 * Dropdown de notificações no header (API JSON).
 */
(function () {
  'use strict';

  var cfg = window.CrmNotifications || {};
  if (!cfg.listUrl) {
    return;
  }

  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function markReadUrl(id) {
    return '/notificacoes/' + encodeURIComponent(id) + '/read';
  }

  function escapeHtml(s) {
    if (!s) return '';
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  /** Permite apenas &lt;br&gt; e &lt;strong&gt;/&lt;b&gt; no corpo do sininho. */
  function formatNotificationBody(html) {
    if (!html) return '';
    var tpl = document.createElement('template');
    tpl.innerHTML = String(html);
    function sanitize(node) {
      Array.from(node.childNodes).forEach(function (child) {
        if (child.nodeType === 1) {
          var tag = child.tagName.toLowerCase();
          if (tag === 'br') {
            while (child.attributes.length) {
              child.removeAttribute(child.attributes[0].name);
            }
            return;
          }
          if (tag === 'strong' || tag === 'b') {
            while (child.attributes.length) {
              child.removeAttribute(child.attributes[0].name);
            }
            sanitize(child);
            return;
          }
          var text = document.createTextNode(child.textContent || '');
          child.parentNode.replaceChild(text, child);
          return;
        }
      });
    }
    sanitize(tpl.content);
    return tpl.innerHTML;
  }

  function formatTime(iso) {
    if (!iso) return '';
    try {
      var d = new Date(iso);
      if (isNaN(d.getTime())) return '';
      return d.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
    } catch (e) {
      return '';
    }
  }

  function setBadge(count) {
    var badge = document.getElementById('headerNotificationBadge');
    var countEl = document.getElementById('headerNotificationCount');
    var markRead = document.getElementById('headerNotificationMarkRead');
    var empty = document.getElementById('headerNotificationEmpty');
    var itemsWrap = document.getElementById('headerNotificationItems');

    if (!badge) return;

    if (count > 0) {
      badge.textContent = String(count > 99 ? '99+' : count);
      badge.classList.remove('d-none');
      if (countEl) {
        countEl.textContent = count === 1 ? '1 nova' : count + ' novas';
        countEl.classList.remove('d-none');
      }
      if (markRead) markRead.classList.remove('d-none');
    } else {
      badge.classList.add('d-none');
      if (countEl) countEl.classList.add('d-none');
      if (markRead) markRead.classList.add('d-none');
    }

    if (itemsWrap && itemsWrap.children.length === 0) {
      if (empty) empty.classList.remove('d-none');
    }
  }

  function renderList(notifications) {
    var itemsWrap = document.getElementById('headerNotificationItems');
    var empty = document.getElementById('headerNotificationEmpty');
    if (!itemsWrap) return;

    itemsWrap.innerHTML = '';
    itemsWrap.classList.add('d-none');

    if (!notifications || notifications.length === 0) {
      if (empty) empty.classList.remove('d-none');
      return;
    }

    if (empty) empty.classList.add('d-none');
    itemsWrap.classList.remove('d-none');

    notifications.forEach(function (n) {
      var unread = !n.read;
      var iconClass = 'info';
      var iconName = 'bi-calendar-check';
      if (n.type === 'status_changed') {
        iconClass = 'danger';
        iconName = 'bi-calendar-x';
      } else if (n.type === 'rescheduled') {
        iconClass = 'warning';
        iconName = 'bi-calendar-event';
      } else if (n.type === 'reassigned') {
        iconClass = 'warning';
        iconName = 'bi-arrow-left-right';
      }
      var a = document.createElement('a');
      a.href = n.url || '#';
      a.className = 'notification-item' + (unread ? ' unread' : '');
      a.setAttribute('data-notification-id', n.id);
      a.innerHTML =
        '<div class="notification-icon ' + iconClass + '"><i class="bi ' + iconName + '"></i></div>' +
        '<div class="notification-content">' +
        '<div class="notification-title">' + formatNotificationBody(n.title) + '</div>' +
        '<div class="notification-text">' + formatNotificationBody(n.body) + '</div>' +
        '<div class="notification-time"><i class="bi bi-clock"></i> ' +
        escapeHtml(formatTime(n.created_at)) +
        '</div></div>' +
        (unread ? '<span class="notification-dot"></span>' : '');

      a.addEventListener('click', function (e) {
        if (!unread || !n.id) return;
        e.preventDefault();
        fetch(markReadUrl(n.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: '{}',
        })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            if (data.unread_count !== undefined) {
              setBadge(data.unread_count);
            }
            window.location.href = n.url || '/agenda';
          })
          .catch(function () {
            window.location.href = n.url || '/agenda';
          });
      });

      itemsWrap.appendChild(a);
    });
  }

  function load() {
    fetch(cfg.listUrl, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        renderList(data.notifications || []);
        setBadge(typeof data.unread_count === 'number' ? data.unread_count : 0);
      })
      .catch(function () {
        /* silencioso */
      });
  }

  function markAllRead(e) {
    e.preventDefault();
    if (!cfg.readAllUrl) return;
    fetch(cfg.readAllUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: '{}',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function () {
        load();
      })
      .catch(function () {});
  }

  document.addEventListener('DOMContentLoaded', function () {
    load();

    var markAll = document.querySelector('[data-notification-action="mark-all-read"]');
    if (markAll) {
      markAll.addEventListener('click', markAllRead);
    }

    var dropdown = document.querySelector('.notification-dropdown');
    if (dropdown) {
      dropdown.addEventListener('show.bs.dropdown', function () {
        load();
      });
    }

    setInterval(load, 120000);
  });
})();
