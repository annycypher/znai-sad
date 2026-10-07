<?php
/**
 * Этап 8.5 — задел под партнёрские ссылки: /go/{slug}/ → 302 + счётчик переходов.
 * Таблица и правило создаются сразу (фаза 1), наполнение — позже.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite: /go/{slug}/.
 */
function sad_znaniy_social_go_rewrite() {
	add_rewrite_rule( '^go/([a-z0-9-]+)/?$', 'index.php?sz_go=$matches[1]', 'top' );
}
add_action( 'init', 'sad_znaniy_social_go_rewrite', 11 );

/**
 * Регистрирует query-переменную.
 *
 * @param string[] $vars Переменные.
 * @return string[]
 */
function sad_znaniy_social_go_query_vars( $vars ) {
	$vars[] = 'sz_go';
	return $vars;
}
add_filter( 'query_vars', 'sad_znaniy_social_go_query_vars' );

/**
 * 302-редирект партнёрской ссылки + запись перехода.
 */
function sad_znaniy_social_go_redirect() {
	$slug = (string) get_query_var( 'sz_go' );
	if ( '' === $slug ) {
		return;
	}

	global $wpdb;
	$table  = $wpdb->prefix . 'sz_aff_links';
	$target = $wpdb->get_var( $wpdb->prepare( "SELECT target_url FROM {$table} WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB

	if ( ! $target ) {
		status_header( 404 );
		nocache_headers();
		return;
	}

	$clicks = get_option( 'sz_aff_clicks', array() );
	$clicks = is_array( $clicks ) ? $clicks : array();
	$day                  = current_time( 'Y-m-d' );
	$clicks[ $slug ][ $day ] = isset( $clicks[ $slug ][ $day ] ) ? (int) $clicks[ $slug ][ $day ] + 1 : 1;
	update_option( 'sz_aff_clicks', $clicks, false );

	wp_redirect( esc_url_raw( $target ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
	exit;
}
add_action( 'template_redirect', 'sad_znaniy_social_go_redirect', 3 );

/**
 * Разово сбрасывает правила после появления /go/.
 */
function sad_znaniy_social_maybe_flush() {
	if ( '1' !== get_option( 'sz_social_rules_version' ) ) {
		flush_rewrite_rules();
		update_option( 'sz_social_rules_version', '1', false );
	}
}
add_action( 'init', 'sad_znaniy_social_maybe_flush', 22 );

/**
 * Переходы по партнёрским ссылкам: slug => сумма кликов.
 *
 * @return array
 */
function sad_znaniy_social_aff_clicks() {
	$clicks = get_option( 'sz_aff_clicks', array() );
	$out    = array();
	foreach ( (array) $clicks as $slug => $days ) {
		$out[ $slug ] = array_sum( array_map( 'intval', (array) $days ) );
	}
	return $out;
}
