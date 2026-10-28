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
	var fabEl = null;
	var live = null;

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
		var decided = hasDecision();
		mask = (mask & fullMask) | NECESSARY;
		state = { v: cfg.v, mask: mask, ts: Math.floor(Date.now() / 1000), id: (state && state.id) || uuid() };
		d.cookie = COOKIE + '=v' + state.v + '.' + mask + '.' + state.ts + '.' + state.id +
			';path=/;max-age=' + cfg.days * 86400 + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
		fab();
		hide();
		log(mask, decided && prev & ~mask ? 4 : mask === fullMask ? 1 : mask === NECESSARY ? 2 : 3);

		// Уже исполненный скрипт не выгрузить: чистим его cookie и перезагружаем страницу.
		if (prev & ~mask) {
			purge(prev & ~mask);
			location.reload();
			return;
		}
		// Незаблокированный код темы уже проверил наличие библиотеки — подключаем её с перезагрузкой.
		if (needsReload(mask & ~prev)) {
			location.reload();
			return;
		}
		activate(mask);
		emit(mask);
		announce(cfg.text.saved);
	}

	// Вежливое объявление для скринридера: баннер скрылся, выбор принят.
	function announce(text) {
		if (!live) {
			live = el('div', 'dptn-cc-sr');
			live.setAttribute('role', 'status');
			d.body.appendChild(live);
		}
		live.textContent = '';
		setTimeout(function () { live.textContent = text; }, 50);
	}

	// Плавающая кнопка «Настройки cookie»: появляется после выбора, пока баннер скрыт.
	function fab() {
		if (!cfg.fab || !hasDecision()) return;
		if (!fabEl) {
			fabEl = el('button', 'dptn-cc-fab dptn-cc-fab--' + cfg.fab);
			fabEl.type = 'button';
			fabEl.setAttribute('aria-label', cfg.text.fab);
			fabEl.title = cfg.text.fab;
			fabEl.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">' +
				'<path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M21 12.5A9 9 0 1 1 11.5 3a3 3 0 0 0 3.5 3.5 3 3 0 0 0 3.5 3.5 3 3 0 0 0 2.5 2.5z"/>' +
				'<circle cx="8.5" cy="10" r="1.3" fill="currentColor"/><circle cx="12" cy="15.5" r="1.3" fill="currentColor"/>' +
				'<circle cx="16" cy="12.5" r="1" fill="currentColor"/><circle cx="7.5" cy="15" r="1" fill="currentColor"/></svg>';
			fabEl.addEventListener('click', function () { open(true); });
			d.body.appendChild(fabEl);
		}
		fabEl.hidden = !!(ui && !ui.root.hidden);
	}

	// Журнал согласий. Строка уходит как text/plain — без предварительного CORS-запроса,
	// и sendBeacon доставит её, даже если следом страница перезагрузится.
	// Действие: 1 — принял все, 2 — только необходимые, 3 — свой выбор, 4 — отозвал часть.
	function log(mask, action) {
		if (!cfg.log) return;
		var body = JSON.stringify({ id: state.id, v: state.v, m: mask, a: action });
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(cfg.log, body)) return;
			fetch(cfg.log, { method: 'POST', body: body, keepalive: true, credentials: 'omit' });
		} catch (e) {}
	}

	function needsReload(granted) {
		return Array.prototype.some.call(
			d.querySelectorAll('script[type="text/plain"][data-dptn-cc-reload]'),
			function (el) { return bitOf(el.getAttribute('data-dptn-cc')) & granted; }
		);
	}

	// Заглушка до согласия: «Разрешить и показать» или открыть настройки.
	function makePh(name, bit, height) {
		var ph = el('div', 'dptn-cc-ph');
		ph.style.minHeight = (height || 200) + 'px';
		ph.appendChild(el('p', 'dptn-cc-ph__text', cfg.text.embed.replace('%s', name || '')));

		var actions = el('div', 'dptn-cc-ph__actions');
		var allow = button('allow', cfg.text.embed_btn);
		var more = button('more', cfg.text.settings);
		allow.addEventListener('click', function () { save(currentMask() | bit); });
		more.addEventListener('click', function () { open(true); });
		actions.appendChild(allow);
		actions.appendChild(more);
		ph.appendChild(actions);
		return ph;
	}

	function placeholders(mask) {
		// Iframe: заглушка встаёт на его место.
		Array.prototype.forEach.call(d.querySelectorAll('iframe[data-dptn-cc-src]'), function (f) {
			var bit = bitOf(f.getAttribute('data-dptn-cc'));
			if (!bit || bit & mask || f._dptnPh) return;

			var ph = makePh(f.getAttribute('data-dptn-cc-name'), bit, f.offsetHeight);
			if (f.offsetWidth) ph.style.maxWidth = f.offsetWidth + 'px';
			f.parentNode.insertBefore(ph, f);
			f.style.display = 'none';
			f._dptnPh = ph;
		});

		// Блоки, которые наполняет заблокированный скрипт (например, контейнер карты): заглушка внутри.
		(cfg.ph || []).forEach(function (p) {
			var bit = bitOf(p.cat);
			if (!bit || bit & mask) return;
			var boxes;
			try {
				boxes = d.querySelectorAll(p.sel);
			} catch (e) {
				return; // некорректный селектор из настроек
			}
			Array.prototype.forEach.call(boxes, function (box) {
				if (box._dptnPh) return;
				box._dptnPh = makePh(p.name, bit, box.offsetHeight);
				box._dptnPh.className += ' dptn-cc-ph--fill';
				box._dptnPh._dptnBit = bit;
				box.appendChild(box._dptnPh);
			});
		});
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
		Array.prototype.forEach.call(d.querySelectorAll('iframe[data-dptn-cc-src]'), function (f) {
			if (!(bitOf(f.getAttribute('data-dptn-cc')) & mask)) return;
			f.src = f.getAttribute('data-dptn-cc-src');
			f.removeAttribute('data-dptn-cc-src');
			f.style.display = '';
			if (f._dptnPh) f._dptnPh.parentNode.removeChild(f._dptnPh);
		});
		Array.prototype.forEach.call(d.querySelectorAll('.dptn-cc-ph--fill'), function (ph) {
			if (ph._dptnBit & mask) {
				ph.parentNode._dptnPh = null;
				ph.parentNode.removeChild(ph);
			}
		});

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
			a.appendChild(el('span', 'dptn-cc-sr', ' ' + t.new_tab));
			links.appendChild(a);
		});
		if (links.children.length) box.appendChild(links);

		var prefs = el('div', 'dptn-cc__prefs');
		prefs.id = 'dptn-cc-prefs';
		prefs.hidden = true;
		prefs.setAttribute('role', 'group');
		prefs.setAttribute('aria-label', t.settings);
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
		// ID согласия: по нему посетитель может запросить запись из журнала.
		var idLine = el('p', 'dptn-cc__id');
		prefs.appendChild(idLine);
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
			// Открытая панель превращает кнопку в «Сохранить выбор» — это уже не переключатель.
			if (open) bSettings.removeAttribute('aria-expanded');
			else bSettings.setAttribute('aria-expanded', 'false');
			if (open) {
				idLine.textContent = state ? t.id + ': ' + state.id : '';
				idLine.hidden = !state;
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

		// Первым в body: скринридер и Tab доходят до баннера раньше содержимого страницы.
		d.body.insertBefore(root, d.body.firstChild);
		return { root: root, box: box, togglePrefs: togglePrefs };
	}

	function open(withPrefs) {
		ui = ui || build();
		ui.togglePrefs(!!withPrefs);
		ui.root.hidden = false;
		if (fabEl) fabEl.hidden = true;
		// При автопоказе внизу экрана фокус не перехватываем, чтобы не мешать чтению страницы.
		if (withPrefs || cfg.pos === 'center') {
			lastFocus = d.activeElement;
			ui.box.focus({ preventScroll: true });
		}
	}

	function hide() {
		if (!ui) return;
		var hadFocus = ui.root.contains(d.activeElement);
		ui.root.hidden = true;
		if (fabEl) fabEl.hidden = false;
		// Возвращаем фокус туда, откуда открыли; иначе — на плавающую кнопку, чтобы он не терялся.
		if (lastFocus && lastFocus.focus && lastFocus !== d.body) lastFocus.focus({ preventScroll: true });
		else if (hadFocus && fabEl) fabEl.focus({ preventScroll: true });
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
	placeholders(mask);
	emit(mask);
	if (!hasDecision()) open(false);
	else fab();
})(window, document);
