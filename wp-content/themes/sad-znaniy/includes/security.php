<?php
/**
 * ЭТАП 7.3 — БЕЗОПАСНОСТЬ И ЗАЩИТА (партия S2, 14.10.2026).
 *
 * Нативно и своими средствами, без плагинов:
 *   • XML-RPC выключен (403), pingback-заголовок убран;
 *   • REST-список пользователей доступен только тем, кто может list_users;
 *   • авторские архивы и ?author=N для гостей отдают 404 (перечисление логинов);
 *   • заголовки безопасности (XFO, nosniff, Referrer-Policy, Permissions-Policy)
 *     и базовый CSP для фронтенда (админку не трогаем — Gutenberg);
 *   • Gravatar заменён локальной заглушкой — сайт не делает внешних запросов;
 *   • свой лимитер попыток входа: 5 неудач → блокировка 15 минут, журнал,
 *     экран «🛡 Безопасность» с разблокировкой IP;
 *   • эскалация блокировок (батч B, 06.11.2026): 3-й блок за 24 часа → 24 часа,
 *     с 6-го блока → 30 дней и постоянный список в logs/ (вне webroot);
 *   • письма о блокировках — не чаще одного в час на IP, с агрегатом попыток,
 *     логинов и user-agent; попадание в постоянный список — отдельным письмом;
 *   • лимитер покрывает wp-login.php (вход), wp-login.php?action=lostpassword
 *     (подбор через сброс пароля) и POST с паролем к закрытым записям.
 *
 * Параметры лимитера меняются фильтром sad_znaniy_security_settings.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Версия схемы таблиц безопасности.
 *
 * @return string
 */
function sad_znaniy_security_schema_version() {
	return '2';
}

/**
 * Таблица счётчиков попыток по IP.
 *
 * @return string
 */
function sad_znaniy_security_table_attempts() {
	global $wpdb;
	return $wpdb->prefix . 'sz_login_attempts';
}

/**
 * Таблица журнала входов.
 *
 * @return string
 */
function sad_znaniy_security_table_log() {
	global $wpdb;
	return $wpdb->prefix . 'sz_login_log';
}

/**
 * Параметры защиты.
 *
 * @return array
 */
function sad_znaniy_security_settings() {
	return apply_filters(
		'sad_znaniy_security_settings',
		array(
			'max_attempts'    => 5,
			'window'          => 15,
			'lock'            => 15,
			'escalate_after'  => 3,
			'escalate_lock'   => 1440,
			'escalate_window' => 24,
			'permanent_after' => 6,
			'permanent_lock'  => 43200,
			'notify_interval' => 3600,
			'log_days'        => 60,
			'notify'          => true,
		)
	);
}

/**
 * Создаёт таблицы модуля (один раз и при смене версии схемы).
 *
 * @return void
 */
function sad_znaniy_security_install() {
	if ( sad_znaniy_security_schema_version() === get_option( 'sad_znaniy_security_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$tries   = sad_znaniy_security_table_attempts();
	$log     = sad_znaniy_security_table_log();

	dbDelta(
		"CREATE TABLE {$tries} (
			ip varchar(45) NOT NULL,
			attempts int NOT NULL DEFAULT 0,
			last_attempt datetime NULL DEFAULT NULL,
			lock_until datetime NULL DEFAULT NULL,
			locks int NOT NULL DEFAULT 0,
			locks_start datetime NULL DEFAULT NULL,
			permanent tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (ip),
			KEY lock_until (lock_until)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$log} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NULL DEFAULT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			login_name varchar(60) NOT NULL DEFAULT '',
			status varchar(10) NOT NULL DEFAULT '',
			agent varchar(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY ip (ip)
		) {$charset};"
	);

	update_option( 'sad_znaniy_security_db', sad_znaniy_security_schema_version(), false );
}
add_action( 'after_setup_theme', 'sad_znaniy_security_install' );

/**
 * IP посетителя. За прокси (Local/hosting) берём первый публичный адрес из
 * X-Forwarded-For, иначе REMOTE_ADDR: подделывать XFF снаружи бессмысленно,
 * потому что берём его только когда REMOTE_ADDR приватный.
 *
 * @return string
 */
function sad_znaniy_security_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

	$is_public = static function ( $ip ) {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	};

	if ( $is_public( $remote ) ) {
		return $remote;
	}

	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		foreach ( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) as $part ) {
			$candidate = trim( $part );
			if ( $is_public( $candidate ) ) {
				return $candidate;
			}
		}
	}

	return '' !== $remote ? $remote : '0.0.0.0';
}

/* === 1. XML-RPC ========================================================== */

/**
 * Отдаёт 403 на любой запрос к xmlrpc.php (веб-клиентов у сайта нет).
 *
 * @return void
 */
function sad_znaniy_security_block_xmlrpc() {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		exit( 'XML-RPC отключён на этом сайте.' );
	}
}
add_action( 'init', 'sad_znaniy_security_block_xmlrpc', 0 );
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Убирает заголовок X-Pingback и rel-pingback из ответов.
 *
 * @param array $headers Заголовки.
 * @return array
 */
function sad_znaniy_security_drop_pingback( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
}
add_filter( 'wp_headers', 'sad_znaniy_security_drop_pingback' );

/**
 * Пустой список методов XML-RPC — вторая линия защиты.
 *
 * @return array
 */
function sad_znaniy_security_no_xmlrpc_methods() {
	return array();
}
add_filter( 'xmlrpc_methods', 'sad_znaniy_security_no_xmlrpc_methods' );

/* === 2. REST: перечисление пользователей ================================= */

/**
 * Список и карточки пользователей в REST — только для тех, кто их видит
 * в админке (гость получает 401, подписчик — 403).
 *
 * @param array $endpoints Зарегистрированные маршруты.
 * @return array
 */
