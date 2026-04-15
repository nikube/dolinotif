/* Copyright (C) 2026 DoliNotif contributors
 *
 * Licensed under the GNU General Public License v3 or later (GPL-3.0-or-later)
 * with the Commons Clause restriction.
 *
 * Commons Clause: you may not Sell the Software.
 */

/* DoliNotif — vanilla JS, ES5-compatible.
 * Handles: polling, bell dropdown, mark-read, mark-all-read, toasts on new items.
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
		var toastCt     = document.getElementById('dolinotif-toast-container');

		var LABELS = {
			noNotifications: 'No notifications',
			loadError:       'Unable to load notifications',
			now:             'just now',
			minutesAgo:      ' min ago',
			hoursAgo:        ' h ago',
			daysAgo:         ' d ago'
		};

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

		function parseSqlDate(s) {
			if (!s) return null;
			// 'YYYY-MM-DD HH:MM:SS' → Date (interpret as local time)
			var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/.exec(s);
			if (!m) return null;
			return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]);
		}

		function relTime(sqlDate) {
			var d = parseSqlDate(sqlDate);
			if (!d) return '';
			var diff = Math.floor((Date.now() - d.getTime()) / 1000);
			if (diff < 60) return LABELS.now;
			if (diff < 3600) return Math.floor(diff / 60) + LABELS.minutesAgo;
			if (diff < 86400) return Math.floor(diff / 3600) + LABELS.hoursAgo;
			return Math.floor(diff / 86400) + LABELS.daysAgo;
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

		/* ------------ toast ------------ */

		function showToast(item) {
			if (!toastCt) return;
			var t = document.createElement('div');
			t.className = 'dolinotif-toast ' + (item.type || 'info');
			var h = '<div class="dolinotif-toast-title">' + escapeHtml(item.title || '') + '</div>';
			if (item.message) {
				h += '<div class="dolinotif-toast-msg">' + escapeHtml(item.message) + '</div>';
			}
			t.innerHTML = h;
			t.addEventListener('click', function () {
				if (item.url) {
					markRead(item.rowid, function () { window.location.href = item.url; });
				} else {
					markRead(item.rowid);
					if (t.parentNode) t.parentNode.removeChild(t);
				}
			});
			toastCt.appendChild(t);
			setTimeout(function () {
				if (t.parentNode) t.parentNode.removeChild(t);
			}, 5000);
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
				html += '  <span class="dolinotif-dot ' + escapeHtml(type) + '"></span>';
				html += '  <div class="dolinotif-body">';
				html += '    <div class="dolinotif-item-title">' + escapeHtml(it.title || '') + '</div>';
				if (it.message) {
					html += '    <div class="dolinotif-item-msg">' + escapeHtml(it.message) + '</div>';
				}
				html += '    <div class="dolinotif-item-meta">';
				html += '      <span>' + escapeHtml(relTime(it.date_creation)) + '</span>';
				if (it.category) {
					html += '      <span class="dolinotif-tag">' + escapeHtml(it.category) + '</span>';
				}
				html += '    </div>';
				html += '  </div>';
				if (it.url) {
					html += '  <a href="' + escapeHtml(it.url) + '" class="dolinotif-link" data-id="' + (it.rowid | 0) + '" title="Open">&rarr;</a>';
				}
				html += '</div>';
			}
			listEl.innerHTML = html;
		}

		function openDropdown() {
			if (!dropdown) return;
			dropdown.style.display = 'block';
			loadList();
		}
		function closeDropdown() {
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

		function markAllRead() {
			xhrPost(AJAX_URL + '?action=markallread', { token: TOKEN }, function (err) {
				if (err) return;
				setBadge(0);
				if (isOpen()) loadList();
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
					for (var i = 0; i < data['new'].length; i++) {
						showToast(data['new'][i]);
					}
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

		if (markAllBtn) {
			markAllBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				markAllRead();
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
