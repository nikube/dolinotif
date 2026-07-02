/* Copyright (C) 2026 DoliNotif contributors
 *
 * Licensed under the GNU General Public License v3 or later (GPL-3.0-or-later)
 * with the Commons Clause restriction.
 *
 * Commons Clause: you may not Sell the Software.
 */

/* DoliNotif — vanilla JS, ES5-compatible.
 * Handles: polling, bell dropdown, mark-read, auto-open drawer on new items.
 * No jQuery dependency.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		var wrap = document.getElementById('dolinotif-wrap');
		if (!wrap) return;

		var AJAX_URL   = wrap.getAttribute('data-ajax') || '';
		var TOKEN      = wrap.getAttribute('data-token') || '';
		var POLLING    = parseInt(wrap.getAttribute('data-polling') || '30', 10);
		var MAX_ITEMS  = parseInt(wrap.getAttribute('data-max') || '15', 10);
		if (!AJAX_URL) return;
		if (isNaN(POLLING) || POLLING < 5) POLLING = 30;

		var bell        = document.getElementById('dolinotif-bell');
		var badge       = document.getElementById('dolinotif-badge');
		var dropdown    = document.getElementById('dolinotif-dropdown');
		var listEl      = document.getElementById('dolinotif-list');
		var markAllBtn  = document.getElementById('dolinotif-markall');

		// Translated labels injected by the hook (data-labels JSON);
		// English fallbacks if absent.
		var LABELS = {
			noNotifications:   'No notifications',
			loadError:         'Unable to load notifications',
			now:               'just now',
			minutesAgo:        '%s min ago',
			hoursAgo:          '%s h ago',
			daysAgo:           '%s d ago',
			moreNotifications: 'more notifications'
		};
		try {
			var lbl = JSON.parse(wrap.getAttribute('data-labels') || '{}');
			for (var lk in lbl) {
				if (Object.prototype.hasOwnProperty.call(lbl, lk) && lbl[lk]) LABELS[lk] = lbl[lk];
			}
		} catch (e) {}

		/* ------------ small helpers ------------ */

		function xhrGet(url, cb) {
			var x = new XMLHttpRequest();
			x.open('GET', url, true);
			x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			x.onreadystatechange = function () {
				if (x.readyState !== 4) return;
				if (x.status >= 200 && x.status < 300) {
					try { cb(null, JSON.parse(x.responseText)); }
					catch (e) { cb(e); }
				} else { cb(new Error('HTTP ' + x.status)); }
			};
			x.send();
		}

		function xhrPost(url, data, cb) {
			var x = new XMLHttpRequest();
			x.open('POST', url, true);
			x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
			x.onreadystatechange = function () {
				if (x.readyState !== 4) return;
				if (x.status >= 200 && x.status < 300) {
					try { cb(null, JSON.parse(x.responseText)); }
					catch (e) { cb(e); }
				} else { cb(new Error('HTTP ' + x.status)); }
			};
			var body = '';
			for (var k in data) {
				if (!Object.prototype.hasOwnProperty.call(data, k)) continue;
				if (body.length) body += '&';
				body += encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
			}
			x.send(body);
		}

		function escapeHtml(s) {
			if (s === null || s === undefined) return '';
			return String(s)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		function relTime(ageSeconds) {
			// Age is computed SERVER-side (ajax 'age' field): parsing the raw
			// DB datetime in the browser shifted every timestamp by the
			// server/browser timezone offset.
			var diff = ageSeconds | 0;
			if (diff < 60) return LABELS.now;
			if (diff < 3600) return fmtRel(LABELS.minutesAgo, Math.floor(diff / 60));
			if (diff < 86400) return fmtRel(LABELS.hoursAgo, Math.floor(diff / 3600));
			return fmtRel(LABELS.daysAgo, Math.floor(diff / 86400));
		}

		function fmtRel(tpl, n) {
			if (tpl.indexOf('__N__') !== -1) return tpl.replace('__N__', String(n));
			if (tpl.indexOf('%s') !== -1) return tpl.replace('%s', String(n));
			return String(n) + ' ' + tpl;
		}

		function typeIcon(type) {
			if (type === 'success') return '✓';
			if (type === 'error') return '✕';
			if (type === 'warning') return '!';
			return 'i';
		}

		/* ------------ badge ------------ */

		function setBadge(n) {
			if (!badge) return;
			if (n > 0) {
				badge.textContent = n > 99 ? '99+' : String(n);
				badge.style.display = '';
			} else {
				badge.style.display = 'none';
			}
		}

		/* ------------ session check time ------------ */

		function getLastCheck() {
			try { return sessionStorage.getItem('dolinotif_lastCheck') || ''; } catch (e) { return ''; }
		}
		function setLastCheck(v) {
			try { sessionStorage.setItem('dolinotif_lastCheck', v); } catch (e) {}
		}

		/* ------------ auto-open drawer on new notifications ------------
		 * No separate toast: the drawer IS the notification surface. When a
		 * new item arrives, it slides open, then closes itself after a few
		 * seconds if untouched — and the badge KEEPS counting (auto-open is
		 * not "read"; only engaging with the drawer is). */

		var autoCloseTimer = null;
		var AUTO_CLOSE_MS = 8000;

		function armAutoClose() {
			if (autoCloseTimer) clearTimeout(autoCloseTimer);
			autoCloseTimer = setTimeout(function () {
				autoCloseTimer = null;
				closeDropdown();
			}, AUTO_CLOSE_MS);
		}

		function autoOpenDropdown() {
			if (!dropdown) return;
			if (isOpen()) {
				loadList();
				if (autoCloseTimer) armAutoClose();
				return;
			}
			positionDropdown();
			dropdown.style.display = 'block';
			loadList();
			armAutoClose();
		}

		// The drawer is position:fixed (so it can hug the viewport's right
		// edge below the nav bar); align its top just under the bell.
		function positionDropdown() {
			if (!dropdown || !bell) return;
			var r = bell.getBoundingClientRect();
			dropdown.style.top = Math.round(r.bottom + 22) + 'px';
		}

		/* ------------ dropdown rendering ------------ */

		function renderList(items) {
			if (!listEl) return;
			if (!items || !items.length) {
				listEl.innerHTML = '<div class="dolinotif-empty">' + escapeHtml(LABELS.noNotifications) + '</div>';
				return;
			}
			var html = '';
			for (var i = 0; i < items.length; i++) {
				var it = items[i];
				var unreadCls = it.is_read ? '' : ' unread';
				var type = it.type || 'info';
				html += '<div class="dolinotif-item' + unreadCls + '" data-id="' + (it.rowid | 0) + '" data-url="' + escapeHtml(it.url || '') + '">';
				html += '  <span class="dolinotif-item-icon ' + escapeHtml(type) + '">' + typeIcon(type) + '</span>';
				html += '  <div class="dolinotif-body">';
				html += '    <div class="dolinotif-item-title">' + escapeHtml(it.title || '') + '</div>';
				if (it.message) {
					html += '    <div class="dolinotif-item-msg">' + escapeHtml(it.message) + '</div>';
				}
				html += '    <div class="dolinotif-item-meta">';
				html += '      <span>' + escapeHtml(relTime(it.age)) + '</span>';
				if (it.category) {
					html += '      <span class="dolinotif-tag">' + escapeHtml(it.category) + '</span>';
				}
				html += '    </div>';
				html += '  </div>';
				if (!it.is_read) {
					html += '  <span class="dolinotif-unread-dot" aria-hidden="true"></span>';
				}
				if (it.url) {
					html += '  <a href="' + escapeHtml(it.url) + '" class="dolinotif-link" data-id="' + (it.rowid | 0) + '" title="Open">&rarr;</a>';
				}
				html += '</div>';
			}
			listEl.innerHTML = html;
		}

		function openDropdown() {
			if (!dropdown) return;
			if (autoCloseTimer) { clearTimeout(autoCloseTimer); autoCloseTimer = null; }
			positionDropdown();
			dropdown.style.display = 'block';
			loadList();
			// Opening the panel = notifications seen. The list just rendered
			// keeps its unread styling until the next open; only the badge
			// drops to zero (no list reload here on purpose).
			markAllRead(false);
		}
		function closeDropdown() {
			if (autoCloseTimer) { clearTimeout(autoCloseTimer); autoCloseTimer = null; }
			if (dropdown) dropdown.style.display = 'none';
		}
		function isOpen() {
			return dropdown && dropdown.style.display !== 'none';
		}

		function loadList() {
			xhrGet(AJAX_URL + '?action=list&limit=' + encodeURIComponent(MAX_ITEMS), function (err, data) {
				if (err) {
					if (listEl) listEl.innerHTML = '<div class="dolinotif-empty">' + escapeHtml(LABELS.loadError) + '</div>';
					return;
				}
				renderList(data);
			});
		}

		/* ------------ actions ------------ */

		function markRead(id, cb) {
			xhrPost(AJAX_URL + '?action=markread', { id: id, token: TOKEN }, function (err) {
				if (!err) {
					refreshCount();
				}
				if (cb) cb(err);
			});
		}

		function markAllRead(reloadList) {
			xhrPost(AJAX_URL + '?action=markallread', { token: TOKEN }, function (err) {
				if (err) return;
				setBadge(0);
				if (reloadList && isOpen()) loadList();
			});
		}

		function refreshCount() {
			var since = getLastCheck();
			var url = AJAX_URL + '?action=count';
			if (since) url += '&since=' + encodeURIComponent(since);
			xhrGet(url, function (err, data) {
				if (err || !data) return;
				setBadge(data.unread | 0);
				if (data.now) setLastCheck(data.now);
				if (data['new'] && data['new'].length) {
					autoOpenDropdown();
				}
			});
		}

		/* ------------ wiring ------------ */

		if (bell) {
			bell.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (isOpen()) closeDropdown(); else openDropdown();
			});
		}

		document.addEventListener('click', function (e) {
			if (!isOpen()) return;
			if (wrap.contains(e.target)) return;
			closeDropdown();
		});

		// Engaging with an auto-opened drawer cancels the auto-close and
		// counts as "seen" (badge drops), like a manual open would.
		if (dropdown) {
			dropdown.addEventListener('mouseenter', function () {
				if (autoCloseTimer) {
					clearTimeout(autoCloseTimer);
					autoCloseTimer = null;
					markAllRead(false);
				}
			});
		}

		// Legacy "mark all read" button (removed from the hook markup —
		// opening the panel marks everything read). Kept wired defensively
		// in case an older cached header still renders it.
		if (markAllBtn) {
			markAllBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				markAllRead(true);
			});
		}

		if (listEl) {
			listEl.addEventListener('click', function (e) {
				var linkEl = e.target;
				while (linkEl && linkEl !== listEl && !linkEl.classList.contains('dolinotif-link') && !linkEl.classList.contains('dolinotif-item')) {
					linkEl = linkEl.parentNode;
				}
				if (!linkEl || linkEl === listEl) return;

				if (linkEl.classList.contains('dolinotif-link')) {
					// Link icon: mark read, then let browser follow href.
					e.preventDefault();
					var lid = parseInt(linkEl.getAttribute('data-id') || '0', 10);
					var lurl = linkEl.getAttribute('href') || '';
					markRead(lid, function () { if (lurl) window.location.href = lurl; });
					return;
				}
				if (linkEl.classList.contains('dolinotif-item')) {
					var iid = parseInt(linkEl.getAttribute('data-id') || '0', 10);
					var iurl = linkEl.getAttribute('data-url') || '';
					markRead(iid, function () {
						if (iurl) window.location.href = iurl;
						else loadList();
					});
				}
			});
		}

		// Initial check + polling
		refreshCount();
		setInterval(refreshCount, POLLING * 1000);
	});
})();