function sad_znaniy_security_rest_users( $endpoints ) {
	foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $route ) {
		if ( empty( $endpoints[ $route ] ) || ! is_array( $endpoints[ $route ] ) ) {
			continue;
		}
		foreach ( $endpoints[ $route ] as $index => $handler ) {
			if ( ! is_array( $handler ) ) {
				continue;
			}
			$endpoints[ $route ][ $index ]['permission_callback'] = static function () {
				return current_user_can( 'list_users' );
			};
		}
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'sad_znaniy_security_rest_users' );

/* === 3. Авторские архивы и ?author=N ===================================== */

/**
 * Гостю авторские архивы и ?author=N отдают 404: это второй канал
 * перечисления логинов. Работает раньше кэша (priority 5) и снимает
 * канонический редирект, чтобы не было 301 на /author/<login>/.
 *
 * @return void
 */
function sad_znaniy_security_block_author() {
	if ( is_user_logged_in() ) {
		return;
	}
	if ( ! is_author() ) {
		return;
	}
	remove_action( 'template_redirect', 'redirect_canonical' );
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'sad_znaniy_security_block_author', 5 );

/* === 4. Заголовки безопасности и CSP ===================================== */

/**
 * Базовые заголовки для всех ответов; CSP — только фронтенд
 * (в админке его намеренно нет, чтобы не сломать Gutenberg).
 *
 * @return void
 */
function sad_znaniy_security_headers() {
	// X-Content-Type-Options и Referrer-Policy ставит .htaccess (Header always set),
	// чтобы заголовки не дублировались в ответе — батч A′-2 (06.11.2026).
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()' );
	header( 'Cross-Origin-Opener-Policy: same-origin' );

	if ( ! is_admin() ) {
		header(
			"Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
			. "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
			. "font-src 'self' data:; connect-src 'self'; frame-src 'none'; "
			. "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
		);
	}
}
add_action( 'send_headers', 'sad_znaniy_security_headers' );

/**
 * Локальная аватарка вместо Gravatar: сайт не делает внешних запросов
 * (правило проекта) и не отдаёт хеш почты наружу.
 *
 * @param array $args        Аргументы аватара.
 * @param mixed $id_or_email Кто запрашивает.
 * @return array
 */
function sad_znaniy_security_local_avatar( $args, $id_or_email ) {
	$size = isset( $args['size'] ) ? max( 16, (int) $args['size'] ) : 48;
	$svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '">'
		. '<rect width="100%" height="100%" rx="50%" fill="#68B96E" fill-opacity="0.12"/>'
		. '<text x="50%" y="62%" font-family="Arial, sans-serif" font-size="' . (int) round( $size * 0.5 ) . '"'
		. ' fill="#68B96E" text-anchor="middle">С</text></svg>';

	$args['url']          = 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	$args['found_avatar'] = true;
	unset( $id_or_email );
	return $args;
}
add_filter( 'get_avatar_data', 'sad_znaniy_security_local_avatar', 10, 2 );

/* === 5. Лимитер попыток входа ============================================ */

/**
 * Строка счётчика для IP.
 *
 * @param string $ip IP.
 * @return array|null
 */
function sad_znaniy_security_get_row( $ip ) {
	global $wpdb;
	$table = sad_znaniy_security_table_attempts();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT attempts, last_attempt, lock_until, locks, locks_start, permanent FROM {$table} WHERE ip = %s", $ip ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Время блокировки в Unix-времени (0 — не заблокирован).
 *
 * @param array|null $row Строка счётчика.
 * @return int
 */
function sad_znaniy_security_lock_time( $row ) {
	if ( ! is_array( $row ) || empty( $row['lock_until'] ) ) {
		return 0;
	}
	$time = strtotime( (string) $row['lock_until'] . ' +00:00' );
	return $time ? $time : 0;
}

/**
 * Запись в журнал входов (с редкой ротацией старых строк).
 *
 * @param string $ip     IP.
 * @param string $login  Логин из формы.
 * @param string $status fail | locked | success.
 * @return void
 */
function sad_znaniy_security_log( $ip, $login, $status ) {
	global $wpdb;
	$settings = sad_znaniy_security_settings();
	$agent    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 190 ) : '';
	$table    = sad_znaniy_security_table_log();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$table,
		array(
			'created_at' => current_time( 'mysql', true ),
			'ip'         => $ip,
			'login_name' => substr( (string) $login, 0, 60 ),
			'status'     => substr( (string) $status, 0, 10 ),
			'agent'      => $agent,
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);

	if ( 1 === wp_rand( 1, 25 ) ) {
		$limit = gmdate( 'Y-m-d H:i:s', time() - ( (int) $settings['log_days'] * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $limit ) );
	}
}

/* === 5a. Постоянный список IP (logs/, вне webroot) ======================= */

/**
 * Файл постоянного списка. Лежит в logs/ вне webroot; если папки нет —
 * рядом с базовой папкой сайта (тоже вне webroot).
 *
 * @return string
 */
function sad_znaniy_security_permanent_file() {
	$dir = sad_znaniy_security_base_dir() . '/logs';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( is_dir( $dir ) && is_writable( $dir ) ) {
		return $dir . '/permanent-ips.json';
	}
	return sad_znaniy_security_base_dir() . '/permanent-ips.json';
}

/**
 * Постоянный список: ip => запись (since, locks, attempts, logins, agent).
 *
 * @return array
 */
function sad_znaniy_security_permanent_all() {
	$file = sad_znaniy_security_permanent_file();
	if ( ! is_file( $file ) ) {
		return array();
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$raw  = file_get_contents( $file );
	$data = json_decode( (string) $raw, true );
	return is_array( $data ) ? $data : array();
}

/**
 * Сохраняет постоянный список (через временный файл, права 600).
 *
 * @param array $data Список.
 * @return bool
 */
function sad_znaniy_security_permanent_save( $data ) {
	$file = sad_znaniy_security_permanent_file();
	$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $json ) ) {
		return false;
	}
	$tmp = $file . '.tmp';
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$written = file_put_contents( $tmp, $json, LOCK_EX );
	if ( false === $written ) {
		return false;
	}
	@chmod( $tmp, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	return (bool) rename( $tmp, $file );
}

