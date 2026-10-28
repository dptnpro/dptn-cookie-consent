<?php
/**
 * Вкладка «Тексты».
 *
 * @var array $s Сохранённые настройки.
 */

use DPTN\CookieConsent\Admin;
use DPTN\CookieConsent\Settings;

defined( 'ABSPATH' ) || exit;

$dptn_cc_labels = array(
	'title'     => 'Заголовок',
	'body'      => 'Текст',
	'accept'    => 'Кнопка «Принять все»',
	'necessary' => 'Кнопка «Только необходимые»',
	'settings'  => 'Кнопка «Настроить»',
	'save'      => 'Кнопка «Сохранить выбор»',
	'consent'   => 'Ссылка на согласие',
	'policy'    => 'Ссылка на политику',
	'services'  => 'Подпись перед списком сервисов',
	'embed'     => 'Заглушка видео и карт (%s — название сервиса)',
	'embed_btn' => 'Кнопка на заглушке',
	'id'        => 'Подпись к ID согласия',
	'fab'       => 'Плавающая кнопка (для скринридеров)',
	'saved'     => 'Объявление после выбора (для скринридеров)',
	'new_tab'   => 'Пометка ссылок (для скринридеров)',
);
$dptn_cc_defaults = Settings::defaults();
?>
<p>Пустое поле возвращает текст по умолчанию.</p>

<h2>Баннер</h2>
<table class="form-table" role="presentation">
	<?php foreach ( $dptn_cc_labels as $dptn_cc_key => $dptn_cc_label ) : ?>
		<tr>
			<th scope="row"><label for="dptn-cc-text-<?php echo esc_attr( $dptn_cc_key ); ?>"><?php echo esc_html( $dptn_cc_label ); ?></label></th>
			<td>
				<?php if ( 'body' === $dptn_cc_key ) : ?>
					<textarea id="dptn-cc-text-body" class="large-text" rows="3" name="<?php echo esc_attr( Admin::name( 'texts', 'body' ) ); ?>" placeholder="<?php echo esc_attr( $dptn_cc_defaults['texts']['body'] ); ?>"><?php echo esc_textarea( $s['texts']['body'] ); ?></textarea>
				<?php else : ?>
					<input type="text" id="dptn-cc-text-<?php echo esc_attr( $dptn_cc_key ); ?>" class="regular-text" name="<?php echo esc_attr( Admin::name( 'texts', $dptn_cc_key ) ); ?>" value="<?php echo esc_attr( $s['texts'][ $dptn_cc_key ] ); ?>" placeholder="<?php echo esc_attr( $dptn_cc_defaults['texts'][ $dptn_cc_key ] ); ?>">
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>

<h2>Категории</h2>
<p>Посетитель видит категорию, только если в ней есть включённые сервисы. «Необходимые» показываются всегда.</p>
<table class="form-table" role="presentation">
	<?php foreach ( $s['categories'] as $dptn_cc_id => $dptn_cc_category ) : ?>
		<tr>
			<th scope="row"><label for="dptn-cc-cat-<?php echo esc_attr( $dptn_cc_id ); ?>"><?php echo esc_html( $dptn_cc_defaults['categories'][ $dptn_cc_id ]['label'] ?? $dptn_cc_id ); ?></label></th>
			<td>
				<input type="text" id="dptn-cc-cat-<?php echo esc_attr( $dptn_cc_id ); ?>" class="regular-text" name="<?php echo esc_attr( Admin::name( 'categories', $dptn_cc_id, 'label' ) ); ?>" value="<?php echo esc_attr( $dptn_cc_category['label'] ); ?>" aria-label="Название">
				<textarea class="large-text dptn-cc-admin__desc" rows="2" name="<?php echo esc_attr( Admin::name( 'categories', $dptn_cc_id, 'desc' ) ); ?>" aria-label="Описание"><?php echo esc_textarea( $dptn_cc_category['desc'] ); ?></textarea>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
