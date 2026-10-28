<?php
/**
 * Вкладка «Справка».
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="dptn-cc-admin__help">
	<h2>Чек-лист по 152-ФЗ</h2>
	<p>Плагин закрывает техническую часть. Остальное делается вне его:</p>
	<ul class="dptn-cc-admin__list">
		<li>Отдельная страница «Согласие на обработку cookie и метрических данных» выбрана на вкладке «Основное» (156-ФЗ, с 01.09.2025).</li>
		<li>Опубликована политика обработки персональных данных (ч. 2 ст. 18.1).</li>
		<li>Подано уведомление в Роскомнадзор об обработке персональных данных (ст. 22).</li>
		<li>Код счётчиков удалён из темы и других плагинов: их выводит этот плагин.</li>
		<li>Журнал согласий включён (вкладка «Журнал»): по ч. 3 ст. 9 получение согласия доказывает оператор.</li>
		<li>Сайт размещён на хостинге в РФ (ч. 5 ст. 18, с 01.07.2025).</li>
		<li>В формах обратной связи есть отдельный чекбокс согласия, не отмеченный по умолчанию.</li>
		<li>Тексты согласия и политики проверены юристом.</li>
	</ul>

	<h2>Ссылка «Настройки cookie»</h2>
	<p>Посетитель должен иметь возможность отозвать согласие. Разместите ссылку, например, в подвале сайта:</p>
	<ul class="dptn-cc-admin__list">
		<li>Шорткод: <code>[dptn_cc_settings]</code> или <code>[dptn_cc_settings text="Изменить настройки cookie"]</code></li>
		<li>Пункт меню «Произвольная ссылка» с адресом <code>#dptn-cc-settings</code></li>
		<li>Плавающая кнопка: «Внешний вид» → «Плавающая кнопка».</li>
		<li>В шаблоне: <code>&lt;a href="#dptn-cc-settings"&gt;Настройки cookie&lt;/a&gt;</code></li>
	</ul>

	<h2>Для разработчиков</h2>
	<ul class="dptn-cc-admin__list">
		<li>Заблокировать свой скрипт до согласия: <code>&lt;script type="text/plain" data-dptn-cc="marketing"&gt;…&lt;/script&gt;</code>. Категории: <code>analytics</code>, <code>marketing</code>, <code>functional</code>.</li>
		<li>Iframe из шаблона темы: <code>&lt;iframe data-dptn-cc="functional" data-dptn-cc-name="Яндекс.Карты" data-dptn-cc-src="…"&gt;</code> — до согласия покажется заглушка.</li>
		<li>Добавить сервис: фильтр <code>dptn_cc_services</code> (пример в <code>includes/class-services.php</code>).</li>
		<li>JS: <code>dptnCC.has('analytics')</code>, <code>dptnCC.show()</code>, событие <code>dptn-cc:change</code>.</li>
		<li>PHP на некешируемых страницах: <code>dptn_cc_has_consent( 'analytics' )</code>.</li>
		<li>Стили темы: переопределите переменные <code>--dptn-cc-*</code> у <code>.dptn-cc</code>.</li>
		<li>Оптимизаторы JS (WP Rocket, LiteSpeed Cache, Autoptimize, Cloudflare): скрипт баннера исключается из отложенного запуска автоматически. Если используете другой оптимизатор, исключите <code>dptn-cc-js</code> и <code>data-dptn-cc-writer</code>.</li>
	</ul>
</div>
