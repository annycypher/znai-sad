<?php
/**
 * Этап 8.5 — Автопостинг: таблицы БД и миграции.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SAD_ZNANIY_SOCIAL_DB_VERSION' ) ) {
	define( 'SAD_ZNANIY_SOCIAL_DB_VERSION', '1' );
}

/**
 * Создаёт и обновляет таблицы модуля (dbDelta + версия в опции).
 */
function sad_znaniy_social_install() {
	if ( SAD_ZNANIY_SOCIAL_DB_VERSION === (string) get_option( 'sz_social_db_version', '' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	$queue = $wpdb->prefix . 'sz_social_queue';
	dbDelta(
		"CREATE TABLE {$queue} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			network varchar(20) NOT NULL DEFAULT '',
			payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			scheduled_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			posted_at datetime DEFAULT NULL,
			error text NOT NULL,
			erid varchar(80) DEFAULT NULL,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status_sched (status,scheduled_at),
			KEY post_network (post_id,network)
		) {$charset};"
	);

	$aff = $wpdb->prefix . 'sz_aff_links';
	dbDelta(
		"CREATE TABLE {$aff} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(60) NOT NULL DEFAULT '',
			store varchar(60) NOT NULL DEFAULT '',
			target_url text NOT NULL,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset};"
	);

	sad_znaniy_social_seed_aff_links();

	update_option( 'sz_social_db_version', SAD_ZNANIY_SOCIAL_DB_VERSION, false );
}
add_action( 'init', 'sad_znaniy_social_install', 5 );

/**
 * Стартовое наполнение таблицы партнёрских ссылок (10 демо-строк).
 */
function sad_znaniy_social_seed_aff_links() {
	global $wpdb;
	$table = $wpdb->prefix . 'sz_aff_links';
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
	if ( $count > 0 ) {
		return;
	}
	$stores = array( 'semena', 'udobreniya', 'inventar', 'teplicy', 'sadovaya-tehnika', 'grunt', 'sredstva-zashchity', 'sažency', 'poleznosti', 'knigi' );
	foreach ( $stores as $i => $store ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'slug'       => 'demo-' . ( $i + 1 ),
				'store'      => $store,
				'target_url' => home_url( '/' ),
				'created'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}
}

/**
 * Таблицы, не подлежащие удалению при деактивации (создаются заново при необходимости).
 *
 * @return string[]
 */
function sad_znaniy_social_tables() {
	global $wpdb;
	return array( $wpdb->prefix . 'sz_social_queue', $wpdb->prefix . 'sz_aff_links' );
}
