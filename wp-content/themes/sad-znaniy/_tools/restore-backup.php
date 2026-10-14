<?php
/**
 * ВОССТАНОВЛЕНИЕ ИЗ БЭКАПА (Этап 7.3, партия S3).
 *
 * Два режима:
 *   --check    симуляция: проверяет пару «дамп + архив», разворачивает дамп в
 *              ВРЕМЕННУЮ базу и сверяет таблицы/строки с текущей базой. Ничего
 *              боевого не меняет, временную базу удаляет.
 *   --restore  настоящее восстановление: страховочный дамп текущей базы,
 *              импорт дампа, распаковка архива поверх wp-content. Требует
 *              явного --yes и указания файлов.
 *
 * Примеры:
 *   php _tools/restore-backup.php --check
 *   php _tools/restore-backup.php --check --db=../backups/db-2026-10-14.sql --zip=../backups/sz-wp-content-2026-10-14.zip
 *   php _tools/restore-backup.php --restore --yes --db=... --zip=...
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

$sz_args = isset( $argv ) ? array_slice( $argv, 1 ) : array();
$sz_db   = '';
$sz_zip  = '';
$sz_mode = 'check';
$sz_yes  = false;
$sz_port = defined( 'DB_PORT' ) ? (int) DB_PORT : 10005;

foreach ( $sz_args as $sz_a ) {
	if ( preg_match( '/^--db=(.+)$/', (string) $sz_a, $sz_m ) ) {
		$sz_db = $sz_m[1];
	}
	if ( preg_match( '/^--zip=(.+)$/', (string) $sz_a, $sz_m ) ) {
		$sz_zip = $sz_m[1];
	}
	if ( preg_match( '/^--port=(\d+)$/', (string) $sz_a, $sz_m ) ) {
		$sz_port = (int) $sz_m[1];
	}
	if ( '--restore' === $sz_a ) {
		$sz_mode = 'restore';
	}
	if ( '--yes' === $sz_a ) {
		$sz_yes = true;
	}
}

/**
 * Папка бэкапов (вне webroot).
 *
 * @return string
 */
function sz_restore_dir() {
	return dirname( dirname( rtrim( ABSPATH, '/\\' ) ) ) . '/backups';
}

/**
 * Путь к mysql.exe/mysqldump.exe из LocalWP (или пустая строка).
 *
 * @param string $tool mysql|mysqldump.
 * @return string
 */
function sz_restore_bin( $tool ) {
	foreach ( (array) glob( (string) getenv( 'APPDATA' ) . '/Local/lightning-services/mysql-*/bin/win64/bin/' . $tool . '.exe' ) as $candidate ) {
		if ( is_file( $candidate ) ) {
			return $candidate;
		}
	}
	return $tool;
}

/**
 * Самый свежий файл по маске.
 *
 * @param string $pattern Маска.
 * @return string
 */
function sz_restore_newest( $pattern ) {
	$files = glob( $pattern );
	if ( ! $files ) {
		return '';
	}
	usort(
		$files,
		static function ( $a, $b ) {
			return filemtime( $b ) <=> filemtime( $a );
		}
	);
	return (string) $files[0];
}

/**
 * Импортирует SQL-файл в указанную базу: клиентом mysql, иначе PHP-разбором.
 *
 * @param string $file Файл дампа.
 * @param string $db   База.
 * @param int    $port Порт MySQL.
 * @return array array( bool $ok, string $how, string $note )
 */
function sz_restore_import( $file, $db, $port ) {
	$bin  = sz_restore_bin( 'mysql' );
	$exec = function_exists( 'exec' ) && false === stripos( (string) ini_get( 'disable_functions' ), 'exec' );

	if ( $exec && '' !== $bin ) {
		$cmd = 'cmd /c ""' . $bin . '" -h 127.0.0.1 -P ' . (int) $port
			. ' -u "' . str_replace( '"', '', (string) DB_USER ) . '"'
			. ' -p"' . str_replace( '"', '', (string) DB_PASSWORD ) . '"'
			. ' ' . $db . ' < "' . $file . '"" 2>&1';
		$out  = array();
		$code = 0;
		@exec( $cmd, $out, $code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( 0 === $code ) {
			return array( true, 'mysql', '' );
		}
		$php = sz_restore_import_php( $file, $db );
		return array( $php, 'php', 'mysql не сработал (exit=' . $code . '), импорт сделан PHP' );
	}

	return array( sz_restore_import_php( $file, $db ), 'php', '' );
}

/**
 * Резервный импорт на PHP: простой разбор по «;\n» (наши дампы и mysqldump
 * экранируют переводы строк внутри значений, поэтому разбор безопасен).
 *
 * @param string $file Файл дампа.
 * @param string $db   База.
 * @return bool
 */
function sz_restore_import_php( $file, $db ) {
	global $wpdb;
	$fh = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $fh ) {
		return false;
	}

	$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
	$buffer = '';
	$ok     = true;
	while ( false !== ( $line = fgets( $fh ) ) ) {
		$trim = rtrim( $line );
		if ( '' === $trim || '--' === substr( $trim, 0, 2 ) || '/*' === substr( $trim, 0, 2 ) ) {
			continue;
		}
		$buffer .= $line;
		if ( ';' === substr( $trim, -1 ) ) {
			$sql = trim( $buffer );
			$buffer = '';
			if ( '' === $sql ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $wpdb->query( $sql ) ) {
				$ok = false;
			}
		}
	}
	fclose( $fh );
	$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
	return $ok;
}

