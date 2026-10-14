<?php
/**
 * ЭТАП 7.3 — БЭКАП-ДИСЦИПЛИНА (партия S3, 14.10.2026).
 *
 * Ежедневный бэкап: дамп базы + архив wp-content (вложения и тема) в папку
 * `../backups` — ВНЕ webroot, хранение 7 последних копий каждого типа.
 * Запуск: WP-Cron (на боевом его дёргает системный cron), вручную — кнопкой
 * на экране «🛡 Безопасность». Дамп делается mysqldump, если он доступен,
 * иначе — встроенным PHP-дампером (работает и на хостинге без exec).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Настройки бэкапа.
 *
 * @return array
 */
function sad_znaniy_backup_settings() {
	return apply_filters(
		'sad_znaniy_backup_settings',
		array(
			'keep' => 7,
			'hour' => 3,
		)
	);
}

/**
 * Пути файлов бэкапа на дату.
 *
 * @param string $date Дата ГГГГ-ММ-ДД (по умолчанию — сегодня).
 * @return array
 */
function sad_znaniy_backup_paths( $date = '' ) {
	if ( '' === $date ) {
		$date = current_time( 'Y-m-d' );
	}
	$dir = sad_znaniy_security_backup_dir();
	return array(
		'dir' => $dir,
		'db'  => $dir . '/db-' . $date . '.sql',
		'zip' => $dir . '/sz-wp-content-' . $date . '.zip',
	);
}

/**
 * Планирует ежедневный бэкап (по времени сайта).
 *
 * @return void
 */
function sad_znaniy_backup_schedule() {
	if ( wp_next_scheduled( 'sz_daily_backup' ) ) {
		return;
	}
	$settings = sad_znaniy_backup_settings();
	$stamp    = strtotime( current_time( 'Y-m-d' ) . ' ' . (int) $settings['hour'] . ':00:00' );
	if ( ! $stamp || $stamp < time() ) {
		$stamp = strtotime( current_time( 'Y-m-d' ) . ' ' . (int) $settings['hour'] . ':00:00 +1 day' );
	}
	wp_schedule_event( $stamp, 'daily', 'sz_daily_backup' );
}
add_action( 'init', 'sad_znaniy_backup_schedule' );

/* === Дамп базы =========================================================== */

/**
 * Дамп базы: mysqldump при возможности, иначе PHP-дампер.
 *
 * @param string $file Куда писать.
 * @return array array( bool $ok, string $how )
 */
