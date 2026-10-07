<?php
/**
 * Этап 8.5 — Автопостинг: отправка в сети и воркер очереди (WP-Cron).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Внешние запросы разрешены? (константа включается при переносе на хостинг)
 *
 * @return bool
 */
function sad_znaniy_social_external_allowed() {
	return ( defined( 'SZ_EXTERNAL_ON' ) && SZ_EXTERNAL_ON );
}

/**
 * POST-запрос к API сети.
 *
 * @param string $url  Адрес.
 * @param array  $body Тело запроса.
 * @return array ok / error / data.
 */
function sad_znaniy_social_http_post( $url, $body ) {
	if ( ! sad_znaniy_social_external_allowed() ) {
		return array( 'ok' => false, 'error' => 'Внешние запросы выключены (SZ_EXTERNAL_ON). Включите после переноса на хостинг.' );
	}

	$response = wp_remote_post(
		$url,
		array(
			'timeout' => 20,
			'body'    => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		return array( 'ok' => false, 'error' => $response->get_error_message() );
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		$desc = ( is_array( $json ) && isset( $json['description'] ) ) ? (string) $json['description'] : ( 'HTTP ' . $code );
		return array( 'ok' => false, 'error' => $desc );
	}

	return array( 'ok' => true, 'data' => $json );
}

/**
 * Человеческое пояснение ошибки для журнала.
 *
 * @param string $network Ключ сети.
 * @param string $error   Технический текст.
 * @return string
 */
function sad_znaniy_social_human_error( $network, $error ) {
	$all   = sad_znaniy_social_networks();
	$label = isset( $all[ $network ]['label'] ) ? $all[ $network ]['label'] : $network;
	$low   = mb_strtolower( (string) $error );

	if ( false !== mb_strpos( $low, 'unauthorized' ) || false !== mb_strpos( $low, '401' ) ) {
		return 'Токен ' . $label . ' недействителен или истёк — обновите его во вкладке «Настройки каналов».';
	}
	if ( false !== mb_strpos( $low, 'chat not found' ) || false !== mb_strpos( $low, 'chat_id' ) ) {
		return 'Канал не найден: проверьте chat_id и что бот добавлен администратором канала.';
	}
	if ( false !== mb_strpos( $low, 'blocked' ) ) {
		return 'Бот заблокирован в канале — разблокируйте и нажмите «Повторить».';
	}
	if ( false !== mb_strpos( $low, 'too many requests' ) || false !== mb_strpos( $low, '429' ) ) {
		return 'Сеть ограничила частоту запросов (429). Подождите и нажмите «Повторить».';
	}
	if ( false !== mb_strpos( $low, 'sz_external_on' ) ) {
		return (string) $error;
	}

	return (string) $error;
}

/**
 * Отправка в Telegram: sendPhoto с подписью или sendMessage.
 *
 * @param array $payload Данные поста.
 * @return array ok / error.
 */
function sad_znaniy_social_send_telegram( $payload ) {
	$token = sad_znaniy_social_channel_token( 'telegram' );
	$chat  = sad_znaniy_social_channel_target( 'telegram' );

	if ( '' === $token || '' === $chat ) {
		return array( 'ok' => false, 'error' => 'Не заданы токен бота или chat_id (вкладка «Настройки каналов»).' );
	}

	$text  = (string) ( $payload['text'] ?? '' );
	$link  = (string) ( $payload['link'] ?? '' );
	$image = (string) ( $payload['image'] ?? '' );

	$markup = wp_json_encode(
		array(
			'inline_keyboard' => array(
				array(
					array(
						'text' => '🌐 Сайт',
						'url'  => $link,
					),
				),
			),
		)
	);

	if ( '' !== $image && mb_strlen( $text ) <= 1000 ) {
		return sad_znaniy_social_http_post(
			'https://api.telegram.org/bot' . $token . '/sendPhoto',
			array(
				'chat_id'      => $chat,
				'photo'        => $image,
				'caption'      => $text,
				'parse_mode'   => 'HTML',
				'reply_markup' => $markup,
			)
		);
	}

	return sad_znaniy_social_http_post(
		'https://api.telegram.org/bot' . $token . '/sendMessage',
		array(
			'chat_id'                  => $chat,
			'text'                     => $text,
			'parse_mode'               => 'HTML',
			'reply_markup'             => $markup,
			'disable_web_page_preview' => 'false',
		)
	);
}

/**
 * Диспетчер отправки по сети.
 *
 * @param string $network Ключ сети.
 * @param array  $payload Данные поста.
 * @return array ok / error.
 */
function sad_znaniy_social_send( $network, $payload ) {
	if ( 'telegram' === $network ) {
		return sad_znaniy_social_send_telegram( $payload );
	}

	$all   = sad_znaniy_social_networks();
	$label = isset( $all[ $network ]['label'] ) ? $all[ $network ]['label'] : $network;
	return array( 'ok' => false, 'error' => 'Отправка в ' . $label . ' ещё не подключена (следующая партия этапа 8.5).' );
}

/**
 * Тестовый пост в канал (кнопка «Проверить связь»).
 *
 * @param string $network Ключ сети.
 * @return string Пустая строка — успех, иначе текст ошибки.
 */
function sad_znaniy_social_test_send( $network ) {
	$sent = sad_znaniy_social_send(
		$network,
		array(
			'text'  => '🌱 Тестовое сообщение «Сад знаний». Если вы его видите — канал подключён правильно.',
			'link'  => home_url( '/' ),
			'image' => '',
		)
	);

	if ( ! empty( $sent['ok'] ) ) {
		return '';
	}
	return sad_znaniy_social_human_error( $network, isset( $sent['error'] ) ? (string) $sent['error'] : 'Ошибка отправки' );
}

/**
 * Не превышен ли дневной лимит по сети.
 *
 * @param string $network Ключ сети.
 * @return bool
 */
function sad_znaniy_social_daily_limit_ok( $network ) {
	global $wpdb;
	$table = $wpdb->prefix . 'sz_social_queue';
	$day   = current_time( 'Y-m-d' );
	$n     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE network = %s AND status = 'sent' AND DATE(posted_at) = %s", $network, $day ) ); // phpcs:ignore WordPress.DB
	return $n < sad_znaniy_social_daily_limit();
}

/**
 * Обрабатывает одно задание очереди: отправляет и пишет результат.
 *
 * @param object $row Строка очереди.
 * @return bool Успех.
 */
function sad_znaniy_social_process_item( $row ) {
	global $wpdb;
	$table = $wpdb->prefix . 'sz_social_queue';

	$payload = json_decode( (string) $row->payload, true );
	if ( ! is_array( $payload ) ) {
		$payload = array( 'text' => (string) $row->payload, 'link' => '', 'image' => '' );
	}

	$result   = sad_znaniy_social_send( (string) $row->network, $payload );
	$attempts = (int) $row->attempts + 1;

	if ( ! empty( $result['ok'] ) ) {
		$wpdb->update( // phpcs:ignore WordPress.DB
			$table,
			array(
				'status'    => 'sent',
				'attempts'  => $attempts,
				'posted_at' => current_time( 'mysql' ),
				'error'     => '',
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
		sad_znaniy_social_mark_posted( (int) $row->post_id, (string) $row->network );
		return true;
	}

	$error  = sad_znaniy_social_human_error( (string) $row->network, isset( $result['error'] ) ? (string) $result['error'] : 'Неизвестная ошибка' );
	$failed = ( $attempts >= 3 );
	$next   = $failed ? (string) $row->scheduled_at : gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + 60 );

	$wpdb->update( // phpcs:ignore WordPress.DB
		$table,
		array(
			'status'       => $failed ? 'error' : 'pending',
			'attempts'     => $attempts,
			'error'        => mb_substr( $error, 0, 500 ),
			'scheduled_at' => $next,
		),
		array( 'id' => (int) $row->id ),
		array( '%s', '%d', '%s', '%s' ),
		array( '%d' )
	);

	return false;
}

/**
 * Воркер WP-Cron: отправляет по одному заданию за прогон (пауза ≥60 секунд).
 */
function sad_znaniy_social_cron_worker() {
	if ( ! sad_znaniy_social_is_enabled() || ! sad_znaniy_social_external_allowed() ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'sz_social_queue';

	$last = $wpdb->get_var( "SELECT MAX(posted_at) FROM {$table} WHERE status = 'sent'" ); // phpcs:ignore WordPress.DB
	if ( $last && ( time() - (int) strtotime( (string) $last . ' UTC' ) ) < 60 ) {
		return;
	}

	$now = current_time( 'mysql' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC, id ASC LIMIT 1", $now ) ); // phpcs:ignore WordPress.DB
	if ( ! $row ) {
		return;
	}

	if ( ! sad_znaniy_social_daily_limit_ok( (string) $row->network ) ) {
		$next = sad_znaniy_social_slot_after( strtotime( $now ) + DAY_IN_SECONDS, 0 );
		$wpdb->update( $table, array( 'scheduled_at' => $next ), array( 'id' => (int) $row->id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		return;
	}

	sad_znaniy_social_process_item( $row );
}

/**
 * Добавляет свой интервал в расписание WP-Cron.
 *
 * @param array $schedules Интервалы.
 * @return array
 */
function sad_znaniy_social_cron_schedules( $schedules ) {
	$schedules['sz_5min'] = array(
		'interval' => 300,
		'display'  => 'Каждые 5 минут (Сад знаний)',
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'sad_znaniy_social_cron_schedules' );

/**
 * Планирует событие очереди.
 */
function sad_znaniy_social_cron_init() {
	if ( ! wp_next_scheduled( 'sz_social_cron' ) ) {
		wp_schedule_event( time() + 300, 'sz_5min', 'sz_social_cron' );
	}
}
add_action( 'init', 'sad_znaniy_social_cron_init', 23 );
add_action( 'sz_social_cron', 'sad_znaniy_social_cron_worker' );

/**
 * Кнопка «Проверить связь»: отправляет тестовый пост и возвращает результат.
 */
function sad_znaniy_social_handle_test() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'sad-znaniy' ) );
	}
	check_admin_referer( 'sz_social_test' );

	$network = isset( $_POST['network'] ) ? sanitize_key( wp_unslash( $_POST['network'] ) ) : '';
	$all     = sad_znaniy_social_networks();
	$result  = 'Неизвестная сеть: ' . $network;

	if ( isset( $all[ $network ] ) ) {
		$error  = sad_znaniy_social_test_send( $network );
		$result = ( '' === $error ) ? 'ok' : $error;
	}

	set_transient( 'sz_social_test_' . get_current_user_id(), $result, 120 );

	wp_safe_redirect( add_query_arg( array( 'page' => 'sz-distribution', 'tab' => 'settings', 'sz_msg' => 'test' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_sz_social_test', 'sad_znaniy_social_handle_test' );

