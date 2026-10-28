<?php
/**
 * Вкладка «Сервисы».
 *
 * @var array $s Сохранённые настройки.
 */

use DPTN\CookieConsent\Admin;
use DPTN\CookieConsent\Services;

defined( 'ABSPATH' ) || exit;

$dptn_cc_ym = $s['services']['yandex_metrika'];
$dptn_cc_n  = static function ( string $field ): string {
	return Admin::name( 'services', 'yandex_metrika', $field );
};

$dptn_cc_categories = array(
	'analytics'  => $s['categories']['analytics']['label'],
	'marketing'  => $s['categories']['marketing']['label'],
	'functional' => $s['categories']['functional']['label'],
);

$dptn_cc_row = static function ( string $index, array $row ) use ( $dptn_cc_categories ): void {
	$name = static function ( string $field ) use ( $index ): string {
		return Admin::name( 'scripts', $index, $field );
	};
	?>
	<tr>
		<td><input type="text" class="regular-text code" name="<?php echo esc_attr( $name( 'handle' ) ); ?>" value="<?php echo esc_attr( $row['handle'] ?? '' ); ?>" aria-label="Handle" data-field="handle"></td>
		<td><input type="text" class="regular-text" name="<?php echo esc_attr( $name( 'name' ) ); ?>" value="<?php echo esc_attr( $row['name'] ?? '' ); ?>" aria-label="Название для посетителей" data-field="name"></td>
		<td>
			<select name="<?php echo esc_attr( $name( 'category' ) ); ?>" aria-label="Категория" data-field="category">
				<?php foreach ( $dptn_cc_categories as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['category'] ?? 'marketing', $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><input type="text" class="code" name="<?php echo esc_attr( $name( 'cookies' ) ); ?>" value="<?php echo esc_attr( implode( ', ', (array) ( $row['cookies'] ?? array() ) ) ); ?>" aria-label="Префиксы cookie" placeholder="_abc_, xyz" data-field="cookies"></td>
		<td><input type="text" class="code" name="<?php echo esc_attr( $name( 'placeholder' ) ); ?>" value="<?php echo esc_attr( $row['placeholder'] ?? '' ); ?>" aria-label="Блок для заглушки" placeholder="#map" data-field="placeholder"></td>
		<td><label><input type="checkbox" name="<?php echo esc_attr( $name( 'delete' ) ); ?>" value="1"> Удалить</label></td>
	</tr>
	<?php
};

$dptn_cc_scan = null;
if ( isset( $_GET['scan'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'dptn_cc_scan' ) ) {
	$dptn_cc_scan = Admin::scan();
}
$dptn_cc_added = array_column( $s['scripts'], 'handle' );
?>
<div class="notice notice-info inline"><p>
	Плагин запускает включённые сервисы только после согласия.
	Удалите код этих счётчиков из темы и других плагинов, иначе он будет работать без согласия.
	При изменении списка сервисов баннер автоматически покажется всем посетителям заново.
</p></div>

<h2>Яндекс.Метрика <span class="dptn-cc-admin__badge">Аналитика</span></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">Подключение</th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $dptn_cc_n( 'enabled' ) ); ?>" value="1" <?php checked( $dptn_cc_ym['enabled'] ); ?>>
				Подключить счётчик
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="dptn-cc-ym-id">Номер счётчика</label></th>
		<td>
			<input type="text" id="dptn-cc-ym-id" class="regular-text" inputmode="numeric" pattern="[0-9]*" name="<?php echo esc_attr( $dptn_cc_n( 'counter_id' ) ); ?>" value="<?php echo esc_attr( $dptn_cc_ym['counter_id'] ); ?>">
			<?php if ( $dptn_cc_ym['enabled'] && '' === $dptn_cc_ym['counter_id'] ) : ?>
				<div class="notice notice-warning inline"><p>Счётчик включён, но номер не указан: Метрика не подключится.</p></div>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row">Возможности</th>
		<td>
			<fieldset>
				<legend class="screen-reader-text">Возможности Метрики</legend>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $dptn_cc_n( 'clickmap' ) ); ?>" value="1" <?php checked( $dptn_cc_ym['clickmap'] ); ?>>
					Карта кликов
				</label><br>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $dptn_cc_n( 'webvisor' ) ); ?>" value="1" <?php checked( $dptn_cc_ym['webvisor'] ); ?>>
					Вебвизор
				</label>
				<p class="description">Вебвизор записывает действия посетителя, включая ввод в формы. Упомяните это в тексте согласия.</p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $dptn_cc_n( 'ecommerce' ) ); ?>" value="1" <?php checked( $dptn_cc_ym['ecommerce'] ); ?>>
					Электронная коммерция (контейнер <code>dataLayer</code>)
				</label>
			</fieldset>
		</td>
	</tr>
</table>

