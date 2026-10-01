<?php
/**
 * SEO-Хаб (Этап 7.2): каркас.
 *
 * Общие требования ТЗ:
 *   • меню «📊 SEO-Хаб» с вкладками Ключи / Программатик / Индексация / Эффективность;
 *   • свои таблицы БД ($wpdb, префикс sz_seo_) через dbDelta + миграция по опции sz_seo_db_version;
 *   • WP-Cron раз в сутки, логи в опциях;
 *   • внешних запросов НЕТ до Этапа 8 (константа SZ_EXTERNAL_ON в wp-config).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Включены ли внешние сервисы (после Этапа 8).
 *
 * До переноса на хостинг константа не объявлена — все внешние вызовы
 * выключены, кнопки проверки связи неактивны.
 *
 * @return bool
 */
function sad_znaniy_seo_external_on() {
	return defined( 'SZ_EXTERNAL_ON' ) && SZ_EXTERNAL_ON;
}

/**
 * Имена таблиц хаба.
 *
 * @return array Ключ => полное имя таблицы.
 */
function sad_znaniy_seo_tables() {
	global $wpdb;
	return array(
		'keys'  => $wpdb->prefix . 'sz_seo_keys',
		'bots'  => $wpdb->prefix . 'sz_seo_bot_log',
		'views' => $wpdb->prefix . 'sz_seo_views',
	);
}

/**
 * Создаёт таблицы хаба (dbDelta) и отмечает версию схемы.
 *
 * Вызывается на init: пока версия совпадает — только чтение опции.
 */
function sad_znaniy_seo_maybe_install() {
	if ( '1' === get_option( 'sz_seo_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$t       = sad_znaniy_seo_tables();
	$keys    = $t['keys'];
	$bots    = $t['bots'];
	$views   = $t['views'];

	$sql = array();

	// Ключи Вордстата с авторазметкой на программатик-URL.
	$sql[] = "CREATE TABLE {$keys} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		phrase varchar(191) NOT NULL,
		freq int(10) unsigned NOT NULL DEFAULT 0,
		matched_url varchar(255) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'manual',
		created datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY phrase (phrase),
		KEY status (status),
		KEY freq (freq)
	) {$charset};";

	// Визиты поисковых ботов к страницам программатика и календаря.
	$sql[] = "CREATE TABLE {$bots} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		url varchar(191) NOT NULL,
		bot varchar(40) NOT NULL,
		day date NOT NULL,
		at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY url_bot_day (url,bot,day),
		KEY bot (bot)
	) {$charset};";

	// Свои счётчики визитов и кликов (без куки, агрегат по дню).
	$sql[] = "CREATE TABLE {$views} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		url varchar(191) NOT NULL,
		day date NOT NULL,
		views int(10) unsigned NOT NULL DEFAULT 0,
		clicks int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY url_day (url,day),
		KEY day (day)
	) {$charset};";

	foreach ( $sql as $query ) {
		dbDelta( $query );
	}

	update_option( 'sz_seo_db_version', '1', false );
}
add_action( 'init', 'sad_znaniy_seo_maybe_install', 5 );

/**
 * Меню «📊 SEO-Хаб» с вкладками: Ключи / Программатик / Индексация / Эффективность.
 */
function sad_znaniy_seo_hub_menu() {
	add_menu_page(
		__( 'SEO-Хаб', 'sad-znaniy' ),
		__( '📊 SEO-Хаб', 'sad-znaniy' ),
		'manage_options',
		'sad-seo-hub',
		'sad_znaniy_seo_hub_keys_page',
		'dashicons-chart-area',
		81
	);
	add_submenu_page( 'sad-seo-hub', __( 'Ключи', 'sad-znaniy' ), __( 'Ключи', 'sad-znaniy' ), 'manage_options', 'sad-seo-hub', 'sad_znaniy_seo_hub_keys_page' );
	add_submenu_page( 'sad-seo-hub', __( 'Программатик', 'sad-znaniy' ), __( 'Программатик', 'sad-znaniy' ), 'manage_options', 'sad-seo-hub-programmatic', 'sad_znaniy_programmatic_admin_page' );
	add_submenu_page( 'sad-seo-hub', __( 'Индексация', 'sad-znaniy' ), __( 'Индексация', 'sad-znaniy' ), 'manage_options', 'sad-seo-hub-index', 'sad_znaniy_seo_hub_index_page' );
	add_submenu_page( 'sad-seo-hub', __( 'Эффективность', 'sad-znaniy' ), __( 'Эффективность', 'sad-znaniy' ), 'manage_options', 'sad-seo-hub-eff', 'sad_znaniy_seo_hub_eff_page' );
}
add_action( 'admin_menu', 'sad_znaniy_seo_hub_menu', 9 );