/**
 * Есть ли IP в постоянном списке.
 *
 * @param string $ip IP.
 * @return bool
 */
function sad_znaniy_security_permanent_has( $ip ) {
	$all = sad_znaniy_security_permanent_all();
	return isset( $all[ $ip ] );
}

/**
 * Добавляет IP в постоянный список.
 *
 * @param string $ip      IP.
 * @param array  $context locks, attempts, logins, agent.
 * @return void
 */
function sad_znaniy_security_permanent_add( $ip, $context = array() ) {
	$all = sad_znaniy_security_permanent_all();

	$logins = array();
	if ( ! empty( $context['logins'] ) && is_array( $context['logins'] ) ) {
		$logins = array_slice( array_values( array_unique( array_map( 'strval', $context['logins'] ) ) ), 0, 10 );
	}

	$all[ $ip ] = array(
		'since'    => current_time( 'mysql', true ),
		'locks'    => isset( $context['locks'] ) ? (int) $context['locks'] : 0,
		'attempts' => isset( $context['attempts'] ) ? (int) $context['attempts'] : 0,
		'logins'   => $logins,
		'agent'    => isset( $context['agent'] ) ? substr( (string) $context['agent'], 0, 190 ) : '',
	);

	sad_znaniy_security_permanent_save( $all );
}

/**
 * Убирает IP из постоянного списка.
 *
 * @param string $ip IP.
 * @return void
 */
function sad_znaniy_security_permanent_remove( $ip ) {
	$all = sad_znaniy_security_permanent_all();
	if ( ! isset( $all[ $ip ] ) ) {
		return;
	}
	unset( $all[ $ip ] );
	sad_znaniy_security_permanent_save( $all );
}

/* === 5b. Агрегат попыток и письма ======================================== */

/**
 * Сводка по IP из журнала: сколько неудач, какие логины, последний user-agent.
 *
 * @param string $ip        IP.
 * @param string $since_gmt Начало периода (GMT, mysql).
 * @return array
 */
function sad_znaniy_security_ip_stats( $ip, $since_gmt ) {
	global $wpdb;
	$table = sad_znaniy_security_table_log();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT login_name, status, agent FROM {$table} WHERE ip = %s AND created_at >= %s ORDER BY id ASC", $ip, $since_gmt ), ARRAY_A );

	$attempts = 0;
	$logins   = array();
	$agent    = '';

	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			if ( 'success' === (string) $row['status'] ) {
				continue;
			}
			$attempts++;
			$login = (string) $row['login_name'];
			if ( '' !== $login && ! in_array( $login, $logins, true ) ) {
				$logins[] = $login;
			}
			if ( '' !== (string) $row['agent'] ) {
				$agent = (string) $row['agent'];
			}
		}
	}

	return array(
		'attempts' => $attempts,
		'logins'   => array_slice( $logins, 0, 10 ),
		'agent'    => $agent,
	);
}

/**
 * Когда по этому IP последний раз уходило письмо (Unix-время, 0 — не было).
 *
 * @param string $ip IP.
 * @return int
 */
function sad_znaniy_security_notify_last( $ip ) {
	$state = get_option( 'sad_znaniy_security_notify_state', array() );
	$state = is_array( $state ) ? $state : array();
	return isset( $state[ $ip ] ) ? (int) $state[ $ip ] : 0;
}

/**
 * Запоминает время письма (и чистит записи старше 7 дней).
 *
 * @param string $ip IP.
 * @param int    $ts Время.
 * @return void
 */
function sad_znaniy_security_notify_mark( $ip, $ts ) {
	$state = get_option( 'sad_znaniy_security_notify_state', array() );
	$state = is_array( $state ) ? $state : array();

	$state[ $ip ] = (int) $ts;
	$cut          = time() - 7 * DAY_IN_SECONDS;
	foreach ( $state as $key => $value ) {
		if ( (int) $value < $cut ) {
			unset( $state[ $key ] );
		}
	}

	update_option( 'sad_znaniy_security_notify_state', $state, false );
}

/**
 * Тело письма о блокировке.
 *
 * @param string $ip        IP.
 * @param int    $duration  Длительность блока в минутах.
 * @param int    $locks     Номер блокировки в серии.
 * @param array  $stats     Сводка попыток.
 * @param bool   $permanent Попал в постоянный список.
 * @return string
 */
function sad_znaniy_security_notify_body( $ip, $duration, $locks, $stats, $permanent ) {
	$minutes = (int) $duration;
	$human   = $minutes >= 1440 ? sprintf( '%d сут.', (int) round( $minutes / 1440 ) ) : sprintf( '%d мин.', $minutes );

	$lines   = array();
	$lines[] = 'IP: ' . $ip;
	$lines[] = sprintf( 'Блокировка: %s (по счёту %d в текущей серии)', $human, (int) $locks );
	$lines[] = sprintf( 'Неудачных попыток за период: %d', (int) $stats['attempts'] );
	$lines[] = 'Логины: ' . ( $stats['logins'] ? implode( ', ', $stats['logins'] ) : 'не определено' );
	$lines[] = 'User-Agent: ' . ( '' !== $stats['agent'] ? $stats['agent'] : 'не определён' );

	if ( $permanent ) {
		$lines[] = '';
		$lines[] = 'IP добавлен в постоянный список (logs/): повторные блокировки → 30 дней.';
		$lines[] = 'Файл списка: ' . sad_znaniy_security_permanent_file();
	}

	$lines[] = '';
	$lines[] = 'Управление: ' . admin_url( 'tools.php?page=sz-security' );

	return implode( "\n", $lines );
}

/**
 * Письмо о блокировке: не чаще одного в час на IP (остальное агрегируется).
 *
 * @param string $ip       IP.
 * @param int    $duration Минуты блокировки.
 * @param int    $locks    Номер блокировки.
 * @return bool
 */
