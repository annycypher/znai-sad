<?php
/**
 * ПЕРЕНОС АВТОРСТВА НА НОВОГО АДМИНИСТРАТОРА (Этап 7.3, пункт 3 аудита).
 *
 * Делает ровно то, о чём договорено с владельцем, и ничего больше:
 *   1) полный дамп базы в ../backups (перед правками);
 *   2) переносит авторство ВСЕХ записей (любых типов) на нового админа;
 *   3) при необходимости ставит display_name «Редакция»;
 *   4) с флагом --delete-from удаляет старого пользователя (только если
 *      у него не осталось записей).
 *
 * Запуск:
 *   php _tools/switch-owner.php dry
 *   php _tools/switch-owner.php confirm --to=sz_owner
 *   php _tools/switch-owner.php confirm --to=sz_owner --delete-from=admin
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
require_once ABSPATH . 'wp-admin/includes/user.php';

$sz_args   = isset( $argv ) ? array_slice( $argv, 1 ) : array();
$sz_mode   = in_array( 'confirm', $sz_args, true ) ? 'confirm' : 'dry';
$sz_to_log = 'sz_owner';
$sz_del    = '';
$sz_rename = true;
$sz_port   = 10005;

foreach ( $sz_args as $sz_a ) {
	if ( preg_match( '/^--to=(.+)$/', (string) $sz_a, $sz_m ) ) {
		$sz_to_log = $sz_m[1];
	}
	if ( preg_match( '/^--delete-from=(.+)$/', (string) $sz_a, $sz_m ) ) {
		$sz_del = $sz_m[1];
	}
	if ( preg_match( '/^--port=(\d+)$/', (string) $sz_a, $sz_m ) ) {
		$sz_port = (int) $sz_m[1];
	}
	if ( '--no-rename' === $sz_a ) {
		$sz_rename = false;
	}
}

/**
 * Печатает пользователей и авторство по типам записей.
 *
 * @param string $title Заголовок блока.
 * @return void
 */
function sz_switch_report( $title ) {
	global $wpdb;
	echo "\n== " . $title . " ==\nпользователи:\n";
	foreach ( get_users( array( 'orderby' => 'ID' ) ) as $u ) {
		echo '  #' . $u->ID . ' ' . $u->user_login . ' | ' . implode( ',', $u->roles ) . ' | display: ' . $u->display_name . ' | ' . $u->user_email . "\n";
	}
	echo "авторство (без ревизий):\n";
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( "SELECT post_type, post_author, post_status, COUNT(*) c FROM {$wpdb->posts} WHERE post_type <> 'revision' GROUP BY post_type, post_author, post_status ORDER BY post_type, post_author, post_status" );
	foreach ( $rows as $r ) {
		echo '  ' . str_pad( (string) $r->post_type, 18 ) . ' автор #' . str_pad( (string) $r->post_author, 4 ) . str_pad( (string) $r->post_status, 10 ) . (int) $r->c . "\n";
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	echo '  всего без ревизий: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type <> 'revision'" ) . "\n";
}

$sz_to = get_user_by( 'login', $sz_to_log );
if ( ! $sz_to ) {
	echo "НЕТ пользователя «{$sz_to_log}» — создайте его в админке и повторите (сейчас ничего не меняю).\n";
	sz_switch_report( 'ТЕКУЩЕЕ СОСТОЯНИЕ' );
	return;
}
if ( ! in_array( 'administrator', (array) $sz_to->roles, true ) ) {
	echo 'У «' . $sz_to_log . "» нет роли administrator — правки не делаю.\n";
	return;
}

echo 'цель: #' . $sz_to->ID . ' ' . $sz_to->user_login . ' | display: ' . $sz_to->display_name . "\n";
sz_switch_report( 'ДО ПРАВОК' );

if ( 'dry' === $sz_mode ) {
	echo "\nрежим dry: ничего не менял.\n";
	return;
}

/* === Правки === */
global $wpdb;

// 1. Дамп перед правками (обычная практика проекта).
$sz_dump_bin = '';
foreach ( (array) glob( (string) getenv( 'APPDATA' ) . '/Local/lightning-services/mysql-*/bin/win64/bin/mysqldump.exe' ) as $sz_candidate ) {
	if ( is_file( $sz_candidate ) ) {
		$sz_dump_bin = $sz_candidate;
	}
}
$sz_dir = dirname( dirname( rtrim( ABSPATH, '/\\' ) ) ) . '/backups';
if ( ! is_dir( $sz_dir ) ) {
	mkdir( $sz_dir, 0775, true );
}
$sz_sql = $sz_dir . '/sz-before-owner-switch-' . current_time( 'Y-m-d-Hi' ) . '.sql';
$sz_cmd = escapeshellarg( '' !== $sz_dump_bin ? $sz_dump_bin : 'mysqldump' )
	. ' -h 127.0.0.1 -P ' . (int) $sz_port
	. ' -u ' . escapeshellarg( DB_USER ) . ' -p' . escapeshellarg( DB_PASSWORD )
	. ' --single-transaction --skip-lock-tables --default-character-set=utf8mb4'
	. ' --result-file=' . escapeshellarg( $sz_sql ) . ' ' . escapeshellarg( DB_NAME ) . ' 2>&1';
$sz_lines = array();
$sz_code  = 0;
exec( $sz_cmd, $sz_lines, $sz_code );
echo 'дамп перед правками: exit=' . $sz_code . ' | ' . ( is_file( $sz_sql ) ? round( filesize( $sz_sql ) / 1048576, 2 ) . ' МБ → ' . $sz_sql : 'ФАЙЛА НЕТ' ) . "\n";

// 2. Перенос авторства всех записей на нового админа.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$sz_moved = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_author = %d WHERE post_author <> %d", $sz_to->ID, $sz_to->ID ) );
echo 'перенесено записей: ' . (int) $sz_moved . "\n";

// 3. Отображаемое имя — только если владелец не поставил своё.
if ( $sz_rename && ( '' === (string) $sz_to->display_name || $sz_to->user_login === $sz_to->display_name ) ) {
	wp_update_user( array( 'ID' => $sz_to->ID, 'display_name' => 'Редакция' ) );
	echo "display_name → «Редакция»\n";
} elseif ( $sz_rename ) {
	echo 'display_name оставлен как есть: «' . $sz_to->display_name . "»\n";
}

// 4. Удаление старого пользователя — по явному флагу.
if ( '' !== $sz_del ) {
	$sz_old = get_user_by( 'login', $sz_del );
	if ( ! $sz_old ) {
		echo 'пользователь «' . $sz_del . "» не найден — удалять нечего.\n";
	} elseif ( (int) $sz_old->ID === (int) $sz_to->ID ) {
		echo "нельзя удалять цель переноса.\n";
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sz_left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d", $sz_old->ID ) );
		if ( $sz_left > 0 ) {
			echo 'у «' . $sz_del . '» осталось ' . $sz_left . " записей — не удаляю.\n";
		} else {
			$sz_email = (string) $sz_old->user_email;
			$sz_ok    = wp_delete_user( $sz_old->ID );
			echo $sz_ok ? 'пользователь «' . $sz_del . '» удалён.' : 'не удалось удалить «' . $sz_del . '».';
			echo ( get_option( 'admin_email' ) === $sz_email ) ? " ВНИМАНИЕ: admin_email всё ещё указывает на его почту — смените в «Настройки → Общие».\n" : "\n";
		}
	}
}

sz_switch_report( 'ПОСЛЕ ПРАВОК' );
echo "\nготово.\n";
