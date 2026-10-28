<?php
/**
 * Вкладка «Внешний вид» с живым предпросмотром.
 *
 * @var array $s Сохранённые настройки.
 */

use DPTN\CookieConsent\Admin;
use DPTN\CookieConsent\Settings;

defined( 'ABSPATH' ) || exit;

$dptn_cc_defaults = Settings::defaults()['style'];
$dptn_cc_colors   = array(
	'accent'    => array( 'Цвет кнопок и ссылок', '--dptn-cc-accent' ),
	'accent_fg' => array( 'Текст на кнопках', '--dptn-cc-accent-fg' ),
	'bg'        => array( 'Фон баннера', '--dptn-cc-bg' ),
	'fg'        => array( 'Текст баннера', '--dptn-cc-fg' ),
);
$dptn_cc_t        = $s['texts'];
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="dptn-cc-position">Положение</label></th>
		<td>
			<select id="dptn-cc-position" name="<?php echo esc_attr( Admin::name( 'position' ) ); ?>">
				<?php foreach ( Admin::POSITIONS as $dptn_cc_value => $dptn_cc_label ) : ?>
					<option value="<?php echo esc_attr( $dptn_cc_value ); ?>" <?php selected( $s['position'], $dptn_cc_value ); ?>><?php echo esc_html( $dptn_cc_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="dptn-cc-floating">Плавающая кнопка</label></th>
		<td>
			<select id="dptn-cc-floating" name="<?php echo esc_attr( Admin::name( 'floating' ) ); ?>">
				<?php foreach ( array( 'none' => 'Не показывать', 'left' => 'Внизу слева', 'right' => 'Внизу справа' ) as $dptn_cc_value => $dptn_cc_label ) : ?>
					<option value="<?php echo esc_attr( $dptn_cc_value ); ?>" <?php selected( $s['floating'], $dptn_cc_value ); ?>><?php echo esc_html( $dptn_cc_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">Круглая кнопка «Настройки cookie» появляется после выбора и позволяет изменить или отозвать согласие. Вместо неё можно поставить ссылку в подвал — см. «Справку».</p>
		</td>
	</tr>
	<?php foreach ( $dptn_cc_colors as $dptn_cc_key => list( $dptn_cc_label, $dptn_cc_var ) ) : ?>
		<tr>
			<th scope="row"><label for="dptn-cc-<?php echo esc_attr( $dptn_cc_key ); ?>"><?php echo esc_html( $dptn_cc_label ); ?></label></th>
			<td>
				<input type="text" id="dptn-cc-<?php echo esc_attr( $dptn_cc_key ); ?>" class="dptn-cc-color" name="<?php echo esc_attr( Admin::name( 'style', $dptn_cc_key ) ); ?>" value="<?php echo esc_attr( $s['style'][ $dptn_cc_key ] ); ?>" data-default-color="<?php echo esc_attr( $dptn_cc_defaults[ $dptn_cc_key ] ); ?>" data-var="<?php echo esc_attr( $dptn_cc_var ); ?>">
			</td>
		</tr>
	<?php endforeach; ?>
	<tr>
		<th scope="row"><label for="dptn-cc-radius">Скругление углов</label></th>
		<td>
			<input type="number" id="dptn-cc-radius" class="small-text" min="0" max="32" name="<?php echo esc_attr( Admin::name( 'style', 'radius' ) ); ?>" value="<?php echo esc_attr( (string) $s['style']['radius'] ); ?>" data-var-px="--dptn-cc-radius"> px
		</td>
	</tr>
	<tr>
		<th scope="row">Подключение файлов</th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( Admin::name( 'inline_assets' ) ); ?>" value="1" <?php checked( $s['inline_assets'] ); ?>>
				Встраивать CSS и JS прямо в страницу
			</label>
			<p class="description">
				Без лишних HTTP-запросов: около 5 КБ (в сжатом виде) на каждой странице. Если выключить, файлы
				подключатся отдельно и будут кешироваться браузером.
			</p>
		</td>
	</tr>
</table>

<h2>Предпросмотр</h2>
<div id="dptn-cc-contrast" class="notice notice-warning inline" hidden></div>
<p class="description">Шрифт на сайте наследуется от темы. Цвета обновляются сразу, тексты — после сохранения.</p>
<div id="dptn-cc-preview" class="dptn-cc dptn-cc--preview" style="<?php echo esc_attr( Settings::css_vars( $s['style'] ) ); ?>">
	<div class="dptn-cc__box">
		<p class="dptn-cc__title"><?php echo esc_html( $dptn_cc_t['title'] ); ?></p>
		<p class="dptn-cc__text"><?php echo esc_html( $dptn_cc_t['body'] ); ?></p>
		<p class="dptn-cc__links">
			<a href="#" tabindex="-1"><?php echo esc_html( $dptn_cc_t['consent'] ); ?></a>
			<a href="#" tabindex="-1"><?php echo esc_html( $dptn_cc_t['policy'] ); ?></a>
		</p>
		<div class="dptn-cc__actions">
			<button type="button" class="dptn-cc__btn" data-act="necessary" tabindex="-1"><?php echo esc_html( $dptn_cc_t['necessary'] ); ?></button>
			<button type="button" class="dptn-cc__btn" data-act="settings" tabindex="-1"><?php echo esc_html( $dptn_cc_t['settings'] ); ?></button>
			<button type="button" class="dptn-cc__btn" data-act="accept" tabindex="-1"><?php echo esc_html( $dptn_cc_t['accept'] ); ?></button>
		</div>
	</div>
</div>
