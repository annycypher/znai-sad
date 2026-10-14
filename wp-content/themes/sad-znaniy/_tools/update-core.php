<?php
/**
 * ОБНОВЛЕНИЕ ЯДРА WORDPRESS (Этап 7.3, партия S4).
 *
 * Порядок, зафиксированный в проекте: перед обновлением ядра обязателен бэкап
 * (он делается этим же скриптом), обновление — только с локалки, после
 * обновления — сверка целостности и отчёт.
 *
 * Запуск:
 *   php _tools/update-core.php dry
 *   php _tools/update-core.php confirm
 *
 * @package sad-znaniy
 */

// Только CLI: скрипты разработки не исполняются из браузера (Этап 7.3, S2).
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'Скрипт запускается только из командной строки.' );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

$sz_args    = isset( $argv ) ? array_slice( $argv, 1 ) : array();
$sz_confirm = in_array( 'confirm', $sz_args, true );

echo 'сейчас: WordPress ' . get_bloginfo( 'version' ) . ' | PHP ' . PHP_VERSION . "\n";

wp_version_check();
$sz_transient = get_site_transient( 'update_core' );
$sz_offer     = null;

if ( is_object( $sz_transient ) && ! empty( $sz_transient->updates ) && is_array( $sz_transient->updates ) ) {
	foreach ( $sz_transient->updates as $sz_u ) {
		if ( ! is_object( $sz_u ) || empty( $sz_u->response ) ) {
			continue;
		}
		if ( 'upgrade' === $sz_u->response || 'autoupdate' === $sz_u->response ) {
			$sz_offer = $sz_u;
			break;
		}
	}
}

if ( ! $sz_offer ) {
	echo "обновлений ядра не предложено (или нет связи с wordpress.org) — выхожу.\n";
	return;
}

echo 'предложено: ' . $sz_offer->current . ' (язык ' . ( isset( $sz_offer->locale ) ? $sz_offer->locale : '-' ) . ")\n";
if ( ! $sz_confirm ) {
	echo "режим dry: ничего не меняю. Для обновления запустите с 'confirm'.\n";
	return;
}

// 1. Бэкап перед обновлением (обязательное правило проекта).
if ( function_exists( 'sad_znaniy_backup_run' ) ) {
	echo 'бэкап перед обновлением: ' . sad_znaniy_backup_run( true ) . "\n";
}

// 2. Обновление ядра.
$sz_upgrader = new Core_Upgrader( new Automatic_Upgrader_Skin() );
$sz_result   = $sz_upgrader->upgrade( $sz_offer );

if ( is_wp_error( $sz_result ) ) {
	echo 'ОШИБКА обновления: ' . $sz_result->get_error_message() . "\n";
	return;
}

// 3. Обновление схемы базы (обычно запускается при входе в админку).
if ( function_exists( 'wp_upgrade' ) ) {
	wp_upgrade();
}

echo 'после обновления: WordPress ' . get_bloginfo( 'version' ) . "\n";

// 4. Сверка целостности с новым набором сумм.
if ( function_exists( 'sad_znaniy_integrity_run' ) ) {
	sad_znaniy_integrity_run( true );
	echo 'целостность: ' . sad_znaniy_integrity_summary() . "\n";
}

echo "готово. Дальше: smoke-тест страниц и проверка логов.\n";
