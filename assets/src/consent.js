/*! DPTN Cookie Consent | dptn.pro | GPL-2.0-or-later */
(function (w, d) {
	'use strict';

	var cfgEl = d.getElementById('dptn-cc-config');
	if (!cfgEl || w.dptnCC) return;

	var cfg;
	try {
		cfg = JSON.parse(cfgEl.textContent);
	} catch (e) {
		return;
	}

	var COOKIE = 'dptn_cc';
	var NECESSARY = 1;
	var bits = {};
	var fullMask = 0;
	var listeners = [];
	var state = readCookie();
	var ui = null;
	var lastFocus = null;

	cfg.cats.forEach(function (c) {
		bits[c.id] = c.bit;
		fullMask |= c.bit;
		c.services = cfg.svc.filter(function (s) { return s.cat === c.id; }).map(function (s) { return s.name; });
	});

	function bitOf(cat) {
		return bits[cat] || 0;
	}

	// Формат: v{версия}.{маска}.{unix-время}.{uuid}
	function readCookie() {
		var m = d.cookie.match(/(?:^|;\s*)dptn_cc=v(\d+)\.(\d+)\.(\d+)\.([0-9a-f-]{36})(?:;|$)/);
		return m ? { v: +m[1], mask: +m[2], ts: +m[3], id: m[4] } : null;
	}

	function hasDecision() {
		return !!state && state.v === cfg.v;
	}

	function currentMask() {
		if (hasDecision()) return (state.mask & fullMask) | NECESSARY;
		return cfg.mode === 'notice' ? fullMask : NECESSARY;
	}

	function uuid() {
		var c = w.crypto;
		if (c.randomUUID) return c.randomUUID(); // только в secure context
		var b = c.getRandomValues(new Uint8Array(16));
		b[6] = (b[6] & 15) | 64;
		b[8] = (b[8] & 63) | 128;
		var h = Array.prototype.map.call(b, function (x) { return (x + 256).toString(16).slice(1); }).join('');
		return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
	}

	function save(mask) {
		var prev = currentMask();
		mask = (mask & fullMask) | NECESSARY;
		state = { v: cfg.v, mask: mask, ts: Math.floor(Date.now() / 1000), id: (state && state.id) || uuid() };
		d.cookie = COOKIE + '=v' + state.v + '.' + mask + '.' + state.ts + '.' + state.id +
			';path=/;max-age=' + cfg.days * 86400 + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
		hide();

		// Уже исполненный скрипт не выгрузить: чистим его cookie и перезагружаем страницу.
		if (prev & ~mask) {
			purge(prev & ~mask);
			location.reload();
			return;
		}
		activate(mask);
		emit(mask);
	}

	function purge(revoked) {
		var prefixes = [];
		cfg.svc.forEach(function (s) {
			if (bitOf(s.cat) & revoked) prefixes = prefixes.concat(s.cookies || []);
		});
		if (!prefixes.length) return;

		var match = function (name) {
			return name !== COOKIE && prefixes.some(function (p) { return name.indexOf(p) === 0; });
		};

		// Cookie могли поставить на любой родительский домен: www.site.ru → .site.ru.
		var host = location.hostname.split('.');
		var domains = [''];
		for (var i = 0; i < host.length - 1; i++) domains.push(';domain=.' + host.slice(i).join('.'));

		d.cookie.split(';').forEach(function (pair) {
			var name = pair.split('=')[0].trim();
			if (!match(name)) return;
			domains.forEach(function (dom) { d.cookie = name + '=;path=/;max-age=0' + dom; });
		});

		try {
			Object.keys(localStorage).forEach(function (k) { if (match(k)) localStorage.removeItem(k); });
		} catch (e) {}
	}

	// Запускаем заблокированные скрипты по порядку: внешний скрипт без async
	// дожидается загрузки, чтобы следующий за ним inline-код нашёл свои зависимости.
	function activate(mask) {
		var queue = Array.prototype.filter.call(
			d.querySelectorAll('script[type="text/plain"][data-dptn-cc]'),
			function (el) { return bitOf(el.getAttribute('data-dptn-cc')) & mask; }
		);

		(function next() {
			var el = queue.shift();
			if (!el) return;

			var s = d.createElement('script');
			Array.prototype.forEach.call(el.attributes, function (a) {
				if (a.name !== 'type' && a.name !== 'data-type') s.setAttribute(a.name, a.value);
			});
			if (el.hasAttribute('data-type')) s.type = el.getAttribute('data-type');

			var wait = s.src && !el.hasAttribute('async');
			if (wait) {
				s.async = false;
				s.onload = s.onerror = next;
			} else if (!s.src) {
				s.text = el.text;
			}
			el.parentNode.replaceChild(s, el);
			if (!wait) next();
		})();
	}

	function emit(mask) {
		var cats = cfg.cats.filter(function (c) { return c.bit & mask; }).map(function (c) { return c.id; });
		listeners.forEach(function (cb) {
			try { cb(cats); } catch (e) {}
		});
		try {
			d.dispatchEvent(new CustomEvent('dptn-cc:change', { detail: { categories: cats, mask: mask } }));
		} catch (e) {}
	}

	function el(tag, cls, text) {
		var e = d.createElement(tag);
		if (cls) e.className = cls;
		if (text) e.textContent = text;
		return e;
	}

	function button(act, text) {
		var b = el('button', 'dptn-cc__btn', text);
		b.type = 'button';
		b.setAttribute('data-act', act);
		return b;
	}

	function build() {
		var t = cfg.text;
		var root = el('div', 'dptn-cc dptn-cc--' + cfg.pos);
		root.setAttribute('role', 'dialog');
		root.setAttribute('aria-labelledby', 'dptn-cc-title');
		root.setAttribute('aria-describedby', 'dptn-cc-text');
		if (cfg.pos === 'center') root.setAttribute('aria-modal', 'true');

		var box = el('div', 'dptn-cc__box');
		box.tabIndex = -1;

		var title = el('p', 'dptn-cc__title', t.title);
		title.id = 'dptn-cc-title';
		var body = el('p', 'dptn-cc__text', t.body);
		body.id = 'dptn-cc-text';
		box.appendChild(title);
		box.appendChild(body);

		var links = el('p', 'dptn-cc__links');
		['consent', 'policy'].forEach(function (k) {
			if (!cfg.links[k]) return;
			var a = el('a', '', t[k]);
			a.href = cfg.links[k];
			a.target = '_blank';
			a.rel = 'noopener';
			links.appendChild(a);
		});
		if (links.children.length) box.appendChild(links);

		var prefs = el('div', 'dptn-cc__prefs');
		prefs.id = 'dptn-cc-prefs';
		prefs.hidden = true;
		var boxes = [];
		cfg.cats.forEach(function (c) {
			var row = el('label', 'dptn-cc__cat');
			var cb = el('input');
			cb.type = 'checkbox';
			cb.value = c.bit;
			if (c.req) {
				cb.checked = true;
				cb.disabled = true;
			} else {
				boxes.push(cb);
			}
			var info = el('span', 'dptn-cc__cat-info');
			var name = el('span', 'dptn-cc__cat-name', c.label);
			var desc = el('span', 'dptn-cc__cat-desc', c.desc + (c.services.length ? ' ' + t.services + ': ' + c.services.join(', ') + '.' : ''));
			name.id = 'dptn-cc-cat-' + c.id;
			desc.id = name.id + '-desc';
			cb.setAttribute('aria-labelledby', name.id);
			cb.setAttribute('aria-describedby', desc.id);
			info.appendChild(name);
			info.appendChild(desc);
			row.appendChild(cb);
			row.appendChild(info);
			prefs.appendChild(row);
		});
		box.appendChild(prefs);

		var actions = el('div', 'dptn-cc__actions');
		var bSettings = button('settings', t.settings);
		bSettings.setAttribute('aria-controls', 'dptn-cc-prefs');
		bSettings.setAttribute('aria-expanded', 'false');
		actions.appendChild(button('necessary', t.necessary));
		actions.appendChild(bSettings);
		actions.appendChild(button('accept', t.accept));
		box.appendChild(actions);
		root.appendChild(box);

		var togglePrefs = function (open) {
			prefs.hidden = !open;
			bSettings.textContent = open ? t.save : t.settings;
			bSettings.setAttribute('aria-expanded', String(open));
			if (open) {
				var mask = currentMask();
				boxes.forEach(function (cb) { cb.checked = !!(mask & +cb.value); });
			}
		};

		root.addEventListener('click', function (e) {
			var b = e.target.closest('[data-act]');
			if (!b) return;
			var act = b.getAttribute('data-act');
			if (act === 'accept') save(fullMask);
			else if (act === 'necessary') save(NECESSARY);
			else if (prefs.hidden) togglePrefs(true);
			else save(boxes.reduce(function (m, cb) { return cb.checked ? m | +cb.value : m; }, NECESSARY));
		});

		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !prefs.hidden) {
				togglePrefs(false);
				bSettings.focus();
			}
			// В модальном режиме фокус не уходит за пределы окна.
			if (e.key === 'Tab' && cfg.pos === 'center') {
				var f = box.querySelectorAll('a[href], button, input:not([disabled])');
				var visible = Array.prototype.filter.call(f, function (x) { return x.offsetParent !== null; });
				var first = visible[0];
				var last = visible[visible.length - 1];
				if (e.shiftKey && (d.activeElement === first || d.activeElement === box)) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && d.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			}
		});

		d.body.appendChild(root);
		return { root: root, box: box, togglePrefs: togglePrefs };
	}

	function open(withPrefs) {
		ui = ui || build();
		ui.togglePrefs(!!withPrefs);
		ui.root.hidden = false;
		// При автопоказе внизу экрана фокус не перехватываем, чтобы не мешать чтению страницы.
		if (withPrefs || cfg.pos === 'center') {
			lastFocus = d.activeElement;
			ui.box.focus({ preventScroll: true });
		}
	}

	function hide() {
		if (!ui) return;
		ui.root.hidden = true;
		if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
		lastFocus = null;
	}

	w.dptnCC = {
		has: function (cat) { return !!(bitOf(cat) & currentMask()); },
		show: function () { open(true); },
		onChange: function (cb) { if (typeof cb === 'function') listeners.push(cb); },
		id: function () { return state ? state.id : null; }
	};

	// Ссылка «Настройки cookie» в любом месте сайта: <a href="#dptn-cc-settings">.
	d.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href$="#dptn-cc-settings"]');
		if (a) {
			e.preventDefault();
			open(true);
		}
	});

	var mask = currentMask();
	// Дочищаем то, что сервис успел записать при выгрузке страницы после отзыва.
	if (hasDecision()) purge(fullMask & ~mask);
	activate(mask);
	emit(mask);
	if (!hasDecision()) open(false);
})(window, document);
