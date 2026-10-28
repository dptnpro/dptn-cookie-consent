<?php
/**
 * Вкладка «Журнал»: статистика, версии согласия и записи с фильтрами.
 *
 * @var array $s Сохранённые настройки.
 */

use DPTN\CookieConsent\Admin;
use DPTN\CookieConsent\Log;

defined( 'ABSPATH' ) || exit;

$dptn_cc_filters  = Log::filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только фильтры просмотра.
$dptn_cc_page     = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$dptn_cc_per_page = 50;
$dptn_cc_result   = Log::query( $dptn_cc_filters, $dptn_cc_per_page, ( $dptn_cc_page - 1 ) * $dptn_cc_per_page );
$dptn_cc_stats    = Log::stats( 30 );
$dptn_cc_sum      = array_sum( $dptn_cc_stats );
$dptn_cc_versions = get_option( 'dptn_cc_versions', array() );
$dptn_cc_pages    = (int) ceil( $dptn_cc_result['total'] / $dptn_cc_per_page );
?>
<hr>
<h2>За последние 30 дней</h2>
<div class="dptn-cc-admin__stats">
	<?php foreach ( Log::ACTIONS as $dptn_cc_action => $dptn_cc_label ) : ?>
		<div class="dptn-cc-admin__stat">
			<span class="dptn-cc-admin__stat-value"><?php echo esc_html( number_format_i18n( $dptn_cc_stats[ $dptn_cc_action ] ) ); ?></span>
			<span class="dptn-cc-admin__stat-label">
				<?php echo esc_html( $dptn_cc_label ); ?>
				<?php if ( $dptn_cc_sum ) : ?>
					· <?php echo esc_html( (string) round( $dptn_cc_stats[ $dptn_cc_action ] / $dptn_cc_sum * 100 ) ); ?>%
				<?php endif; ?>
			</span>
		</div>
	<?php endforeach; ?>
</div>

<?php if ( is_array( $dptn_cc_versions ) && $dptn_cc_versions ) : ?>
	<h2>Версии согласия</h2>
	<table class="widefat striped dptn-cc-admin__table">
		<thead><tr><th>Версия</th><th>Дата</th><th>Страница согласия</th><th>SHA-256 текста</th><th>Сервисы</th></tr></thead>
		<tbody>
			<?php foreach ( array_reverse( $dptn_cc_versions, true ) as $dptn_cc_v => $dptn_cc_info ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $dptn_cc_v ); ?></td>
					<td><?php echo esc_html( get_date_from_gmt( $dptn_cc_info['date'], 'd.m.Y H:i' ) ); ?></td>
					<td><?php echo $dptn_cc_info['url'] ? '<a href="' . esc_url( $dptn_cc_info['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $dptn_cc_info['url'] ) . '</a>' : '—'; ?></td>
					<td><code class="dptn-cc-admin__hash"><?php echo esc_html( $dptn_cc_info['hash'] ? substr( $dptn_cc_info['hash'], 0, 16 ) . '…' : '—' ); ?></code></td>
					<td><?php echo esc_html( implode( ', ', (array) $dptn_cc_info['services'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">Версия повышается при изменении списка сервисов или по флажку на вкладке «Основное». Ранние версии без записи создавались до появления журнала.</p>
<?php endif; ?>

<h2>Записи</h2>
<form method="get" class="dptn-cc-admin__filters">
	<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGE ); ?>">
	<input type="hidden" name="tab" value="log">
	<label>ID согласия <input type="text" class="regular-text code" name="consent_id" value="<?php echo esc_attr( $dptn_cc_filters['id'] ); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
	<label>с <input type="date" name="from" value="<?php echo esc_attr( $dptn_cc_filters['from'] ); ?>"></label>
	<label>по <input type="date" name="to" value="<?php echo esc_attr( $dptn_cc_filters['to'] ); ?>"></label>
	<button type="submit" class="button">Показать</button>
	<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_filter( array( 'action' => 'dptn_cc_export', 'consent_id' => $dptn_cc_filters['id'], 'from' => $dptn_cc_filters['from'], 'to' => $dptn_cc_filters['to'] ) ), admin_url( 'admin-post.php' ) ), 'dptn_cc_export' ) ); ?>">Скачать CSV</a>
</form>

<p>Найдено записей: <?php echo esc_html( number_format_i18n( $dptn_cc_result['total'] ) ); ?></p>

<?php if ( $dptn_cc_result['rows'] ) : ?>
	<table class="widefat striped dptn-cc-admin__table">
		<thead><tr><th>Дата</th><th>ID согласия</th><th>Версия</th><th>Категории</th><th>Действие</th><th>IP</th></tr></thead>
		<tbody>
			<?php foreach ( $dptn_cc_result['rows'] as $dptn_cc_row ) : ?>
				<tr>
					<?php foreach ( Log::present( $dptn_cc_row ) as $dptn_cc_i => $dptn_cc_cell ) : ?>
						<td><?php echo 1 === $dptn_cc_i ? '<code>' . esc_html( $dptn_cc_cell ) . '</code>' : esc_html( $dptn_cc_cell ); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $dptn_cc_pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $dptn_cc_page,
						'total'   => $dptn_cc_pages,
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
<?php endif; ?>
