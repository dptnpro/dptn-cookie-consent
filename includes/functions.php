<?php
/**
 * PHP API для некешируемых контекстов (личный кабинет, AJAX, письма).
 *
 * На страницах под страничным кешем эти функции использовать нельзя:
 * результат «запечётся» в кеш для всех посетителей. Там проверяйте
 * согласие в браузере через window.dptnCC.has().
 */

defined( 'ABSPATH' ) || exit;

/**
 * Разобранное согласие текущего посетителя или null, если его нет
 * либо оно дано на устаревшую версию текста.
 *
 * @return array{version:int, mask:int, time:int, id:string}|null
 */
function dptn_cc_get_consent() {
	if ( empty( $_COOKIE['dptn_cc'] ) || ! is_string( $_COOKIE['dptn_cc'] ) ) {
		return null;
	}

	$raw = wp_unslash( $_COOKIE['dptn_cc'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- проверяется регуляркой ниже.
	if ( ! preg_match( '/^v(\d{1,5})\.(\d{1,3})\.(\d{1,10})\.([0-9a-f-]{36})$/', $raw, $m ) ) {
		return null;
	}

	if ( (int) $m[1] !== (int) DPTN\CookieConsent\Settings::get( 'version' ) ) {
		return null;
	}

	return array(
		'version' => (int) $m[1],
		'mask'    => (int) $m[2] | DPTN\CookieConsent\Settings::CATEGORY_BITS['necessary'],
		'time'    => (int) $m[3],
		'id'      => $m[4],
	);
}

/**
 * Разрешена ли категория: necessary, analytics, marketing, functional.
 */
function dptn_cc_has_consent( string $category ): bool {
	$bits = DPTN\CookieConsent\Settings::CATEGORY_BITS;
	if ( ! isset( $bits[ $category ] ) ) {
		return false;
	}

	$consent = dptn_cc_get_consent();
	if ( null === $consent ) {
		// В режиме уведомления всё разрешено, пока посетитель не отказался.
		return 'necessary' === $category || 'notice' === DPTN\CookieConsent\Settings::get( 'mode' );
	}

	return (bool) ( $consent['mask'] & $bits[ $category ] );
}