/* === Основной ход ======================================================== */

if ( '' === $sz_db ) {
	$sz_db = sz_restore_newest( sz_restore_dir() . '/db-*.sql' );
}
if ( '' === $sz_zip ) {
	$sz_zip = sz_restore_newest( sz_restore_dir() . '/sz-wp-content-*.zip' );
}

echo 'режим: ' . ( 'restore' === $sz_mode ? "ВОССТАНОВЛЕНИЕ (боевая база!)" : 'проверка (симуляция, ничего не меняем)' ) . "\n";
echo 'дамп: ' . ( '' !== $sz_db ? $sz_db : 'НЕ НАЙДЕН' ) . "\n";
echo 'архив: ' . ( '' !== $sz_zip ? $sz_zip : 'НЕ НАЙДЕН' ) . "\n\n";

if ( '' === $sz_db || ! is_file( $sz_db ) ) {
	echo "нет файла дампа — выхожу.\n";
	return;
}
if ( '' !== $sz_zip && ! is_file( $sz_zip ) ) {
	echo "нет файла архива — выхожу.\n";
	return;
}

$sz_sql  = (string) file_get_contents( $sz_db );
$sz_mb   = round( strlen( $sz_sql ) / 1048576, 2 );
$sz_cre  = substr_count( $sz_sql, 'CREATE TABLE' );
$sz_ins  = substr_count( $sz_sql, 'INSERT INTO' );
echo 'дамп: ' . $sz_mb . ' МБ | CREATE TABLE: ' . $sz_cre . ' | INSERT: ' . $sz_ins . "\n";
if ( $sz_cre < 5 ) {
	echo "похоже, это не дамп базы — выхожу.\n";
	return;
}
unset( $sz_sql );

if ( '' !== $sz_zip ) {
	$sz_z = new ZipArchive();
	if ( true === $sz_z->open( $sz_zip ) ) {
		$sz_theme = false;
		$sz_up    = false;
		for ( $i = 0; $i < $sz_z->numFiles; $i++ ) {
			$sz_n = (string) $sz_z->getNameIndex( $i );
			if ( false !== strpos( $sz_n, 'themes/sad-znaniy/' ) ) {
				$sz_theme = true;
			}
			if ( false !== strpos( $sz_n, 'uploads/' ) ) {
				$sz_up = true;
			}
		}
		echo 'архив: ' . round( filesize( $sz_zip ) / 1048576, 2 ) . ' МБ | файлов: ' . $sz_z->numFiles
			. ' | тема: ' . ( $sz_theme ? 'да' : 'НЕТ' ) . ' | uploads: ' . ( $sz_up ? 'да' : 'НЕТ' ) . "\n";
		$sz_z->close();
	} else {
		echo "не удалось открыть архив\n";
	}
}

