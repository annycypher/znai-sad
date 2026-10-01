<?php
/**
 * Умный календарь: хранение отметок «выполнено» (Этап 5.5, Партия 3).
 *
 * Для залогиненных пользователей отметки хранятся в user_meta через REST;
 * гости используют localStorage. REST-роут закрыт nonce + is_user_logged_in.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует REST-маршрут для отметок «выполнено».
 */
function sad_znaniy_register_calendar_rest() {
	register_rest_route(
		'sad-znaniy/v1',
		'/calendar/done',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'sad_znaniy_rest_get_done',
				'permission_callback' => 'is_user_logged_in',
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'sad_znaniy_rest_save_done',
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'ids' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'sad_znaniy_register_calendar_rest' );

/**
 * Возвращает ID выполненных событий текущего пользователя.
 *
 * @return int[]
 */
function sad_znaniy_get_user_done() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}

	return array_filter( array_map( 'absint', (array) get_user_meta( $user_id, '_sz_cal_done', true ) ) );
}

/**
 * REST: отдаёт отметки пользователя.
 *
 * @return WP_REST_Response
 */
function sad_znaniy_rest_get_done() {
	return rest_ensure_response( array( 'ids' => sad_znaniy_get_user_done() ) );
}

/**
 * REST: сохраняет отметки пользователя.
 *
 * @param WP_REST_Request $request Запрос.
 * @return WP_REST_Response
 */
function sad_znaniy_rest_save_done( WP_REST_Request $request ) {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return new WP_Error( 'rest_forbidden', __( 'Требуется вход.', 'sad-znaniy' ), array( 'status' => 401 ) );
	}

	$ids = array_filter( array_map( 'absint', (array) $request->get_param( 'ids' ) ) );
	update_user_meta( $user_id, '_sz_cal_done', $ids );

	return rest_ensure_response( array( 'ok' => true ) );
}