<h2>Скрипты темы и плагинов</h2>
<p class="dptn-cc-admin__text">
	Скрипты, подключённые через <code>wp_enqueue_script</code>, блокируются до согласия по handle — это id тега
	без окончания <code>-js</code>. Если от скрипта зависят другие скрипты темы, после согласия страница
	перезагрузится, чтобы они увидели подключённую библиотеку.
</p>
<p>
	<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'scan', '1', Admin::url( 'services' ) ), 'dptn_cc_scan' ) ); ?>">Найти скрипты на главной странице</a>
</p>

<?php if ( is_wp_error( $dptn_cc_scan ) ) : ?>
	<div class="notice notice-error inline"><p>Не удалось загрузить главную страницу: <?php echo esc_html( $dptn_cc_scan->get_error_message() ); ?></p></div>
<?php elseif ( is_array( $dptn_cc_scan ) ) : ?>
	<h3>Найдено на главной</h3>
	<?php if ( ! $dptn_cc_scan ) : ?>
		<p>Внешних и известных скриптов не найдено.</p>
	<?php else : ?>
		<table class="widefat striped dptn-cc-admin__table">
			<thead><tr><th>Handle</th><th>Адрес</th><th>Подсказка</th><th></th></tr></thead>
			<tbody>
				<?php foreach ( $dptn_cc_scan as $dptn_cc_item ) : ?>
					<tr>
						<td><code><?php echo esc_html( $dptn_cc_item['handle'] ); ?></code></td>
						<td><?php echo esc_html( $dptn_cc_item['host'] ?: '—' ); ?></td>
						<td>
							<?php if ( $dptn_cc_item['hint'] ) : ?>
								<?php echo esc_html( $dptn_cc_item['hint'][0] ); ?>
								<?php if ( $dptn_cc_item['hint'][1] ) : ?>
									· <?php echo esc_html( $dptn_cc_categories[ $dptn_cc_item['hint'][1] ] ); ?>
								<?php endif; ?>
							<?php else : ?>
								Внешний скрипт — проверьте, ставит ли он cookie
							<?php endif; ?>
						</td>
						<td>
							<?php if ( in_array( $dptn_cc_item['handle'], $dptn_cc_added, true ) ) : ?>
								Добавлен
							<?php elseif ( ! $dptn_cc_item['hint'] || $dptn_cc_item['hint'][1] ) : ?>
								<button type="button" class="button button-small" data-dptn-cc-add="<?php echo esc_attr( wp_json_encode( array(
									'handle'   => $dptn_cc_item['handle'],
									'name'     => $dptn_cc_item['hint'][0] ?? $dptn_cc_item['host'],
									'category' => $dptn_cc_item['hint'][1] ?? 'marketing',
									'cookies'  => implode( ', ', $dptn_cc_item['hint'][2] ?? array() ),
								) ) ); ?>">Добавить</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
<?php endif; ?>

<table class="widefat dptn-cc-admin__table">
	<thead><tr><th>Handle</th><th>Название для посетителей</th><th>Категория</th><th>Префиксы cookie</th><th>Блок для заглушки</th><th></th></tr></thead>
	<tbody id="dptn-cc-scripts">
		<?php foreach ( $s['scripts'] as $dptn_cc_i => $dptn_cc_script ) : ?>
			<?php $dptn_cc_row( (string) $dptn_cc_i, $dptn_cc_script ); ?>
		<?php endforeach; ?>
	</tbody>
</table>
<template id="dptn-cc-script-row"><?php $dptn_cc_row( '__i__', array() ); ?></template>
<p><button type="button" class="button" id="dptn-cc-add-row">Добавить скрипт</button></p>
<p class="description">Префиксы cookie нужны, чтобы удалить их при отзыве согласия. «Блок для заглушки» — CSS-селектор
	элемента, который скрипт наполняет (например, контейнер карты): до согласия в нём будет заглушка. Изменения применятся после сохранения.</p>

<h2>Встроенный контент</h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">Заглушка до согласия</th>
		<td>
			<fieldset>
				<legend class="screen-reader-text">Провайдеры встроенного контента</legend>
				<?php foreach ( Services::EMBEDS as $dptn_cc_id => $dptn_cc_embed ) : ?>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Admin::name( 'embeds', $dptn_cc_id ) ); ?>" value="1" <?php checked( ! empty( $s['embeds'][ $dptn_cc_id ] ) ); ?>>
						<?php echo esc_html( $dptn_cc_embed[0] ); ?>
					</label><br>
				<?php endforeach; ?>
				<p class="description">
					Iframe в записях и oEmbed-вставках заменяются заглушкой до согласия на категорию
					«<?php echo esc_html( $dptn_cc_categories['functional'] ); ?>». Iframe, выведенные шаблоном темы
					напрямую, плагин не видит: замените в них <code>src</code> на <code>data-dptn-cc-src</code> и добавьте
					<code>data-dptn-cc="functional"</code>.
				</p>
			</fieldset>
		</td>
	</tr>
</table>
