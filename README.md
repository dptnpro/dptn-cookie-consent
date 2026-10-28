# DPTN Cookie Consent

Разработчик: DPTN — https://dptn.pro

Лёгкий баннер согласия на cookie для сайтов РФ. Совместим со страничным кешем: HTML одинаков для всех, решение о запуске сервисов принимается в браузере.

Статус: этап 1 из спецификации (ядро, баннер, пресет Яндекс.Метрики). Страницы настроек пока нет.

## Установка

Скопировать папку в `wp-content/plugins/` без `node_modules`, активировать.

## Настройка до появления админки

Через фильтр в `functions.php` темы:

```php
add_filter( 'dptn_cc_settings', function ( $s ) {
	$s['consent_url'] = home_url( '/soglasie-cookie/' );
	$s['policy_url']  = home_url( '/privacy/' );
	$s['services']['yandex_metrika'] = array(
		'enabled'    => true,
		'counter_id' => '12345678',
		'webvisor'   => false, // вебвизор записывает ввод в формы
		'clickmap'   => true,
		'ecommerce'  => false,
	);
	// $s['mode']     = 'notice';  // сервисы грузятся сразу (правовой риск)
	// $s['position'] = 'center';  // bottom | bottom-left | bottom-right | center
	// $s['version']  = 2;         // показать баннер всем заново
	return $s;
} );
```

Старый код Метрики из темы нужно удалить, иначе он будет работать без согласия.

## Свои сервисы

- Через фильтр `dptn_cc_services` (см. комментарий в `includes/class-services.php`).
- Вручную в шаблоне: `<script type="text/plain" data-dptn-cc="marketing">…</script>`. Для `type="module"` добавьте `data-type="module"`.

## API

```js
dptnCC.has('analytics');            // разрешена ли категория
dptnCC.show();                      // открыть настройки
dptnCC.onChange(cats => {});        // подписка на изменение выбора
document.addEventListener('dptn-cc:change', e => e.detail.categories);
```

Ссылка «Настройки cookie» в любом месте сайта: `<a href="#dptn-cc-settings">Настройки cookie</a>`.

PHP (только на некешируемых страницах): `dptn_cc_has_consent( 'analytics' )`, `dptn_cc_get_consent()`.

## Сборка

```bash
npm install && npm run build && npm run size
```

Без сборки плагин берёт исходники из `assets/src/`.