function sad_znaniy_backup_dump( $file ) {
	$bin = '';
	foreach ( (array) glob( (string) getenv( 'APPDATA' ) . '/Local/lightning-services/mysql-*/bin/win64/bin/mysqldump.exe' ) as $candidate ) {
		if ( is_file( $candidate ) ) {
			$bin = $candidate;
		}
	}
	$bin        = (string) apply_filters( 'sad_znaniy_backup_mysqldump', '' !== $bin ? $bin : 'mysqldump' );
	$disabled   = (string) ini_get( 'disable_functions' );
	$can_exec   = function_exists( 'exec' ) && false === stripos( $disabled, 'exec' );
	$dump_works = false;

	if ( $can_exec ) {
		$port  = (int) apply_filters( 'sad_znaniy_backup_port', defined( 'DB_PORT' ) ? (int) DB_PORT : 10005 );
		$hosts = array( '127.0.0.1' );
		if ( ! in_array( (string) DB_HOST, $hosts, true ) ) {
			$hosts[] = (string) DB_HOST;
		}
		foreach ( $hosts as $host ) {
			$cmd = escapeshellarg( $bin ) . ' -h ' . $host . ' -P ' . $port
				. ' -u "' . str_replace( '"', '', (string) DB_USER ) . '"'
				. ' -p"' . str_replace( '"', '', (string) DB_PASSWORD ) . '"'
				. ' --single-transaction --skip-lock-tables --default-character-set=utf8mb4'
				. ' --result-file=' . escapeshellarg( $file ) . ' ' . (string) DB_NAME . ' 2>&1';
			$out  = array();
			$code = 0;
			@exec( $cmd, $out, $code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( 0 === $code && is_file( $file ) && filesize( $file ) > 4096 ) {
				$dump_works = true;
				break;
			}
		}
	}

	if ( $dump_works ) {
		return array( true, 'mysqldump' );
	}
	return array( sad_znaniy_backup_dump_php( $file ), 'php' );
}

/**
 * Резервный дампер на PHP: SHOW CREATE TABLE + INSERT порциями.
 *
 * @param string $file Куда писать.
 * @return bool
 */
function sad_znaniy_backup_dump_php( $file ) {
	global $wpdb;
	$fh = @fopen( $file, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $fh ) {
		return false;
	}

	fwrite( $fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n" );
	$tables = $wpdb->get_col( 'SHOW TABLES' );
	foreach ( (array) $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		if ( ! $create || ! isset( $create[1] ) ) {
			continue;
		}
		fwrite( $fh, "\nDROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n" );

		$offset = 0;
		while ( true ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT * FROM `{$table}` LIMIT 500 OFFSET {$offset}", ARRAY_A );
			if ( empty( $rows ) ) {
				break;
			}
			foreach ( $rows as $row ) {
				$values = array();
				foreach ( $row as $value ) {
					$values[] = ( null === $value ) ? 'NULL' : "'" . esc_sql( (string) $value ) . "'";
				}
				fwrite( $fh, "INSERT INTO `{$table}` VALUES (" . implode( ',', $values ) . ");\n" );
			}
			$offset += 500;
			unset( $rows );
		}
	}

	fwrite( $fh, "\nSET FOREIGN_KEY_CHECKS=1;\n" );
	fclose( $fh );
	return is_file( $file ) && filesize( $file ) > 4096;
}


/* === Архив wp-content ==================================================== */

/**
 * Что не кладём в архив (мусор и служебные папки).
 *
 * @return array
 */
function sad_znaniy_backup_skip() {
	return array( '_tools/', '_docs/', '_design/', '_game-test/', 'deploy/', 'backups/', 'cache/', 'upgrade/', 'ai1wm-backups/', 'node_modules/', '.git/', 'debug.log', '.DS_Store', 'Thumbs.db', '.sql', '.zip', '.log' );
}

/**
 * Архив wp-content (вложения, тема и остальное; ядро в архив не входит).
 *
 * @param string $file Куда писать.
 * @return array array( bool $ok, int $files )
 */
function sad_znaniy_backup_zip( $file ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return array( false, 0 );
	}
	if ( is_file( $file ) ) {
		unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return array( false, 0 );
	}

	$root  = rtrim( ABSPATH, '/\\' ) . '/wp-content';
	$skip  = sad_znaniy_backup_skip();
	$count = 0;
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $item ) {
		$rel = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $root ) + 1 ) );
		$bad = false;
		foreach ( $skip as $needle ) {
			if ( false !== strpos( $rel, $needle ) ) {
				$bad = true;
				break;
			}
		}
		if ( $bad || ! $item->isFile() ) {
			continue;
		}
		if ( $zip->addFile( $item->getPathname(), 'wp-content/' . $rel ) ) {
			$count++;
		}
	}
	$zip->close();

	return array( $count > 0 && is_file( $file ), $count );
}

/* === Ротация ============================================================= */

/**
 * Оставляет последние N копий каждого типа (дампы и архивы).
 * Файлы `sz-before-*` (ручные дампы перед правками) не удаляются.
 *
 * @return int Сколько файлов удалено.
 */