function sad_znaniy_security_notify_block( $ip, $duration, $locks ) {
	$settings = sad_znaniy_security_settings();
	if ( empty( $settings['notify'] ) ) {
		return false;
	}

	$interval = max( 60, (int) $settings['notify_interval'] );
	$last     = sad_znaniy_security_notify_last( $ip );

	if ( $last && ( time() - $last ) < $interval ) {
		return false; // в этот час письмо уже было — копим статистику молча
	}

	$since = gmdate( 'Y-m-d H:i:s', $last ? $last : ( time() - $interval ) );
	$stats = sad_znaniy_security_ip_stats( $ip, $since );
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	$sent = wp_mail(
		get_option( 'admin_email' ),
		sprintf( '[%s] Блокировка входа: %s', $site, $ip ),
		sad_znaniy_security_notify_body( $ip, $duration, $locks, $stats, false )
	);

	if ( $sent ) {
		sad_znaniy_security_notify_mark( $ip, time() );
	}

	return (bool) $sent;
}

/**
 * Отдельное письмо: IP ушёл в постоянный список (всегда, без троттлинга).
 *
 * @param string $ip    IP.
 * @param int    $locks Номер блокировки.
 * @return bool
 */
function sad_znaniy_security_notify_permanent( $ip, $locks ) {
	$settings = sad_znaniy_security_settings();
	if ( empty( $settings['notify'] ) ) {
		return false;
	}

	$stats = sad_znaniy_security_ip_stats( $ip, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	$sent = wp_mail(
		get_option( 'admin_email' ),
		sprintf( '[%s] IP в постоянном списке: %s', $site, $ip ),
		sad_znaniy_security_notify_body( $ip, (int) $settings['permanent_lock'], $locks, $stats, true )
	);

	if ( $sent ) {
		sad_znaniy_security_notify_mark( $ip, time() );
	}

	return (bool) $sent;
}

/**
 * Человеческий текст блокировки для формы входа.
 *
 * @param int  $left_seconds Осталось секунд.
 * @param bool $permanent    IP в постоянном списке.
 * @return string
 */
function sad_znaniy_security_lock_message( $left_seconds, $permanent ) {
	if ( $permanent ) {
		return 'Этот IP в постоянном списке блокировки (30 дней) из-за повторных попыток подбора пароля. Напишите администратору сайта.';
	}
	if ( $left_seconds > DAY_IN_SECONDS ) {
		return sprintf(
			'Слишком много неудачных попыток входа. Доступ заблокирован на %d сут.',
			(int) ceil( $left_seconds / DAY_IN_SECONDS )
		);
	}
	return sprintf(
		'Слишком много неудачных попыток входа. Следующая попытка возможна через %d мин.',
		max( 1, (int) ceil( $left_seconds / 60 ) )
	);
}

/* === 5c. Учёт неудачи и эскалация ======================================== */

/**
 * Считает неудачную попытку и по порогу ставит блокировку нужной длины.
 * Используется и формой входа, и сбросом пароля.
 *
 * @param string $ip    IP.
 * @param string $login Логин из формы.
 * @return void
 */
function sad_znaniy_security_register_failure( $ip, $login ) {
	global $wpdb;
	$settings = sad_znaniy_security_settings();
	$table    = sad_znaniy_security_table_attempts();
	$row      = sad_znaniy_security_get_row( $ip );
	$now      = current_time( 'mysql', true );
	$status   = 'fail';

	// Внутри активной блокировки не считаем: иначе эскалация прыгает на каждом
	// запросе бота, а счётчик «5 неудач» теряет смысл.
	if ( $row && sad_znaniy_security_lock_time( $row ) > time() ) {
		return;
	}

	$attempts = 1;
	$locks    = 0;
	$start    = 0;

	if ( $row ) {
		$last     = ! empty( $row['last_attempt'] ) ? strtotime( (string) $row['last_attempt'] . ' +00:00' ) : 0;
		$expired  = ! $last || ( time() - $last ) > ( (int) $settings['window'] * 60 );
		$attempts = $expired ? 1 : (int) $row['attempts'] + 1;
		$locks    = isset( $row['locks'] ) ? (int) $row['locks'] : 0;
		$start    = ! empty( $row['locks_start'] ) ? strtotime( (string) $row['locks_start'] . ' +00:00' ) : 0;
	}

	// Серия блокировок живёт 24 часа: окно истекло — считаем заново.
	if ( ! $start || ( time() - $start ) > ( (int) $settings['escalate_window'] * HOUR_IN_SECONDS ) ) {
		$locks = 0;
		$start = time();
	}

	$update = array(
		'attempts'     => $attempts,
		'last_attempt' => $now,
		'lock_until'   => ( $row && ! empty( $row['lock_until'] ) ) ? $row['lock_until'] : null,
		'locks'        => $locks,
		'locks_start'  => gmdate( 'Y-m-d H:i:s', $start ),
		'permanent'    => 0,
	);

	$lock_min  = 0;
	$permanent = false;

	if ( $attempts >= (int) $settings['max_attempts'] ) {
		$locks++;

		if ( $locks >= (int) $settings['permanent_after'] ) {
			$lock_min  = (int) $settings['permanent_lock'];
			$permanent = true;
		} elseif ( $locks >= (int) $settings['escalate_after'] ) {
			$lock_min = (int) $settings['escalate_lock'];
		} else {
			$lock_min = (int) $settings['lock'];
		}

		// Счётчик неудач сбрасываем: следующий блок — после новых 5 неудач.
		$update['attempts']   = 0;
		$update['locks']      = $locks;
		$update['permanent']  = $permanent ? 1 : 0;
		$update['lock_until'] = gmdate( 'Y-m-d H:i:s', time() + $lock_min * 60 );
		$status               = 'locked';
	}

	if ( $row ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, $update, array( 'ip' => $ip ) );
	} else {
		$update['ip'] = $ip;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, $update, array( '%d', '%s', '%s', '%d', '%s', '%d', '%s' ) );
	}

	if ( 'locked' === $status ) {
		if ( $permanent ) {
			$stats = sad_znaniy_security_ip_stats( $ip, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
			sad_znaniy_security_permanent_add(
				$ip,
				array(
					'locks'    => $locks,
					'attempts' => (int) $stats['attempts'],
					'logins'   => $stats['logins'],
					'agent'    => $stats['agent'],
				)
			);
			sad_znaniy_security_notify_permanent( $ip, $locks );
		} else {
			sad_znaniy_security_notify_block( $ip, $lock_min, $locks );
		}
	}

	sad_znaniy_security_log( $ip, $login, $status );
}

