# Соглашение об именовании плагинов DPTN

Единый паттерн для всех плагинов WordPress от DPTN (https://dptn.pro).
Две цели: не конфликтовать с чужим кодом и однозначно указывать на автора.

## Схема: вендор + код продукта

Каждый идентификатор строится из двух частей:

- **Вендор** `dptn`, одинаковый для всех плагинов. Даёт авторство и защищает от чужого кода.
- **Код продукта**, 2–4 латинские буквы, уникальный внутри DPTN. Защищает ваши плагины друг от друга.

Одного вендорного префикса мало: два ваших плагина с функциями `dptn_get_settings()` упадут вместе. Поэтому код продукта обязателен везде, где имя попадает в общее пространство (глобальные функции, опции, хуки, CSS, cookie).

## Реестр кодов продуктов

Перед новым плагином добавьте строку и убедитесь, что код не занят.

| Код | Плагин | Slug | Namespace |
|---|---|---|---|
| `cc` | DPTN Cookie Consent | `dptn-cookie-consent` | `DPTN\CookieConsent` |

## Таблица идентификаторов

На примере `cc` (Cookie Consent):

| Что | Шаблон | Пример |
|---|---|---|
| Папка и slug | `dptn-{product-name}` | `dptn-cookie-consent` |
| Главный файл | `{slug}.php` | `dptn-cookie-consent.php` |
| Text Domain | = slug | `dptn-cookie-consent` |
| PHP namespace | `DPTN\{ProductName}` | `DPTN\CookieConsent\Settings` |
| Константы | `DPTN_{CODE}_*` | `DPTN_CC_VERSION` |
| Глобальные функции | `dptn_{code}_*` | `dptn_cc_has_consent()` |
| Хуки (actions/filters) | `dptn_{code}_*` | `dptn_cc_services` |
| Опции, мета, transients | `dptn_{code}_*` | `dptn_cc_settings` |
| Таблицы БД | `{$wpdb->prefix}dptn_{code}_*` | `wp_dptn_cc_log` |
| Cron-хуки | `dptn_{code}_*` | `dptn_cc_cleanup` |
| REST namespace | `dptn-{code}/v{n}` | `dptn-cc/v1` |
| Handle скриптов и стилей | `dptn-{code}[-part]` | `dptn-cc`, `dptn-cc-admin` |
| Slug страницы админки | `dptn-{code}[-part]` | `dptn-cc` |
| Capability (если своя) | `dptn_{code}_*` | `dptn_cc_manage` |
| CSS-классы (BEM) | `.dptn-{code}`, `__элемент`, `--модификатор` | `.dptn-cc__box` |
| CSS-переменные | `--dptn-{code}-*` | `--dptn-cc-accent` |
| HTML id / data-атрибуты | `dptn-{code}-*` / `data-dptn-{code}` | `data-dptn-cc="analytics"` |
| Cookie / localStorage | `dptn_{code}[_*]` | `dptn_cc` |
| JS global | `window.dptn{CODE}` | `window.dptnCC` |
| JS events | `dptn-{code}:{event}` | `dptn-cc:change` |
| Composer (если будет) | `dptn/{slug-без-dptn}` | `dptn/cookie-consent` |

Классы внутри namespace (`Settings`, `Frontend`) префикс не нужен: namespace уже уникален.

## Заголовок плагина

```php
/**
 * Plugin Name:       DPTN Cookie Consent
 * Plugin URI:        https://dptn.pro
 * Description:       … Разработчик: DPTN (dptn.pro).
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            DPTN
 * Author URI:        https://dptn.pro
 * License:           GPL-2.0-or-later
 * Text Domain:       dptn-cookie-consent
 * Update URI:        https://dptn.pro
 */
```

`Update URI` важен. Без него WordPress сверяет плагин по slug с каталогом wordpress.org и, если там появится плагин с таким же slug, предложит «обновление» чужим кодом. Убрать строку стоит только при публикации на wordpress.org.

## Правила

1. В глобальном пространстве нет ни одного имени без `dptn_{code}_` / `DPTN_{CODE}_`.
2. Новый код продукта проверяется по реестру выше и поиском на wordpress.org (`dptn-{product-name}`).
3. Имена в cookie, опциях и таблицах после релиза не меняются: на них завязаны данные пользователей. Переименование только с миграцией.
4. В админке автор виден через `Author` / `Author URI`. Дополнительные ссылки на dptn.pro (например, в строке плагина) добавляются фильтром `plugin_row_meta`, без баннеров и уведомлений.