/**
 * Добавляет строку в журнал хаба (опция sz_seo_log, последние 20 записей).
 *
 * @param string $message Сообщение.
 */
function sad_znaniy_seo_log( $message ) {
	$log = get_option( 'sz_seo_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	array_unshift( $log, current_time( 'Y-m-d H:i' ) . ' — ' . $message );
	update_option( 'sz_seo_log', array_slice( $log, 0, 20 ), false );
}

/**
 * Запись строки в таблицу хаба (INSERT ... ON DUPLICATE KEY UPDATE).
 *
 * @param string $table     Полное имя таблицы.
 * @param array  $data      Данные (колонка => значение).
 * @param string $increment Колонка-счётчик для инкремента при дубле ('' — обновить значениями).
 */
function sad_znaniy_seo_db_write( $table, $data, $increment = '' ) {
	global $wpdb;

	$cols   = array();
	$values = array();
	foreach ( $data as $col => $val ) {
		$cols[]   = $col;
		$values[] = $val;
	}

	$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
	$sql          = "INSERT INTO {$table} (" . implode( ', ', $cols ) . ') VALUES (' . $placeholders . ')';

	if ( '' !== $increment ) {
		$sql .= ' ON DUPLICATE KEY UPDATE ' . $increment . ' = ' . $increment . ' + 1';
	} else {
		$updates = array();
		foreach ( $cols as $col ) {
			$updates[] = $col . ' = VALUES(' . $col . ')';
		}
		$sql .= ' ON DUPLICATE KEY UPDATE ' . implode( ', ', $updates );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- имена таблиц/колонок заданы кодом.
	$wpdb->query( $wpdb->prepare( $sql, $values ) );
}

/**
 * Суточная задача хаба (WP-Cron). До Этапа 8 — только отметка в журнале.
 */
function sad_znaniy_seo_daily_sync() {
	if ( ! sad_znaniy_seo_external_on() ) {
		sad_znaniy_seo_log( 'Суточная синхронизация: внешние сервисы выключены (SZ_EXTERNAL_ON). Пропуск.' );
		return;
	}

	sad_znaniy_seo_log( 'Суточная синхронизация: внешние сервисы включены. Позиции и статусы обновляются (Этап 8).' );
}
add_action( 'sz_seo_daily_sync', 'sad_znaniy_seo_daily_sync' );

/**
 * Ставит суточную задачу в расписание (один раз).
 */
function sad_znaniy_seo_schedule_cron() {
	if ( ! wp_next_scheduled( 'sz_seo_daily_sync' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 04:00' ), 'daily', 'sz_seo_daily_sync' );
	}
}
add_action( 'init', 'sad_znaniy_seo_schedule_cron', 6 );

/**
 * Общая плашка «Как этим пользоваться» для экранов хаба.
 *
 * @param string $title Заголовок плашки.
 * @param array  $lines Пункты инструкции (допускается простой HTML).
 * @param string $more  Раздел ADMINGUIDE для ссылки.
 */
function sad_znaniy_seo_help_box( $title, $lines, $more = '' ) {
	?>
	<details style="margin:10px 0 14px;">
		<summary style="cursor:pointer;font-weight:600;"><?php echo esc_html( $title ); ?></summary>
		<ol style="margin-top:8px;padding-left:20px;">
			<?php foreach ( $lines as $line ) : ?>
				<li><?php echo wp_kses_post( $line ); ?></li>
			<?php endforeach; ?>
		</ol>
		<?php if ( '' !== $more ) : ?>
			<p style="margin-top:6px;">Подробнее — в <b>ADMINGUIDE.md → <?php echo esc_html( $more ); ?></b>.</p>
		<?php endif; ?>
	</details>
	<?php
}
