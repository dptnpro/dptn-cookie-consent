<?php
/**
 * Журнал согласий: таблица {prefix}dptn_cc_log.
 *
 * Хранит псевдонимный ID из cookie, время сервера (UTC), версию текста согласия,
 * выбранные категории и действие. IP — только по опции и только усечённый.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Log {

	const DB_VERSION = 1;
	const CRON       = 'dptn_cc_cleanup';

	const ACCEPT_ALL = 1;
	const NECESSARY  = 2;
	const CUSTOM     = 3;
	const WITHDRAW   = 4;

	const ACTIONS = array(
		self::ACCEPT_ALL => 'Принял все',
		self::NECESSARY  => 'Только необходимые',
		self::CUSTOM     => 'Свой выбор',
		self::WITHDRAW   => 'Отозвал часть',
	);

	/** Записей с одного ID согласия в сутки — защита от накрутки. */
	const PER_ID_DAILY = 10;

	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'dptn_cc_log';
	}

	/**
	 * Создаёт или обновляет таблицу и планирует ежедневную очистку.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				consent_id char(36) NOT NULL,
				created_at datetime NOT NULL,
				version smallint(5) unsigned NOT NULL,
				categories tinyint(3) unsigned NOT NULL,
				action tinyint(3) unsigned NOT NULL,
				ip varbinary(16) DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY consent_id (consent_id,id),
				KEY created_at (created_at)
			) {$wpdb->get_charset_collate()};"
		);

		update_option( 'dptn_cc_db_version', self::DB_VERSION, true );

		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Для уже активированного плагина хук активации не сработает — проверяем при входе в админку.
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( 'dptn_cc_db_version' ) !== self::DB_VERSION || ! wp_next_scheduled( self::CRON ) ) {
			self::install();
		}
	}

	/**
	 * Пишет выбор посетителя. Повтор того же выбора подряд и превышение лимитов молча отбрасываются.
	 */
	public static function add( string $consent_id, int $version, int $categories, int $action ): bool {
		global $wpdb;

		$settings = (array) Settings::get( 'log', array() );
		$table    = self::table();
		$now      = gmdate( 'Y-m-d H:i:s' );
		$day_ago  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- своя таблица, кеш не нужен.
		$last = $wpdb->get_row(
			$wpdb->prepare( "SELECT version, categories FROM {$table} WHERE consent_id = %s ORDER BY id DESC LIMIT 1", $consent_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		if ( $last && (int) $last['version'] === $version && (int) $last['categories'] === $categories ) {
			return false;
		}

		$per_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE consent_id = %s AND created_at > %s", $consent_id, $day_ago ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $per_id >= self::PER_ID_DAILY ) {
			return false;
		}

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at > %s", $day_ago ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $total >= (int) ( $settings['daily_cap'] ?? 10000 ) ) {
			return false;
		}

		$row = array(
			'consent_id' => $consent_id,
			'created_at' => $now,
			'version'    => $version,
			'categories' => $categories,
			'action'     => $action,
		);
		if ( 'truncated' === ( $settings['ip'] ?? 'none' ) ) {
			$row['ip'] = self::truncated_ip();
		}

		$inserted = (bool) $wpdb->insert( $table, $row );
		// phpcs:enable

		return $inserted;
	}

	/**
	 * IPv4 без последнего октета, IPv6 — первые 48 бит. Берём только REMOTE_ADDR:
	 * заголовки прокси подделываются клиентом.
	 */
	private static function truncated_ip(): ?string {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$binary = $ip ? @inet_pton( $ip ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- некорректный адрес просто не пишем.
		if ( false === $binary ) {
			return null;
		}

		return 4 === strlen( $binary )
			? substr( $binary, 0, 3 ) . "\0"
			: substr( $binary, 0, 6 ) . str_repeat( "\0", 10 );
	}

	public static function format_ip( ?string $binary ): string {
		$ip = $binary ? @inet_ntop( $binary ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $ip ? $ip : '—';
	}

	/**
	 * Удаляет записи старше срока хранения порциями, чтобы не держать долгую блокировку таблицы.
	 */
	public static function cleanup(): void {
		global $wpdb;

		$days   = max( 30, (int) ( Settings::get( 'log', array() )['retention'] ?? 1095 ) );
		$before = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table  = self::table();

		do {
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 5000", $before ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} while ( $deleted >= 5000 );
	}

	/**
	 * Выборка для админки и экспорта.
	 *
	 * @param array $filters id, from, to (Y-m-d по времени сайта).
	 * @return array{rows: array[], total: int}
	 */
	public static function query( array $filters, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		list( $where, $args ) = self::where( $filters );
		$table                = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $args ) : "SELECT COUNT(*) FROM {$table}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $args, array( $limit, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Счётчики по действиям за последние N дней.
	 *
	 * @return int[] action => count
	 */
	public static function stats( int $days = 30 ): array {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT action, COUNT(*) AS n FROM {$table} WHERE created_at > %s GROUP BY action", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$stats = array_fill_keys( array_keys( self::ACTIONS ), 0 );
		foreach ( (array) $rows as $row ) {
			$stats[ (int) $row['action'] ] = (int) $row['n'];
		}

		return $stats;
	}

	/**
	 * Категории из битовой маски в подписи из настроек.
	 */
	public static function category_labels( int $mask ): string {
		$labels     = array();
		$categories = (array) Settings::get( 'categories', array() );
		foreach ( Settings::CATEGORY_BITS as $id => $bit ) {
			if ( $mask & $bit ) {
				$labels[] = $categories[ $id ]['label'] ?? $id;
			}
		}

		return implode( ', ', $labels );
	}

	/**
	 * CSV для Excel: UTF-8 с BOM, разделитель «;», время сайта.
	 */
	public static function export( array $filters ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="dptn-cc-log-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'Дата', 'ID согласия', 'Версия', 'Категории', 'Действие', 'IP' ), ';', '"', '\\' );

		$offset = 0;
		do {
			$batch = self::query( $filters, 5000, $offset )['rows'];
			foreach ( $batch as $row ) {
				fputcsv( $out, self::present( $row ), ';', '"', '\\' );
			}
			$offset += 5000;
		} while ( count( $batch ) === 5000 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Строка журнала в человекочитаемом виде.
	 *
	 * @return string[]
	 */
	public static function present( array $row ): array {
		return array(
			get_date_from_gmt( $row['created_at'], 'd.m.Y H:i:s' ),
			$row['consent_id'],
			(string) $row['version'],
			self::category_labels( (int) $row['categories'] ),
			self::ACTIONS[ (int) $row['action'] ] ?? '?',
			self::format_ip( $row['ip'] ),
		);
	}

	/**
	 * @return array{0: string, 1: array}
	 */
	private static function where( array $filters ): array {
		$where = array();
		$args  = array();

		if ( ! empty( $filters['id'] ) ) {
			$where[] = 'consent_id = %s';
			$args[]  = $filters['id'];
		}
		// Даты в фильтре — по времени сайта, в таблице — UTC.
		if ( ! empty( $filters['from'] ) ) {
			$where[] = 'created_at >= %s';
			$args[]  = get_gmt_from_date( $filters['from'] . ' 00:00:00' );
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[] = 'created_at <= %s';
			$args[]  = get_gmt_from_date( $filters['to'] . ' 23:59:59' );
		}

		return array( $where ? 'WHERE ' . implode( ' AND ', $where ) : '', $args );
	}

	/**
	 * Фильтры из запроса админки.
	 *
	 * @param array $source $_GET или $_POST.
	 */
	public static function filters( array $source ): array {
		$id   = strtolower( sanitize_text_field( wp_unslash( $source['consent_id'] ?? '' ) ) );
		$date = static function ( $value ) {
			$value = sanitize_text_field( wp_unslash( (string) $value ) );

			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
		};

		return array(
			'id'   => preg_match( '/^[0-9a-f-]{36}$/', $id ) ? $id : '',
			'from' => $date( $source['from'] ?? '' ),
			'to'   => $date( $source['to'] ?? '' ),
		);
	}
}
