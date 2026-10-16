<?php
/**
 * ПИСЬМО С ПЛАНОМ (Этап 6, Партия 5).
 *
 * Маршрут POST /sad-znaniy/v1/plans/<id>/mail — кнопка «✉ Отправить план»
 * в планировщике. В письме: состав плана, ссылка на read-only план и ссылка
 * на .ics для календаря телефона.
 *
 * Защита: только залогиненным (permission-callback кабинета), адрес получателя
 * проверяется через is_email(), служебный адрес сайта запрещён (иначе сайт
 * «спамит сам себя»), лимит — 10 писем в час на пользователя, значение
 * правится опцией sad_znaniy_planner_mail_limit.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Лимит писем в час (правится опцией, по умолчанию 10).
 *
 * @return int
 */
function sad_znaniy_planner_mail_limit() {
	$limit = (int) get_option( 'sad_znaniy_planner_mail_limit', 10 );
	return $limit > 0 ? $limit : 10;
}

/**
 * Квота пользователя за последний час.
 *
 * @param int $user_id Пользователь.
 * @return array { sent, left, log }
 */
function sad_znaniy_planner_mail_quota( $user_id ) {
	$now   = time();
	$raw   = get_user_meta( (int) $user_id, '_sz_plan_mail_log', true );
	$raw   = is_array( $raw ) ? $raw : array();
	$limit = sad_znaniy_planner_mail_limit();
	$log   = array();
	foreach ( $raw as $stamp ) {
		$stamp = (int) $stamp;
		if ( $stamp > ( $now - HOUR_IN_SECONDS ) ) {
			$log[] = $stamp;
		}
	}
	return array(
		'sent' => count( $log ),
		'left' => max( 0, $limit - count( $log ) ),
		'log'  => $log,
	);
}

/**
 * Текстовая сводка плана для письма: размер, элементы, культуры.
 *
 * @param array $plan План.
 * @return string
 */
function sad_znaniy_planner_mail_summary( $plan ) {
	$elements = ( isset( $plan['elements'] ) && is_array( $plan['elements'] ) ) ? $plan['elements'] : array();
	$crops    = array();
	foreach ( $elements as $element ) {
		$slug = isset( $element['crop'] ) ? (string) $element['crop'] : '';
		if ( '' === $slug ) {
			continue;
		}
		$crops[ $slug ] = isset( $crops[ $slug ] ) ? $crops[ $slug ] + 1 : 1;
	}

	$parts   = array();
	$parts[] = 'Элементов в плане: ' . count( $elements ) . '.';

	$w = isset( $plan['plot']['w'] ) ? (float) $plan['plot']['w'] : 0;
	$h = isset( $plan['plot']['h'] ) ? (float) $plan['plot']['h'] : 0;
	if ( $w > 0 && $h > 0 ) {
		$parts[] = 'Размер участка: ' . $w . ' × ' . $h . ' м.';
	}

	if ( $crops ) {
		$list = array();
		foreach ( $crops as $slug => $count ) {
			$plant  = get_page_by_path( $slug, OBJECT, 'plant' );
			$list[] = ( $plant ? $plant->post_title : $slug ) . ' — ' . $count . ' шт.';
		}
		$parts[] = 'Культуры: ' . implode( ', ', $list ) . '.';
	}

	return implode( "\n", $parts );
}

/**
 * Регистрация маршрута письма.
 *
 * @return void
 */
function sad_znaniy_planner_mail_routes() {
	register_rest_route(
		'sad-znaniy/v1',
		'/plans/(?P<id>[a-f0-9]{32})/mail',
		array(
			array(
				'methods'             => 'POST',
				'callback'            => 'sad_znaniy_planner_rest_mail',
				'permission_callback' => 'sad_znaniy_planner_rest_can',
			),
		)
	);
}
add_action( 'rest_api_init', 'sad_znaniy_planner_mail_routes' );

/**
 * POST /plans/<id>/mail — отправить план на e-mail.
 *
 * @param WP_REST_Request $req Запрос.
 * @return WP_REST_Response|WP_Error
 */
function sad_znaniy_planner_rest_mail( $req ) {
	$id = (string) $req->get_param( 'id' );
	if ( ! sad_znaniy_planner_is_id( $id ) ) {
		return new WP_Error( 'sz_bad_plan', 'Некорректный идентификатор плана.', array( 'status' => 400 ) );
	}

	$plan = sad_znaniy_planner_get_public( $id );
	if ( ! $plan ) {
		return new WP_Error( 'sz_no_plan', 'План не найден.', array( 'status' => 404 ) );
	}

	$to = sanitize_email( (string) $req->get_param( 'to' ) );
	if ( '' === $to || ! is_email( $to ) ) {
		return new WP_Error( 'sz_bad_email', 'Проверьте адрес: похоже, в нём опечатка.', array( 'status' => 400 ) );
	}

	$self = array(
		strtolower( (string) get_option( 'admin_email' ) ),
		strtolower( (string) get_site_option( 'admin_email' ) ),
	);
	if ( in_array( strtolower( $to ), $self, true ) ) {
		return new WP_Error( 'sz_self_email', 'Это служебный адрес сайта — укажите адрес получателя.', array( 'status' => 400 ) );
	}

	$quota = sad_znaniy_planner_mail_quota( get_current_user_id() );
	if ( $quota['left'] < 1 ) {
		return new WP_Error(
			'sz_rate_limited',
			'Лимит ' . sad_znaniy_planner_mail_limit() . ' писем в час исчерпан. Попробуйте позже.',
			array( 'status' => 429 )
		);
	}

	$site    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$title   = isset( $plan['meta']['title'] ) ? (string) $plan['meta']['title'] : 'Мой участок';
	$link    = sad_znaniy_planner_share_url( $id );
	$ics_url = add_query_arg(
		array(
			'plan' => $id,
			'ics'  => 1,
		),
		sad_znaniy_planner_page_url()
	);

	$subject = 'План участка: ' . $title . ' — ' . $site;
	$lines   = array(
		'Здравствуйте!',
		'',
		'Вам отправили план участка «' . $title . '» с сайта ' . $site . '.',
		'',
		sad_znaniy_planner_mail_summary( $plan ),
		'',
		'Открыть план (просмотр): ' . $link,
		'Напоминания о посадках в календарь телефона: ' . $ics_url,
		'',
		'Точные сроки по культурам — в карточках раздела «База растений».',
		'',
		'— ' . $site . ', ' . home_url( '/' ),
	);

	$sent = wp_mail( $to, $subject, implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
	if ( ! $sent ) {
		return new WP_Error( 'sz_mail_failed', 'Письмо не ушло: сервер не принял отправку. Попробуйте позже.', array( 'status' => 500 ) );
	}

	$log   = $quota['log'];
	$log[] = time();
	update_user_meta( get_current_user_id(), '_sz_plan_mail_log', array_slice( $log, -50 ) );

	return rest_ensure_response(
		array(
			'ok'      => true,
			'sent_to' => $to,
			'left'    => max( 0, $quota['left'] - 1 ),
		)
	);
}