/**
 * Блокируем вход с IP, который исчерпал попытки (до проверки пароля).
 *
 * @param null|WP_User|WP_Error $user     Результат предыдущих фильтров.
 * @param string                $username Логин.
 * @param string                $password Пароль.
 * @return null|WP_User|WP_Error
 */
function sad_znaniy_security_check_lock( $user, $username, $password ) {
	if ( '' === (string) $username && '' === (string) $password ) {
		return $user;
	}

	$ip      = sad_znaniy_security_client_ip();
	$until   = sad_znaniy_security_lock_time( sad_znaniy_security_get_row( $ip ) );
	$left    = $until - time();
	$perm    = sad_znaniy_security_permanent_has( $ip );

	if ( $left > 0 || $perm ) {
		sad_znaniy_security_log( $ip, $username, 'locked' );
		return new WP_Error( 'sz_login_locked', sad_znaniy_security_lock_message( $left, $perm ) );
	}
	return $user;
}
// Приоритет 999: наш фильтр должен идти ПОСЛЕ wp_authenticate_username_password,
// иначе ядро перезапишет нашу ошибку (и пустит с верным паролем при блокировке).
add_filter( 'authenticate', 'sad_znaniy_security_check_lock', 999, 3 );

/**
 * Считаем неудачную попытку; по порогам — блокировка нужной длины и письмо.
 *
 * @param string        $username Логин из формы.
 * @param WP_Error|null $error    Ошибка входа.
 * @return void
 */
function sad_znaniy_security_login_failed( $username, $error = null ) {
	sad_znaniy_security_register_failure( sad_znaniy_security_client_ip(), (string) $username );
	unset( $error );
}
add_action( 'wp_login_failed', 'sad_znaniy_security_login_failed', 10, 2 );

/**
 * Сброс пароля — второй канал подбора: проверяем блокировку до отправки письма
 * и считаем попытку, если такого пользователя/почты нет (перебор логинов).
 *
 * @param WP_Error       $errors    Ошибки формы.
 * @param WP_User|false  $user_data Найденный пользователь.
 * @return void
 */
function sad_znaniy_security_lostpassword( $errors, $user_data = null ) {
	if ( ! $errors instanceof WP_Error ) {
		return;
	}

	$ip   = sad_znaniy_security_client_ip();
	$left = sad_znaniy_security_lock_time( sad_znaniy_security_get_row( $ip ) ) - time();
	$perm = sad_znaniy_security_permanent_has( $ip );

	if ( $left > 0 || $perm ) {
		sad_znaniy_security_log( $ip, '[lostpassword]', 'locked' );
		$errors->add( 'sz_login_locked', sad_znaniy_security_lock_message( $left, $perm ) );
		return;
	}

	if ( empty( $user_data ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['user_login'] ) ) : '';
		sad_znaniy_security_register_failure( $ip, '[lostpassword] ' . $login );
	}
}
add_action( 'lostpassword_post', 'sad_znaniy_security_lostpassword', 10, 2 );

/**
 * POST с паролем к закрытой записи (postpass) — третья форма, где перебирают
 * пароли. Счётчик по ней ядро не отдаёт, но действующую блокировку уважаем.
 *
 * @return void
 */