if ( 'check' === $sz_mode ) {
	/* --- Симуляция: разворачиваем дамп во временную базу и сверяем --- */
	$sz_temp = DB_NAME . '_restore_test';
	$sz_link = @new mysqli( '127.0.0.1', DB_USER, DB_PASSWORD, '', (int) $sz_port ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( $sz_link->connect_errno ) {
		echo 'нет связи с MySQL: ' . $sz_link->connect_error . "\n";
		return;
	}
	$sz_link->query( 'DROP DATABASE IF EXISTS `' . $sz_temp . '`' );
	if ( ! $sz_link->query( 'CREATE DATABASE `' . $sz_temp . '` CHARACTER SET utf8mb4' ) ) {
		echo "не удалось создать временную базу\n";
		return;
	}
	echo "временная база создана: " . $sz_temp . "\n";

	$sz_link->close();
	list( $sz_ok, $sz_how, $sz_note ) = sz_restore_import( $sz_db, $sz_temp, $sz_port );
	echo 'импорт: ' . ( $sz_ok ? 'ок' : 'ОШИБКИ' ) . ' (' . $sz_how . ')' . ( '' !== $sz_note ? ' — ' . $sz_note : '' ) . "\n";

	$sz_link = @new mysqli( '127.0.0.1', DB_USER, DB_PASSWORD, $sz_temp, (int) $sz_port ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$sz_report = array();
	$sz_t_new = $sz_link->query( 'SHOW TABLES' )->num_rows;
	$sz_t_old = count( $GLOBALS['wpdb']->get_col( 'SHOW TABLES' ) );
	$sz_report[] = 'таблиц: в дампе ' . $sz_t_new . ', в текущей базе ' . $sz_t_old;

	global $wpdb;
	$sz_pairs = array(
		'users'                  => array( $wpdb->users, '' ),
		'posts'                  => array( $wpdb->posts, '' ),
		'postmeta'               => array( $wpdb->postmeta, '' ),
		'options (без транзитов)' => array( $wpdb->options, " WHERE option_name NOT LIKE '\\_transient%'" ),
		'seo_keys'               => array( $wpdb->prefix . 'sz_seo_keys', '' ),
	);
	foreach ( $sz_pairs as $sz_name => $sz_pair ) {
		list( $sz_table, $sz_where ) = $sz_pair;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sz_live = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$sz_table}`" . $sz_where );
		$sz_res  = $sz_link->query( 'SELECT COUNT(*) FROM `' . $sz_table . '`' . $sz_where );
		$sz_tmp  = $sz_res ? (int) $sz_res->fetch_row()[0] : -1;
		$sz_mark = ( $sz_tmp === $sz_live ) ? 'совпадает' : 'РАЗНИЦА';
		$sz_report[] = $sz_name . ': дамп ' . $sz_tmp . ' / сейчас ' . $sz_live . ' — ' . $sz_mark;
	}
	$sz_link->close();

	echo "сверка:\n";
	foreach ( $sz_report as $sz_line ) {
		echo '  ' . $sz_line . "\n";
	}

	$sz_link = @new mysqli( '127.0.0.1', DB_USER, DB_PASSWORD, '', (int) $sz_port ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$sz_link->query( 'DROP DATABASE IF EXISTS `' . $sz_temp . '`' );
	$sz_link->close();
	echo "временная база удалена. Боевые данные не менялись.\n";
	echo $sz_ok ? "\nИТОГ: восстановление из этой пары пройдёт, данные читаются.\n" : "\nИТОГ: при импорте были ошибки — проверьте дамп.\n";
	return;
}

/* --- Настоящее восстановление --- */
if ( ! $sz_yes ) {
	echo "ВОССТАНОВЛЕНИЕ требует явного подтверждения: добавьте --yes.\n";
	return;
}

$sz_safety = sz_restore_dir() . '/sz-before-restore-' . current_time( 'Y-m-d-Hi' ) . '.sql';
$sz_bin    = sz_restore_bin( 'mysqldump' );
$sz_cmd    = escapeshellarg( $sz_bin ) . ' -h 127.0.0.1 -P ' . (int) $sz_port
	. ' -u "' . str_replace( '"', '', (string) DB_USER ) . '" -p"' . str_replace( '"', '', (string) DB_PASSWORD ) . '"'
	. ' --single-transaction --skip-lock-tables --result-file=' . escapeshellarg( $sz_safety ) . ' ' . DB_NAME . ' 2>&1';
$sz_o   = array();
$sz_c   = 0;
@exec( $sz_cmd, $sz_o, $sz_c ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
echo 'страховочный дамп текущей базы: exit=' . $sz_c . ' | ' . ( is_file( $sz_safety ) ? round( filesize( $sz_safety ) / 1048576, 2 ) . ' МБ' : 'НЕ СОЗДАН' ) . "\n";

list( $sz_ok, $sz_how, $sz_note ) = sz_restore_import( $sz_db, DB_NAME, $sz_port );
echo 'импорт дампа в боевую базу: ' . ( $sz_ok ? 'ок' : 'ОШИБКИ' ) . ' (' . $sz_how . ')' . ( '' !== $sz_note ? ' — ' . $sz_note : '' ) . "\n";

if ( '' !== $sz_zip ) {
	$sz_z = new ZipArchive();
	if ( true === $sz_z->open( $sz_zip ) ) {
		$sz_z->extractTo( rtrim( ABSPATH, '/\\' ) );
		$sz_z->close();
		echo "архив распакован поверх " . rtrim( ABSPATH, '/\\' ) . "\n";
	}
}

if ( function_exists( 'sad_znaniy_cache_purge' ) ) {
	sad_znaniy_cache_purge();
	echo "кэш страниц очищен\n";
}
echo "готово. Проверьте сайт и смените пароли, если восстановление делалось из-за взлома.\n";
