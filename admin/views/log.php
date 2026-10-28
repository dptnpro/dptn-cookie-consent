<?php
/**
 * Вкладка «Журнал»: настройки. Список записей — в log-after.php, вне формы.
 *
 * @var array $s Сохранённые настройки.
 */

use DPTN\CookieConsent\Admin;

defined( 'ABSPATH' ) || exit;

$dptn_cc_log = $s['log'];
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">Журнал</th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( Admin::name( 'log', 'enabled' ) ); ?>" value="1" <?php checked( $dptn_cc_log['enabled'] ); ?>>
				Записывать выбор посетителей
			</label>
			<p class="description">
				По ч. 3 ст. 9 152-ФЗ доказать получение согласия должен оператор. В журнал попадают псевдонимный ID
				из cookie, время сервера, версия текста согласия и выбранные категории. Журнал хранится в базе сайта.
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row">IP-адрес</th>
		<td>
			<fieldset>
				<legend class="screen-reader-text">IP-адрес</legend>
				<label>
					<input type="radio" name="<?php echo esc_attr( Admin::name( 'log', 'ip' ) ); ?>" value="none" <?php checked( $dptn_cc_log['ip'], 'none' ); ?>>
					Не сохранять
				</label><br>
				<label>
					<input type="radio" name="<?php echo esc_attr( Admin::name( 'log', 'ip' ) ); ?>" value="truncated" <?php checked( $dptn_cc_log['ip'], 'truncated' ); ?>>
					Сохранять усечённым (IPv4 без последнего числа, IPv6 — первые 48 бит)
				</label>
				<p class="description">IP-адрес может быть персональными данными. Без явной необходимости не сохраняйте его.</p>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="dptn-cc-retention">Срок хранения записей</label></th>
		<td>
			<input type="number" id="dptn-cc-retention" class="small-text" min="30" max="3650" name="<?php echo esc_attr( Admin::name( 'log', 'retention' ) ); ?>" value="<?php echo esc_attr( (string) $dptn_cc_log['retention'] ); ?>"> дней
			<p class="description">Старые записи удаляются раз в сутки. Срок стоит согласовать с юристом; по умолчанию 3 года.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="dptn-cc-cap">Лимит записей в сутки</label></th>
		<td>
			<input type="number" id="dptn-cc-cap" class="small-text" min="100" max="1000000" name="<?php echo esc_attr( Admin::name( 'log', 'daily_cap' ) ); ?>" value="<?php echo esc_attr( (string) $dptn_cc_log['daily_cap'] ); ?>">
			<p class="description">Защита от засорения базы: сверх лимита записи не сохраняются. С одного ID — не больше 10 записей в сутки.</p>
		</td>
	</tr>
</table>