function sad_znaniy_security_postpass_guard() {
	if ( empty( $_POST['post_password'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	$ip   = sad_znaniy_security_client_ip();
	$left = sad_znaniy_security_lock_time( sad_znaniy_security_get_row( $ip ) ) - time();
	$perm = sad_znaniy_security_permanent_has( $ip );

	if ( $left > 0 || $perm ) {
		sad_znaniy_security_log( $ip, '[postpass]', 'locked' );
		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		exit( esc_html( sad_znaniy_security_lock_message( $left, $perm ) ) );
	}
}
add_action( 'init', 'sad_znaniy_security_postpass_guard', 1 );

/**
 * Успешный вход: снимаем счётчик и пишем в журнал.
 *
 * @param string       $login Логин.
 * @param WP_User|null $user  Пользователь.
 * @return void
 */
function sad_znaniy_security_login_ok( $login, $user = null ) {
	global $wpdb;
	$ip = sad_znaniy_security_client_ip();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( sad_znaniy_security_table_attempts(), array( 'ip' => $ip ), array( '%s' ) );
	sad_znaniy_security_log( $ip, $login, 'success' );
	unset( $user );
}
add_action( 'wp_login', 'sad_znaniy_security_login_ok', 10, 2 );

/* === 6. Экран «🛡 Безопасность» ========================================== */

/**
 * Пункт меню «Инструменты → 🛡 Безопасность».
 *
 * @return void
 */
function sad_znaniy_security_menu() {
	add_management_page( 'Безопасность', '🛡 Безопасность', 'manage_options', 'sz-security', 'sad_znaniy_security_page' );
}
add_action( 'admin_menu', 'sad_znaniy_security_menu' );

/**
 * Обрабатывает действия экрана (разблокировка IP, очистка журнала).
 *
 * @return string Сообщение для админки.
 */
function sad_znaniy_security_handle_actions() {
	if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
		return '';
	}
	if ( ! isset( $_POST['sz_security_action'] ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	check_admin_referer( 'sz_security_action' );

	global $wpdb;
	$action  = sanitize_key( wp_unslash( (string) $_POST['sz_security_action'] ) );
	$ip      = isset( $_POST['sz_security_ip'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sz_security_ip'] ) ) : '';
	$tries   = sad_znaniy_security_table_attempts();
	$log     = sad_znaniy_security_table_log();
	$message = '';

	if ( 'unlock' === $action && '' !== $ip ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $tries, array( 'ip' => $ip ), array( '%s' ) );
		sad_znaniy_security_permanent_remove( $ip );
		$message = 'IP разблокирован и убран из постоянного списка: ' . $ip;
	} elseif ( 'perm_delete' === $action && '' !== $ip ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $tries, array( 'ip' => $ip ), array( '%s' ) );
		sad_znaniy_security_permanent_remove( $ip );
		$message = 'IP удалён из постоянного списка: ' . $ip;
	} elseif ( 'unlock_all' === $action ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$tries}" );
		$message = 'Все счётчики попыток сброшены.';
	} elseif ( 'clear_log' === $action ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$log}" );
		$message = 'Журнал входов очищен.';
	} elseif ( 'backup_now' === $action && function_exists( 'sad_znaniy_backup_run' ) ) {
		$message = 'Бэкап: ' . sad_znaniy_backup_run( true );
	} elseif ( 'integrity_now' === $action && function_exists( 'sad_znaniy_integrity_run' ) ) {
		sad_znaniy_integrity_run( true );
		$message = 'Целостность: ' . sad_znaniy_integrity_summary();
	} elseif ( 'integrity_baseline' === $action && function_exists( 'sad_znaniy_integrity_theme_baseline' ) ) {
		$message = 'Базовая линия темы обновлена: ' . sad_znaniy_integrity_theme_baseline() . ' файлов.';
	} elseif ( 'mail_test' === $action && function_exists( 'sad_znaniy_mail_test' ) ) {
		$message = 'Почта: ' . sad_znaniy_mail_test();
	}

	return $message;
}

/**
 * Строка состояния защиты.
 *
 * @param string $name   Что проверяем.
 * @param bool   $ok     Состояние.
 * @param string $detail Подробность.
 * @return string
 */
function sad_znaniy_security_status_line( $name, $ok, $detail ) {
	return '<tr><td><strong>' . esc_html( $name ) . '</strong></td>'
		. '<td>' . ( $ok ? '<span style="color:#68B96E">✔ включено</span>' : '<span style="color:#b32d2e">✗ выключено</span>' ) . '</td>'
		. '<td>' . esc_html( $detail ) . '</td></tr>';
}

/**
 * Экран «Инструменты → 🛡 Безопасность».
 *
 * @return void
 */
function sad_znaniy_security_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$message  = sad_znaniy_security_handle_actions();
	$settings = sad_znaniy_security_settings();
	$log_path = (string) ini_get( 'error_log' );
	$uploads  = wp_upload_dir();
	global $wpdb;

	$log_table   = sad_znaniy_security_table_log();
	$tries_table = sad_znaniy_security_table_attempts();
	$locked      = $wpdb->get_results( "SELECT * FROM {$tries_table} ORDER BY lock_until DESC, last_attempt DESC LIMIT 50" ); // phpcs:ignore WordPress.DB
	$entries     = $wpdb->get_results( "SELECT * FROM {$log_table} ORDER BY id DESC LIMIT 25" ); // phpcs:ignore WordPress.DB

	echo '<div class="wrap">';
	echo '<h1>🛡 Безопасность</h1>';
	echo '<p>Этап 7.3: защита сделана нативно, без плагинов. Здесь — состояние защиты, журнал попыток входа и разблокировка IP. Файл аудита: <code>_docs/security-audit.md</code> (в репозитории, вне веба).</p>';

	if ( $message ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	echo '<h2>Состояние защиты</h2>';
	echo '<table class="widefat striped"><thead><tr><th>Что</th><th>Состояние</th><th>Как устроено</th></tr></thead><tbody>';
	echo sad_znaniy_security_status_line( 'XML-RPC', has_filter( 'xmlrpc_methods', 'sad_znaniy_security_no_xmlrpc_methods' ), 'Любой запрос к /xmlrpc.php → 403, pingback-заголовок убран' );
	echo sad_znaniy_security_status_line( 'REST: список пользователей', has_filter( 'rest_endpoints', 'sad_znaniy_security_rest_users' ), '/wp-json/wp/v2/users и /users/<id> — только для тех, кто видит пользователей в админке' );
	echo sad_znaniy_security_status_line( 'Перечисление логинов', has_action( 'template_redirect', 'sad_znaniy_security_block_author' ), 'Авторские архивы и ?author=N для гостей → 404' );
	echo sad_znaniy_security_status_line( 'Редактор файлов в админке', defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT, 'DISALLOW_FILE_EDIT = true в wp-config.php' );
	echo sad_znaniy_security_status_line( 'Установка плагинов/тем из админки', defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS, 'DISALLOW_FILE_MODS — включается на боевом сервере (на локалке обновляем ядро)' );
	echo sad_znaniy_security_status_line( 'Принудительный HTTPS в админке', defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN, 'FORCE_SSL_ADMIN — включается автоматически, когда WP_ENVIRONMENT_TYPE не local' );
	echo sad_znaniy_security_status_line( 'Заголовки безопасности', has_action( 'send_headers', 'sad_znaniy_security_headers' ), 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy + CSP на фронтенде' );
	echo sad_znaniy_security_status_line( 'Локальная аватарка', has_filter( 'get_avatar_data', 'sad_znaniy_security_local_avatar' ), 'Gravatar отключён: сайт не делает внешних запросов' );
	echo sad_znaniy_security_status_line( 'Лимитер входов', true, sprintf( '%d неудач → блок %d мин (окно %d мин), журнал %d дней; покрыты вход, сброс пароля и пароль к закрытым записям', (int) $settings['max_attempts'], (int) $settings['lock'], (int) $settings['window'], (int) $settings['log_days'] ) );
	echo sad_znaniy_security_status_line(
		'Эскалация блокировок',
		true,
		sprintf(
			'%d-й блок за %d ч → %d мин; с %d-го блока → %d сут. и постоянный список (сейчас в нём %d IP)',
			(int) $settings['escalate_after'],
			(int) $settings['escalate_window'],
			(int) $settings['escalate_lock'],
			(int) $settings['permanent_after'],
			(int) round( (int) $settings['permanent_lock'] / 1440 ),
			count( sad_znaniy_security_permanent_all() )
		)
	);
	echo sad_znaniy_security_status_line(
		'Письма о блокировках',
		! empty( $settings['notify'] ),
		sprintf( 'не чаще 1 письма в %d мин на IP, с агрегатом попыток; попадание в постоянный список — отдельным письмом', (int) round( (int) $settings['notify_interval'] / 60 ) )
	);
	echo sad_znaniy_security_status_line( 'Лог ошибок вне webroot', '' !== $log_path && false === strpos( $log_path, 'wp-content' ), $log_path ? $log_path : 'не задан' );
	echo sad_znaniy_security_status_line( 'Загрузки (uploads)', is_dir( $uploads['basedir'] ), $uploads['basedir'] );
	echo '</tbody></table>';

	sad_znaniy_security_page_tables();
	echo '</div>';
}

/**
 * Базовая папка вне webroot: сначала уровень проекта, затем уровень выше.
 * Локально это C:\...\sadznaniy, на боевом — каталог аккаунта/сайта.
 *
 * @return string
 */
function sad_znaniy_security_base_dir() {
	// Боевой сервер: каталог сайта на хостинге лежит на два уровня ниже домашнего
	// (домен → public_html), поэтому базу для бэкапов задаём константой в
	// wp-config.php — иначе выбралась бы общая папка аккаунта с чужими бэкапами.
	if ( defined( 'SZ_BACKUP_BASE' ) && is_dir( SZ_BACKUP_BASE ) && is_writable( SZ_BACKUP_BASE ) ) {
		return rtrim( SZ_BACKUP_BASE, '/\\' );
	}

	$webroot = rtrim( ABSPATH, '/\\' );
	$one_up  = dirname( $webroot );
	$two_up  = dirname( $one_up );

	foreach ( array( $two_up, $one_up ) as $candidate ) {
		if ( is_dir( $candidate ) && is_writable( $candidate ) ) {
			return $candidate;
		}
	}
	return $one_up;
}

/**
 * Папка бэкапов — вне webroot (для локалки и для боевого сервера).
 *
 * @return string
 */
function sad_znaniy_security_backup_dir() {
	return sad_znaniy_security_base_dir() . '/backups';
}

/**
 * Самый свежий бэкап (имя файла) или пустая строка.
 *
 * @return string
 */
function sad_znaniy_security_last_backup() {
	$dir = sad_znaniy_security_backup_dir();
	if ( ! is_dir( $dir ) ) {
		return '';
	}
	$files = glob( $dir . '/*.{sql,zip}', GLOB_BRACE );
	if ( ! $files ) {
		return '';
	}
	usort(
		$files,
		static function ( $a, $b ) {
			return filemtime( $b ) <=> filemtime( $a );
		}
	);
	$newest = (string) $files[0];
	return basename( $newest ) . ' — ' . round( filesize( $newest ) / 1048576, 2 ) . ' МБ, ' . wp_date( 'd.m.Y H:i', filemtime( $newest ) );
}

/**
 * Время из GMT в часовой пояс сайта.
 *
 * @param string|null $gmt Время в GMT.
 * @return string
 */
function sad_znaniy_security_local_time( $gmt ) {
	if ( empty( $gmt ) ) {
		return '';
	}
	$ts = strtotime( (string) $gmt . ' +00:00' );
	return $ts ? wp_date( 'd.m.Y H:i', $ts ) : (string) $gmt;
}

/**
 * Вторая половина экрана: бэкапы, целостность, блокировки и журнал.
 *
 * @return void
 */
function sad_znaniy_security_page_tables() {
	global $wpdb;
	$log_table   = sad_znaniy_security_table_log();
	$tries_table = sad_znaniy_security_table_attempts();
	$locked      = $wpdb->get_results( "SELECT * FROM {$tries_table} ORDER BY last_attempt DESC LIMIT 50" ); // phpcs:ignore WordPress.DB
	$entries     = $wpdb->get_results( "SELECT * FROM {$log_table} ORDER BY id DESC LIMIT 25" ); // phpcs:ignore WordPress.DB

	echo '<h2>Резервные копии и целостность</h2>';
	echo '<table class="widefat striped"><tbody>';
	$last = sad_znaniy_security_last_backup();
	$integrity = function_exists( 'sad_znaniy_integrity_summary' ) ? sad_znaniy_integrity_summary() : '';
	echo '<tr><td><strong>Последний бэкап</strong></td><td>' . ( $last ? esc_html( $last ) : '<span style="color:#b32d2e">пока нет (нажмите «Сделать бэкап сейчас»)</span>' ) . '</td><td><code>' . esc_html( sad_znaniy_security_backup_dir() ) . '</code></td></tr>';
	echo '<tr><td><strong>Целостность файлов</strong></td><td>' . ( '' !== $integrity ? esc_html( $integrity ) : '<span style="color:#b32d2e">проверок пока нет</span>' ) . '</td><td>ежедневная сверка контрольных сумм ядра и файлов темы (в 04:30)</td></tr>';
	echo '</tbody></table>';

	if ( function_exists( 'sad_znaniy_backup_panel' ) ) {
		sad_znaniy_backup_panel();
	}

	if ( function_exists( 'sad_znaniy_integrity_panel' ) ) {
		sad_znaniy_integrity_panel();
	}

	if ( function_exists( 'sad_znaniy_mail_panel' ) ) {
		sad_znaniy_mail_panel();
	}

	echo '<h2>Заблокированные IP</h2>';
	if ( empty( $locked ) ) {
		echo '<p>Записей нет — неудачных попыток входа не было.</p>';
	} else {
		echo '<form method="post"><table class="widefat striped"><thead><tr><th>IP</th><th>Попыток</th><th>Последняя</th><th>Блокировка до</th><th>Статус</th><th></th></tr></thead><tbody>';
		wp_nonce_field( 'sz_security_action' );
		foreach ( $locked as $row ) {
			$until  = sad_znaniy_security_lock_time( (array) $row );
			$active = $until > time();
			echo '<tr><td><code>' . esc_html( $row->ip ) . '</code></td><td>' . (int) $row->attempts . '</td><td>'
				. esc_html( sad_znaniy_security_local_time( $row->last_attempt ) ) . '</td><td>'
				. ( $active ? esc_html( sad_znaniy_security_local_time( $row->lock_until ) ) : '—' ) . '</td><td>'
				. ( $active ? '🔒 заблокирован' : 'наблюдение' ) . '</td><td>';
			if ( $active ) {
				echo '<input type="hidden" name="sz_security_action" value="unlock"><input type="hidden" name="sz_security_ip" value="' . esc_attr( $row->ip ) . '">'
					. '<button class="button button-small">Разблокировать</button>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></form>';
	}

	echo '<form method="post" style="margin:10px 0 24px;">';
	wp_nonce_field( 'sz_security_action' );
	echo '<input type="hidden" name="sz_security_action" value="unlock_all">'
		. '<button class="button">Сбросить все счётчики</button>'
		. '</form>';

	$perm_list = sad_znaniy_security_permanent_all();
	echo '<h2>Постоянный список (30 дней)</h2>';
	if ( empty( $perm_list ) ) {
		echo '<p>Список пуст — IP с шестью блокировками за сутки пока не было.</p>';
	} else {
		echo '<form method="post"><table class="widefat striped"><thead><tr><th>IP</th><th>Когда попал</th><th>Блокировок</th><th>Попыток за сутки</th><th>Логины</th><th>Устройство</th><th></th></tr></thead><tbody>';
		wp_nonce_field( 'sz_security_action' );
		foreach ( $perm_list as $perm_ip => $perm_row ) {
			$perm_row    = is_array( $perm_row ) ? $perm_row : array();
			$perm_logins = ! empty( $perm_row['logins'] ) && is_array( $perm_row['logins'] ) ? implode( ', ', $perm_row['logins'] ) : '—';
			echo '<tr><td><code>' . esc_html( (string) $perm_ip ) . '</code></td><td>'
				. esc_html( sad_znaniy_security_local_time( isset( $perm_row['since'] ) ? (string) $perm_row['since'] : '' ) ) . '</td><td>'
				. (int) ( isset( $perm_row['locks'] ) ? $perm_row['locks'] : 0 ) . '</td><td>'
				. (int) ( isset( $perm_row['attempts'] ) ? $perm_row['attempts'] : 0 ) . '</td><td>'
				. esc_html( $perm_logins ) . '</td><td>'
				. esc_html( mb_substr( (string) ( isset( $perm_row['agent'] ) ? $perm_row['agent'] : '' ), 0, 40 ) ) . '</td><td>'
				. '<input type="hidden" name="sz_security_action" value="perm_delete"><input type="hidden" name="sz_security_ip" value="' . esc_attr( (string) $perm_ip ) . '">'
				. '<button class="button button-small">Удалить из списка</button></td></tr>';
		}
		echo '</tbody></table></form>';
	}
	echo '<p class="description">Файл списка (вне webroot): <code>' . esc_html( sad_znaniy_security_permanent_file() ) . '</code>. Удаление из списка сразу снимает и блокировку в таблице попыток.</p>';

	echo '<h2>Журнал входов (последние 25)</h2>';
	if ( empty( $entries ) ) {
		echo '<p>Журнал пуст.</p>';
	} else {
		$labels = array(
			'fail'    => '❌ неверный пароль',
			'locked'  => '🔒 попытка при блокировке',
			'success' => '✔ вход выполнен',
		);
		echo '<table class="widefat striped"><thead><tr><th>Когда</th><th>IP</th><th>Логин</th><th>Результат</th><th>Устройство</th></tr></thead><tbody>';
		foreach ( $entries as $e ) {
			$label = isset( $labels[ $e->status ] ) ? $labels[ $e->status ] : esc_html( $e->status );
			echo '<tr><td>' . esc_html( sad_znaniy_security_local_time( $e->created_at ) ) . '</td><td><code>' . esc_html( $e->ip ) . '</code></td><td>'
				. esc_html( $e->login_name ) . '</td><td>' . esc_html( $label ) . '</td><td>' . esc_html( mb_substr( (string) $e->agent, 0, 60 ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<form method="post" style="margin:10px 0 24px;">';
	wp_nonce_field( 'sz_security_action' );
	echo '<input type="hidden" name="sz_security_action" value="clear_log"><button class="button">Очистить журнал</button></form>';

	echo '<h2>Проверить живьём</h2>';
	echo '<p><a class="button" href="' . esc_url( site_url( '/xmlrpc.php' ) ) . '" target="_blank" rel="noopener">/xmlrpc.php</a> '
		. '<a class="button" href="' . esc_url( rest_url( 'wp/v2/users' ) ) . '" target="_blank" rel="noopener">/wp-json/wp/v2/users</a> '
		. '<a class="button" href="' . esc_url( home_url( '/?author=1' ) ) . '" target="_blank" rel="noopener">/?author=1</a></p>';
	echo '<p class="description">Ожидаемый результат: 403, 401 и 404 соответственно. В DevTools → Network на любой странице фронтенда должны быть заголовки X-Frame-Options, Referrer-Policy и Content-Security-Policy.</p>';
}
