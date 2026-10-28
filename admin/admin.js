/*! DPTN Cookie Consent — страница настроек | dptn.pro */
(function ($) {
	'use strict';

	var preview = document.getElementById('dptn-cc-preview');

	function setVar(name, value) {
		if (preview && name) preview.style.setProperty(name, value);
		// Палитра вызывает change до записи значения в поле — проверяем на следующем тике.
		setTimeout(checkContrast, 0);
	}

	// Контраст по WCAG 2.x: текст должен отличаться от фона не меньше чем в 4,5 раза.
	function luminance(hex) {
		hex = hex.replace('#', '');
		if (hex.length === 3) hex = hex.replace(/./g, '$&$&');
		return [0, 2, 4].reduce(function (sum, i, k) {
			var c = parseInt(hex.substr(i, 2), 16) / 255;
			c = c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
			return sum + c * [0.2126, 0.7152, 0.0722][k];
		}, 0);
	}

	function ratio(a, b) {
		var l1 = luminance(a), l2 = luminance(b);
		return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
	}

	function color(id) {
		var input = document.getElementById('dptn-cc-' + id);
		return input && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(input.value) ? input.value : null;
	}

	function checkContrast() {
		var box = document.getElementById('dptn-cc-contrast');
		if (!box) return;
		var pairs = [
			['Текст на кнопках', 'accent_fg', 'accent'],
			['Текст баннера', 'fg', 'bg'],
			['Ссылки и кнопка «Настроить»', 'accent', 'bg']
		];
		var problems = pairs.filter(function (p) {
			var a = color(p[1]), b = color(p[2]);
			return a && b && (p[3] = ratio(a, b)) < 4.5;
		});
		box.hidden = !problems.length;
		box.innerHTML = problems.length ? '<p><strong>Низкий контраст.</strong> По WCAG AA текст должен быть контрастнее фона минимум в 4,5 раза:</p><ul>' +
			problems.map(function (p) { return '<li>' + p[0] + ': ' + p[3].toFixed(1).replace('.', ',') + ':1</li>'; }).join('') + '</ul>' : '';
	}

	$('.dptn-cc-color').wpColorPicker({
		change: function (e, ui) {
			setVar(e.target.getAttribute('data-var'), ui.color.toString());
		}
	});

	checkContrast();

	$('[data-var-px]').on('input', function () {
		setVar(this.getAttribute('data-var-px'), (parseInt(this.value, 10) || 0) + 'px');
	});

	$('input[name$="[mode]"]').on('change', function () {
		$('#dptn-cc-notice-warning').prop('hidden', this.value !== 'notice');
	});

	// Таблица «Скрипты темы и плагинов»: новая строка из шаблона, по желанию заполненная из результатов поиска.
	var rows = document.getElementById('dptn-cc-scripts');
	var tpl = document.getElementById('dptn-cc-script-row');

	function addRow(values) {
		var tbody = document.createElement('tbody');
		tbody.innerHTML = tpl.innerHTML.replace(/__i__/g, 'n' + Date.now());
		var tr = tbody.firstElementChild;
		Object.keys(values).forEach(function (field) {
			var input = tr.querySelector('[data-field="' + field + '"]');
			if (input) input.value = values[field];
		});
		rows.appendChild(tr);
		tr.querySelector('[data-field="handle"]').focus();
	}

	if (rows && tpl) {
		$('#dptn-cc-add-row').on('click', function () {
			addRow({});
		});
		$('[data-dptn-cc-add]').on('click', function () {
			addRow(JSON.parse(this.getAttribute('data-dptn-cc-add')));
			$(this).replaceWith('Добавлен, сохраните изменения');
		});
	}
})(jQuery);
