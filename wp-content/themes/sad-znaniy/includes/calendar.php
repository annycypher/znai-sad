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

/**
 * Иконки типов работ (для карточек печати и списков).
 *
 * @return array Ключ таксономии work_type => эмодзи.
 */
function sad_znaniy_work_type_icons() {
	return array(
		"posev"      => "🌱",
		"posadka"    => "🌿",
		"poliv"      => "💧",
		"podkormka"  => "🧴",
		"obrezka"    => "✂️",
		"zashchita"  => "🛡️",
		"sbor"       => "🧺",
		"podgotovka" => "🧰",
	);
}

/**
 * Место под узкий баннер над содержимым страницы календаря.
 *
 * Наполняется виджетом в «Внешний вид → Виджеты → Баннер над содержимым».
 * Пустая заглушка видна только администратору и в печать не попадает.
 *
 * @return void
 */
function sad_znaniy_banner_slot() {
	if ( is_active_sidebar( "banner-top" ) ) {
		echo "<div class=\"banner-slot\">";
		dynamic_sidebar( "banner-top" );
		echo "</div>";
		return;
	}
	if ( current_user_can( "manage_options" ) ) {
		echo "<div class=\"banner-slot banner-slot--empty\"><span>"
			. esc_html__( "Здесь место под баннер 728×90 — добавьте виджет в «Внешний вид → Виджеты → Баннер над содержимым (узкий)». В печать этот блок не выводится.", "sad-znaniy" )
			. "</span></div>";
	}
}

/**
 * Плавающая панель инструментов календаря.
 *
 * Выводится через wp_footer — уже ЗА пределами .frame, у которого стоит
 * backdrop-filter (он делает предка контейнером для position: fixed и
 * «приклеивал» панель к странице).
 *
 * @return void
 */
function sad_znaniy_calendar_tools_fab() {
	if ( ! is_page_template( "template-calendar.php" ) ) {
		return;
	}
	$args = array( "print" => "1", "all" => "1" );
	foreach ( array( "region", "exp" ) as $sz_k ) {
		if ( isset( $_GET[ $sz_k ] ) && "" !== (string) $_GET[ $sz_k ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ссылка печати.
			$args[ $sz_k ] = sanitize_key( wp_unslash( (string) $_GET[ $sz_k ] ) );
		}
	}
	$sz_year_url = add_query_arg( $args, home_url( "/kalendar/" ) );

	$sz_ics_args = array();
	foreach ( array( "region", "exp" ) as $sz_k ) {
		if ( isset( $_GET[ $sz_k ] ) && "" !== (string) $_GET[ $sz_k ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ссылки календаря.
			$sz_ics_args[ $sz_k ] = sanitize_key( wp_unslash( (string) $_GET[ $sz_k ] ) );
		}
	}
	if ( isset( $_GET["month"] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sz_ics_args["month"] = (int) $_GET["month"];
	}
	if ( isset( $_GET["year"] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sz_ics_args["year"] = (int) $_GET["year"];
	}
	$sz_ics_url = add_query_arg( $sz_ics_args, home_url( "/kalendar/ical/" ) );
		?>
	<div class="tools-fab" id="toolsFab">
		<button type="button" class="fab-close" id="toolsClose" title="<?php esc_attr_e( "Скрыть панель", "sad-znaniy" ); ?>" aria-label="<?php esc_attr_e( "Скрыть панель инструментов", "sad-znaniy" ); ?>">✕</button>
		<button type="button" class="fab-btn" id="fabPrint" title="<?php esc_attr_e( "Печать", "sad-znaniy" ); ?>" aria-label="<?php esc_attr_e( "Печать", "sad-znaniy" ); ?>">🖨</button>
		<button type="button" class="fab-btn" id="fabPdf" title="<?php esc_attr_e( "Сохранить в PDF — в диалоге выберите «Сохранить как PDF»", "sad-znaniy" ); ?>" aria-label="<?php esc_attr_e( "Сохранить в PDF", "sad-znaniy" ); ?>">📄</button>
		<button type="button" class="fab-btn" id="fabJpg" title="<?php esc_attr_e( "Сохранить в JPG", "sad-znaniy" ); ?>" aria-label="<?php esc_attr_e( "Сохранить в JPG", "sad-znaniy" ); ?>">🖼</button>
		<a class="fab-btn" href="<?php echo esc_url( $sz_ics_url ); ?>" title="<?php esc_attr_e( "В календарь телефона (.ics)", "sad-znaniy" ); ?>" aria-label="<?php esc_attr_e( "В календарь телефона", "sad-znaniy" ); ?>">🗓</a>
		<a class="fab-btn" href="<?php echo esc_url( $sz_year_url ); ?>" title="<?php esc_attr_e( "Календарь на год (печать/PDF)", "sad-znaniy" ); ?>" aria-label="<?php esc_attr_e( "Календарь на год", "sad-znaniy" ); ?>">📅</a>
		<button type="button" class="fab-btn" id="fabShare" title="<?php esc_attr_e( "Поделиться", "sad-znaniy" ); ?>" aria-label="<?php esc_attr_e( "Поделиться", "sad-znaniy" ); ?>">🔗</button>
	</div>
	<button type="button" class="tools-handle" id="toolsHandle" title="<?php esc_attr_e( "Инструменты календаря", "sad-znaniy" ); ?>" aria-label="<?php esc_attr_e( "Показать панель инструментов", "sad-znaniy" ); ?>" hidden><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg></button>
		<?php
}
add_action( "wp_footer", "sad_znaniy_calendar_tools_fab", 5 );