function sad_znaniy_backup_rotate() {
	$keep    = (int) sad_znaniy_backup_settings()['keep'];
	$dir     = sad_znaniy_security_backup_dir();
	$removed = 0;

	foreach ( array( 'db-*.sql', 'sz-wp-content-*.zip' ) as $pattern ) {
		$files = glob( $dir . '/' . $pattern );
		if ( ! $files || count( $files ) <= $keep ) {
			continue;
		}
		usort(
			$files,
			static function ( $a, $b ) {
				return filemtime( $b ) <=> filemtime( $a );
			}
		);
		foreach ( array_slice( $files, $keep ) as $old ) {
			if ( unlink( $old ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$removed++;
			}
		}
	}

	return $removed;
}


/* === История и запуск ==================================================== */

/**
 * История запусков бэкапа (последние 10).
 *
 * @return array
 */
function sad_znaniy_backup_history() {
	$history = get_option( 'sad_znaniy_backup_history' );
	return is_array( $history ) ? $history : array();
}

/**
 * Добавляет запись в историю бэкапов.
 *
 * @param string $status ok | error.
 * @param string $note   Что произошло.
 * @return void
 */
function sad_znaniy_backup_log( $status, $note ) {
	$history = sad_znaniy_backup_history();
	array_unshift(
		$history,
		array(
			'time'   => current_time( 'mysql' ),
			'status' => $status,
			'note'   => $note,
		)
	);
	update_option( 'sad_znaniy_backup_history', array_slice( $history, 0, 10 ), false );
}

/**
 * Делает бэкап (дамп + архив) и чистит старые копии.
 *
 * @param bool $manual Ручной запуск (тогда игнорируем «сегодня уже делали»).
 * @return string Отчёт.
 */
function sad_znaniy_backup_run( $manual = false ) {
	$paths = sad_znaniy_backup_paths();

	if ( ! is_dir( $paths['dir'] ) && ! mkdir( $paths['dir'], 0775, true ) ) {
		sad_znaniy_backup_log( 'error', 'не удалось создать папку ' . $paths['dir'] );
		return 'ОШИБКА: не удалось создать папку ' . $paths['dir'];
	}
	if ( ! $manual && is_file( $paths['db'] ) && is_file( $paths['zip'] ) ) {
		return 'бэкап за сегодня уже есть — пропускаю';
	}
	if ( get_transient( 'sz_backup_lock' ) ) {
		return 'бэкап уже выполняется';
	}
	set_transient( 'sz_backup_lock', 1, 15 * MINUTE_IN_SECONDS );

	list( $db_ok, $how )   = sad_znaniy_backup_dump( $paths['db'] );
	list( $zip_ok, $cnt )  = sad_znaniy_backup_zip( $paths['zip'] );
	$removed               = sad_znaniy_backup_rotate();

	delete_transient( 'sz_backup_lock' );

	$db_mb  = $db_ok ? round( filesize( $paths['db'] ) / 1048576, 2 ) : 0;
	$zip_mb = $zip_ok ? round( filesize( $paths['zip'] ) / 1048576, 2 ) : 0;
	$note   = sprintf(
		'дамп %s МБ (%s)%s, архив %s МБ (%d файлов)%s, удалено старых: %d',
		$db_mb,
		$how,
		$db_ok ? '' : ' — ОШИБКА',
		$zip_mb,
		$cnt,
		$zip_ok ? '' : ' — ОШИБКА',
		$removed
	);

	sad_znaniy_backup_log( ( $db_ok && $zip_ok ) ? 'ok' : 'error', $note );

	return ( $db_ok && $zip_ok ? 'готово: ' : 'внимание: ' ) . $note;
}
add_action( 'sz_daily_backup', 'sad_znaniy_backup_run' );

/* === Панель на экране «🛡 Безопасность» ================================== */

/**
 * Блок «Резервные копии»: кнопка ручного запуска и история.
 *
 * @return void
 */
function sad_znaniy_backup_panel() {
	$history = sad_znaniy_backup_history();

	echo '<h3>Бэкапы (дамп + архив, вне webroot)</h3>';
	echo '<form method="post" style="margin:6px 0 12px;">';
	wp_nonce_field( 'sz_security_action' );
	echo '<input type="hidden" name="sz_security_action" value="backup_now">'
		. '<button class="button button-primary">Сделать бэкап сейчас</button> '
		. '<span class="description">хранение 7 копий каждого типа в <code>' . esc_html( sad_znaniy_security_backup_dir() ) . '</code></span></form>';

	if ( empty( $history ) ) {
		echo '<p>Запусков пока не было.</p>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr><th>Когда</th><th>Итог</th><th>Подробности</th></tr></thead><tbody>';
	foreach ( $history as $row ) {
		$ok = isset( $row['status'] ) && 'ok' === $row['status'];
		echo '<tr><td>' . esc_html( isset( $row['time'] ) ? (string) $row['time'] : '' ) . '</td><td>'
			. ( $ok ? '✔' : '❌' ) . '</td><td>' . esc_html( isset( $row['note'] ) ? (string) $row['note'] : '' ) . '</td></tr>';
	}
	echo '</tbody></table>';
}
