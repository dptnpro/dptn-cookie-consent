<?php
/**
 * Вкладка «Основное».
 *
 * @var array $s Сохранённые настройки.
 */

use DPTN\CookieConsent\Admin;

defined( 'ABSPATH' ) || exit;

$dptn_cc_policy = $s['policy_page'] ? (int) $s['policy_page'] : (int) get_option( 'wp_page_for_privacy_policy' );
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">Режим</th>
		<td>
			<fieldset>
				<legend class="screen-reader-text">Режим</legend>
				<label>
					<input type="radio" name="<?php echo esc_attr( Admin::name( 'mode' ) ); ?>" value="strict" <?php checked( $s['mode'], 'strict' ); ?>>
					<strong>Строгий.</strong> Необязательные сервисы запускаются только после согласия. Рекомендуется.
				</label><br>
				<label>
					<input type="radio" name="<?php echo esc_attr( Admin::name( 'mode' ) ); ?>" value="notice" <?php checked( $s['mode'], 'notice' ); ?>>
					<strong>Уведомление.</strong> Сервисы запускаются сразу, баннер информирует и позволяет отказаться.
				</label>
				<div id="dptn-cc-notice-warning" class="notice notice-warning inline"<?php echo 'notice' === $s['mode'] ? '' : ' hidden'; ?>><p>
					Этот режим не обеспечивает однозначного согласия (ч. 1 ст. 9 152-ФЗ): сервисы обрабатывают данные
					до того, как посетитель сделал выбор. Решение и ответственность — на владельце сайта.
				</p></div>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="dptn-cc-consent-page">Страница согласия</label></th>
		<td>
			<?php
			wp_dropdown_pages(
				array(
					'name'              => Admin::name( 'consent_page' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- экранирует wp_dropdown_pages.
					'id'                => 'dptn-cc-consent-page',
					'selected'          => (int) $s['consent_page'],
					'show_option_none'  => '— не выбрана —',
					'option_none_value' => 0,
				)
			);
			?>
			<p class="description">
				Отдельный документ «Согласие на обработку cookie и метрических данных». С 1 сентября 2025 года
				согласие оформляется отдельно от иных документов (156-ФЗ), ссылки только на политику недостаточно.
			</p>
			<?php if ( ! $s['consent_page'] && 'strict' === $s['mode'] ) : ?>
				<div class="notice notice-warning inline"><p>Страница не выбрана: баннер покажется без ссылки на текст согласия.</p></div>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="dptn-cc-policy-page">Политика обработки ПДн</label></th>
		<td>
			<?php
			wp_dropdown_pages(
				array(
					'name'              => Admin::name( 'policy_page' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'id'                => 'dptn-cc-policy-page',
					'selected'          => $dptn_cc_policy,
					'show_option_none'  => '— не выбрана —',
					'option_none_value' => 0,
				)
			);
			?>
			<p class="description">Публикуется по ч. 2 ст. 18.1 152-ФЗ. По умолчанию — страница политики конфиденциальности WordPress.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="dptn-cc-days">Срок хранения выбора</label></th>
		<td>
			<input type="number" id="dptn-cc-days" class="small-text" min="1" max="730" name="<?php echo esc_attr( Admin::name( 'cookie_days' ) ); ?>" value="<?php echo esc_attr( (string) $s['cookie_days'] ); ?>"> дней
			<p class="description">Когда срок истечёт, баннер покажется снова. Тот же срок укажите в тексте согласия.</p>
		</td>
	</tr>
	<tr>
		<th scope="row">Версия согласия</th>
		<td>
			<p>Текущая версия: <strong><?php echo esc_html( (string) $s['version'] ); ?></strong></p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( Admin::name( 'bump_version' ) ); ?>" value="1">
				Показать баннер всем посетителям заново
			</label>
			<p class="description">
				Отметьте после изменения текста согласия. При изменении списка сервисов версия повышается автоматически.
			</p>
		</td>
	</tr>
</table>
