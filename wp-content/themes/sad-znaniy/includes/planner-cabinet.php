<?php
/**
* Планировщик участка — КАБИНЕТ (Этап 6, Партия 4).
*
* Хранение планов пользователя, строгая валидация схемы (спека §1),
* REST для владельца (список/сохранение/удаление/копия) и публичное чтение
* плана по UUID для режима просмотра (?plan=<uuid>).
*
* Безопасность (требования после Этапа 7.3):
*   • UUID — 32 hex-символа (random_bytes), перебор нереален;
*   • чужие планы недоступны: ключ меты строится от текущего пользователя,
*     в теле хранится owner и сверяется при чтении и записи;
*   • схема валидируется на входе (белый список, диапазоны, лимиты);
*   • на выводе — esc_html в шаблонах и textContent в JS.
*
* @package sad-znaniy
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Лимиты хранения.
*
* @return array
*/
function sad_znaniy_planner_limits() {
	return apply_filters(
	'sad_znaniy_planner_limits',
	array(
	'plans'   => 20,
	'bytes'   => 307200,
	'items'   => 200,
	'label'   => 120,
	'version' => 1,
	)
	);
}

/**
* Ключи типов элементов (как в демо).
*
* @return array
*/
function sad_znaniy_planner_types() {
	return array( 'bed', 'tree', 'bush', 'house', 'build', 'green', 'comp', 'path', 'water', 'rest' );
}

/**
* Новый UUID плана: 32 hex-символа.
*
* @return string
*/
function sad_znaniy_planner_new_id() {
	return bin2hex( random_bytes( 16 ) );
}

/**
* Корректный ли это UUID плана.
*
* @param string $id Идентификатор.
* @return bool
*/
function sad_znaniy_planner_is_id( $id ) {
	return (bool) preg_match( '/^[a-f0-9]{32}$/', (string) $id );
}

/**
* Ключ меты плана.
*
* @param string $id UUID.
* @return string
*/
function sad_znaniy_planner_meta_key( $id ) {
	return '_sz_plan_' . $id;
}

/**
* Индекс планов пользователя.
*
* @param int $user_id Пользователь.
* @return array
*/
function sad_znaniy_planner_index( $user_id ) {
	$idx = get_user_meta( (int) $user_id, '_sz_plans', true );
	return is_array( $idx ) ? $idx : array();
}

/**
* Слаги растений (для проверки культуры в элементе).
*
* @return array
*/
function sad_znaniy_planner_plant_slugs() {
	$cached = wp_cache_get( 'sz_planner_plant_slugs', 'sad-znaniy' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$slugs = array();
	$ids   = get_posts(
	array(
	'post_type'      => 'plant',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
	)
	);
	foreach ( $ids as $pid ) {
		$slugs[] = (string) get_post_field( 'post_name', $pid );
	}
	wp_cache_set( 'sz_planner_plant_slugs', $slugs, 'sad-znaniy', HOUR_IN_SECONDS );
	return $slugs;
}
/**
* Валидация и нормализация плана (белый список ключей, диапазоны, лимиты).
*
* Принимает клиентский снимок (assets/js/planner.js → planSnapshot()):
* version, id, meta{title,region,updated}, plot{shape,north,width,height},
* elements[{id,type,x,y,width,height,rotation,crop,label}], gridStep.
*
* @param mixed $raw   Разобранный JSON плана.
* @param int   $owner ID владельца.
* @return array|WP_Error Чистый план или ошибка.
*/
function sad_znaniy_planner_normalize_plan( $raw, $owner ) {
	$limits = sad_znaniy_planner_limits();
	$types  = sad_znaniy_planner_types();
	$slugs  = sad_znaniy_planner_plant_slugs();

	if ( ! is_array( $raw ) ) {
		return new WP_Error( 'sz_plan_schema', 'План должен быть объектом JSON.' );
	}

	$version = isset( $raw['version'] ) ? (int) $raw['version'] : 1;
	if ( $version < 1 || $version > (int) $limits['version'] ) {
		return new WP_Error( 'sz_plan_version', 'Версия плана не поддерживается.' );
	}

	$meta = isset( $raw['meta'] ) && is_array( $raw['meta'] ) ? $raw['meta'] : array();
	$title = isset( $meta['title'] ) ? sanitize_text_field( (string) $meta['title'] ) : 'Мой участок';
	$title = mb_substr( '' === trim( $title ) ? 'Мой участок' : $title, 0, (int) $limits['label'] );

	$region = isset( $meta['region'] ) ? sanitize_key( (string) $meta['region'] ) : '';
	if ( '' !== $region && ! array_key_exists( $region, sad_znaniy_region_keys() ) ) {
		$region = '';
	}

	$plot = isset( $raw['plot'] ) && is_array( $raw['plot'] ) ? $raw['plot'] : array();
	$pw   = isset( $plot['width'] ) ? (float) $plot['width'] : 20;
	$ph   = isset( $plot['height'] ) ? (float) $plot['height'] : 30;
	$pw   = max( 4.0, min( 100.0, round( $pw * 2 ) / 2 ) );
	$ph   = max( 4.0, min( 100.0, round( $ph * 2 ) / 2 ) );

	$grid = isset( $raw['gridStep'] ) ? (float) $raw['gridStep'] : 0.5;
	if ( ! in_array( $grid, array( 0.25, 0.5, 1.0 ), true ) ) {
		$grid = 0.5;
	}

	$elements = isset( $raw['elements'] ) && is_array( $raw['elements'] ) ? array_slice( $raw['elements'], 0, (int) $limits['items'] ) : array();
	$clean    = array();
	foreach ( $elements as $e ) {
		if ( ! is_array( $e ) ) {
			continue;
		}
		$type = isset( $e['type'] ) ? (string) $e['type'] : '';
		if ( ! in_array( $type, $types, true ) ) {
			continue;
		}

		$w = isset( $e['width'] ) ? (float) $e['width'] : 1;
		$h = isset( $e['height'] ) ? (float) $e['height'] : 1;
		$w = max( 0.5, min( $pw, round( $w * 2 ) / 2 ) );
		$h = max( 0.5, min( $ph, round( $h * 2 ) / 2 ) );

		$x = isset( $e['x'] ) ? (float) $e['x'] : 0;
		$y = isset( $e['y'] ) ? (float) $e['y'] : 0;
		$x = max( 0.0, min( $pw - $w, round( $x * 2 ) / 2 ) );
		$y = max( 0.0, min( $ph - $h, round( $y * 2 ) / 2 ) );

		$crop = isset( $e['crop'] ) ? (string) $e['crop'] : '';
		if ( '' !== $crop && ! in_array( $crop, $slugs, true ) ) {
			$crop = '';
		}

		$eid = isset( $e['id'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $e['id'] ) : '';
		$eid = mb_substr( (string) $eid, 0, 16 );
		if ( '' === $eid ) {
			$eid = substr( sad_znaniy_planner_new_id(), 0, 8 );
		}

		$label = isset( $e['label'] ) ? sanitize_text_field( (string) $e['label'] ) : '';
		$label = mb_substr( $label, 0, (int) $limits['label'] );

		$clean[] = array(
		'id'       => $eid,
		'type'     => $type,
		'x'        => $x,
		'y'        => $y,
		'width'    => $w,
		'height'   => $h,
		'rotation' => 0,
		'crop'     => $crop,
		'label'    => $label,
		);
	}

	return array(
	'version'  => (int) $limits['version'],
	'owner'    => (int) $owner,
	'meta'     => array(
	'title'   => $title,
	'region'  => $region,
	'updated' => current_time( 'mysql' ),
	),
	'plot'     => array(
	'shape'  => 'rect',
	'north'  => 'up',
	'width'  => $pw,
	'height' => $ph,
	),
	'elements' => $clean,
	'gridStep' => $grid,
	);
}
/**
* Свой план по UUID (строгая проверка владельца).
*
* @param string $id      UUID.
* @param int    $user_id Пользователь.
* @return array|null
*/
function sad_znaniy_planner_get_own( $id, $user_id ) {
	if ( ! sad_znaniy_planner_is_id( $id ) ) {
		return null;
	}
	$raw = get_user_meta( (int) $user_id, sad_znaniy_planner_meta_key( $id ), true );
	if ( ! is_string( $raw ) || '' === $raw ) {
		return null;
	}
	$plan = json_decode( $raw, true );
	if ( ! is_array( $plan ) || (int) ( isset( $plan['owner'] ) ? $plan['owner'] : 0 ) !== (int) $user_id ) {
		return null;
	}
	$plan['id'] = $id;
	return $plan;
}

/**
* Сохранение плана в кабинет: валидация → лимиты → запись тела и индекса.
*
* @param mixed $raw     Снимок плана от клиента.
* @param int   $user_id Владелец.
* @return array|WP_Error { plan, row, url }
*/
function sad_znaniy_planner_save( $raw, $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return new WP_Error( 'sz_plan_auth', 'Нужен вход в аккаунт.' );
	}

	$limits = sad_znaniy_planner_limits();

	if ( is_string( $raw ) && strlen( $raw ) > (int) $limits['bytes'] ) {
		return new WP_Error( 'sz_plan_size', 'План слишком большой.' );
	}
	if ( is_array( $raw ) && strlen( (string) wp_json_encode( $raw ) ) > (int) $limits['bytes'] ) {
		return new WP_Error( 'sz_plan_size', 'План слишком большой.' );
	}

	$plan = sad_znaniy_planner_normalize_plan( $raw, $user_id );
	if ( is_wp_error( $plan ) ) {
		return $plan;
	}

	$id = ( is_array( $raw ) && isset( $raw['id'] ) && sad_znaniy_planner_is_id( $raw['id'] ) ) ? (string) $raw['id'] : sad_znaniy_planner_new_id();
	$plan['id'] = $id;

	$index = sad_znaniy_planner_index( $user_id );
	$known = false;
	foreach ( $index as $row ) {
		if ( isset( $row['id'] ) && (string) $row['id'] === $id ) {
			$known = true;
			break;
		}
	}
	if ( $known && null === sad_znaniy_planner_get_own( $id, $user_id ) ) {
		return new WP_Error( 'sz_plan_owner', 'Этот план вам не принадлежит.' );
	}
	if ( ! $known && count( $index ) >= (int) $limits['plans'] ) {
		return new WP_Error( 'sz_plan_limit', 'Достигнут лимит планов — удалите ненужные в разделе «Мои планы».' );
	}

	$json = (string) wp_json_encode( $plan );
	if ( strlen( $json ) > (int) $limits['bytes'] ) {
		return new WP_Error( 'sz_plan_size', 'План слишком большой.' );
	}
	update_user_meta( $user_id, sad_znaniy_planner_meta_key( $id ), $json );

	$prev    = null;
	foreach ( sad_znaniy_planner_index( $user_id ) as $r ) {
		if ( isset( $r['id'] ) && (string) $r['id'] === $id ) {
			$prev = $r;
			break;
		}
	}
	$created = ( $prev && ! empty( $prev['created'] ) ) ? (string) $prev['created'] : current_time( 'mysql' );

	$row   = array(
	'id'       => $id,
	'title'    => $plan['meta']['title'],
	'region'   => $plan['meta']['region'],
	'updated'  => $plan['meta']['updated'],
		'created'  => $created,
	'elements' => count( $plan['elements'] ),
	'plot'     => array(
	'w' => $plan['plot']['width'],
	'h' => $plan['plot']['height'],
	),
	);
	$index = array_values(
	array_filter(
	$index,
	function ( $r ) use ( $id ) {
		return ! isset( $r['id'] ) || (string) $r['id'] !== $id;
	}
	)
	);
	array_unshift( $index, $row );
	update_user_meta( $user_id, '_sz_plans', array_slice( $index, 0, (int) $limits['plans'] ) );

	return array(
	'plan' => $plan,
	'row'  => $row,
	'url'  => sad_znaniy_planner_share_url( $id ),
	);
}

/**
* Удаление своего плана (тело + запись индекса).
*
* @param string $id      UUID.
* @param int    $user_id Владелец.
* @return bool
*/
function sad_znaniy_planner_delete( $id, $user_id ) {
	if ( null === sad_znaniy_planner_get_own( $id, $user_id ) ) {
		return false;
	}
	delete_user_meta( (int) $user_id, sad_znaniy_planner_meta_key( $id ) );
	$index = array_values(
	array_filter(
	sad_znaniy_planner_index( $user_id ),
	function ( $r ) use ( $id ) {
		return ! isset( $r['id'] ) || (string) $r['id'] !== $id;
	}
	)
	);
	update_user_meta( (int) $user_id, '_sz_plans', $index );
	return true;
}

/**
* Копия своего плана в свой кабинет (новый UUID).
*
* @param string $id      UUID источника.
* @param int    $user_id Владелец.
* @return array|WP_Error
*/
function sad_znaniy_planner_copy( $id, $user_id ) {
	$plan = sad_znaniy_planner_get_own( $id, $user_id );
	if ( ! $plan ) {
		return new WP_Error( 'sz_plan_notfound', 'План не найден.' );
	}
	$plan['id'] = sad_znaniy_planner_new_id();
	$plan['meta']['title'] = mb_substr( $plan['meta']['title'] . ' (копия)', 0, (int) sad_znaniy_planner_limits()['label'] );
	return sad_znaniy_planner_save( $plan, $user_id );
}
/**
* Публичное чтение плана по UUID (режим просмотра, виральность по ссылке).
* Владелец в ответе не отдаётся.
*
* @param string $id UUID.
* @return array|null
*/
function sad_znaniy_planner_get_public( $id ) {
	if ( ! sad_znaniy_planner_is_id( $id ) ) {
		return null;
	}
	global $wpdb;
	$key = sad_znaniy_planner_meta_key( $id );
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT 1", $key ) );
	if ( ! is_string( $raw ) || '' === $raw ) {
		return null;
	}
	$plan = json_decode( $raw, true );
	if ( ! is_array( $plan ) || empty( $plan['plot'] ) ) {
		return null;
	}
	$plan['id'] = $id;
	unset( $plan['owner'] );
	return $plan;
}

/**
* URL страницы планировщика.
*
* @return string
*/
function sad_znaniy_planner_page_url() {
	$page = get_page_by_path( 'planirovshchik' );
	return $page ? (string) get_permalink( $page ) : home_url( '/planirovshchik/' );
}

/**
* Публичная ссылка на план (read-only).
*
* @param string $id UUID.
* @return string
*/
function sad_znaniy_planner_share_url( $id ) {
	return add_query_arg( 'plan', $id, sad_znaniy_planner_page_url() );
}

/**
* URL страницы «Мои планы».
*
* @return string
*/
function sad_znaniy_planner_my_plans_url() {
	$page = get_page_by_path( 'moi-plany' );
	return $page ? (string) get_permalink( $page ) : home_url( '/moi-plany/' );
}

/**
* Список планов пользователя (для шаблона «Мои планы» и REST).
*
* @param int $user_id Пользователь.
* @return array
*/
function sad_znaniy_planner_my_plans( $user_id ) {
	$out = array();
	foreach ( sad_znaniy_planner_index( $user_id ) as $row ) {
		if ( empty( $row['id'] ) || ! sad_znaniy_planner_is_id( $row['id'] ) ) {
			continue;
		}
		$out[] = array(
		'id'       => (string) $row['id'],
		'title'    => isset( $row['title'] ) ? (string) $row['title'] : 'Мой участок',
		'region'   => isset( $row['region'] ) ? (string) $row['region'] : '',
		'updated'  => isset( $row['updated'] ) ? (string) $row['updated'] : '',
		'created'  => isset( $row['created'] ) ? (string) $row['created'] : '',
		'elements' => isset( $row['elements'] ) ? (int) $row['elements'] : 0,
		'plot'     => ( isset( $row['plot'] ) && is_array( $row['plot'] ) ) ? $row['plot'] : array( 'w' => 0, 'h' => 0 ),
		'edit'     => add_query_arg( 'plan', $row['id'], sad_znaniy_planner_page_url() ),
		'share'    => sad_znaniy_planner_share_url( (string) $row['id'] ),
		);
	}
	return $out;
}

/* === REST кабинета (только владелец) ===================================== */

/**
* Может ли текущий пользователь работать с планами.
*
* @return bool
*/
function sad_znaniy_planner_rest_can() {
	return is_user_logged_in();
}

/**
* Регистрация маршрутов кабинета.
*
* @return void
*/
function sad_znaniy_planner_rest_routes() {
	register_rest_route(
	'sad-znaniy/v1',
	'/plans',
	array(
	array(
	'methods'             => 'GET',
	'callback'            => 'sad_znaniy_planner_rest_list',
	'permission_callback' => 'sad_znaniy_planner_rest_can',
	),
	array(
	'methods'             => 'POST',
	'callback'            => 'sad_znaniy_planner_rest_save',
	'permission_callback' => 'sad_znaniy_planner_rest_can',
	),
	)
	);
	register_rest_route(
	'sad-znaniy/v1',
	'/plans/(?P<id>[a-f0-9]{32})',
	array(
	array(
	'methods'             => 'DELETE',
	'callback'            => 'sad_znaniy_planner_rest_delete',
	'permission_callback' => 'sad_znaniy_planner_rest_can',
	),
	)
	);
	register_rest_route(
	'sad-znaniy/v1',
	'/plans/(?P<id>[a-f0-9]{32})/copy',
	array(
	array(
	'methods'             => 'POST',
	'callback'            => 'sad_znaniy_planner_rest_copy',
	'permission_callback' => 'sad_znaniy_planner_rest_can',
	),
	)
	);
}
add_action( 'rest_api_init', 'sad_znaniy_planner_rest_routes' );

/**
* GET /plans — список планов текущего пользователя.
*
* @return WP_REST_Response
*/
function sad_znaniy_planner_rest_list() {
	return rest_ensure_response( array( 'plans' => sad_znaniy_planner_my_plans( get_current_user_id() ) ) );
}

/**
* POST /plans — сохранить план.
*
* @param WP_REST_Request $req Запрос.
* @return WP_REST_Response|WP_Error
*/
function sad_znaniy_planner_rest_save( $req ) {
	$raw = $req->get_param( 'plan' );
	if ( null === $raw ) {
		$raw = $req->get_json_params();
	}
	if ( is_array( $raw ) && isset( $raw['plan'] ) ) {
		$raw = $raw['plan'];
	}
	$res = sad_znaniy_planner_save( $raw, get_current_user_id() );
	if ( is_wp_error( $res ) ) {
		$res->add_data( array( 'status' => 400 ) );
		return $res;
	}
	return rest_ensure_response(
	array(
	'id'  => (string) $res['plan']['id'],
	'row' => $res['row'],
	'url' => $res['url'],
	)
	);
}

/**
* DELETE /plans/<id> — удалить свой план.
*
* @param WP_REST_Request $req Запрос.
* @return WP_REST_Response|WP_Error
*/
function sad_znaniy_planner_rest_delete( $req ) {
	if ( ! sad_znaniy_planner_delete( (string) $req['id'], get_current_user_id() ) ) {
		return new WP_Error( 'sz_plan_notfound', 'План не найден.', array( 'status' => 404 ) );
	}
	return rest_ensure_response( array( 'deleted' => true ) );
}

/**
* POST /plans/<id>/copy — копия своего плана.
*
* @param WP_REST_Request $req Запрос.
* @return WP_REST_Response|WP_Error
*/
function sad_znaniy_planner_rest_copy( $req ) {
	$res = sad_znaniy_planner_copy( (string) $req['id'], get_current_user_id() );
	if ( is_wp_error( $res ) ) {
		$res->add_data( array( 'status' => 404 ) );
		return $res;
	}
	return rest_ensure_response(
	array(
	'id'  => (string) $res['plan']['id'],
	'row' => $res['row'],
	'url' => $res['url'],
	)
	);
}
/* === Действия со страницы «Мои планы» (без JS, через admin-post) ========= */

/**
 * Небезопасное действие? Проверка nonce + входа + владельца внутри хендлеров.
 *
 * @param string $action Действие.
 * @return void
 */
function sad_znaniy_planner_redirect_back( $action, $arg = '' ) {
$url = sad_znaniy_planner_my_plans_url();
if ( '' !== $arg ) {
$url = add_query_arg( 'sz_msg', $arg, $url );
}
wp_safe_redirect( $url );
exit;
}

/**
 * Удаление плана (форма со страницы «Мои планы»).
 *
 * @return void
 */
function sad_znaniy_planner_handle_delete() {
if ( ! is_user_logged_in() ) {
wp_die( 'Нужен вход.' );
}
check_admin_referer( 'sz_plan_delete' );
$id = isset( $_POST['plan_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['plan_id'] ) ) : '';
$ok = sad_znaniy_planner_delete( $id, get_current_user_id() );
sad_znaniy_planner_redirect_back( 'delete', $ok ? 'deleted' : 'notfound' );
}
add_action( 'admin_post_sz_plan_delete', 'sad_znaniy_planner_handle_delete' );

/**
 * Копирование плана (форма со страницы «Мои планы»).
 *
 * @return void
 */
function sad_znaniy_planner_handle_copy() {
if ( ! is_user_logged_in() ) {
wp_die( 'Нужен вход.' );
}
check_admin_referer( 'sz_plan_copy' );
$id = isset( $_POST['plan_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['plan_id'] ) ) : '';
$res = sad_znaniy_planner_copy( $id, get_current_user_id() );
sad_znaniy_planner_redirect_back( 'copy', is_wp_error( $res ) ? 'notfound' : 'copied' );
}
add_action( 'admin_post_sz_plan_copy', 'sad_znaniy_planner_handle_copy' );

/**
 * Человеческая подпись региона по ключу.
 *
 * @param string $key Ключ региона.
 * @return string
 */
function sad_znaniy_planner_region_label( $key ) {
$keys = sad_znaniy_region_keys();
return isset( $keys[ $key ] ) ? (string) $keys[ $key ] : '';
}

/**
 * Сообщение для плашки после действия.
 *
 * @return array { ok: bool, text: string }
 */
function sad_znaniy_planner_flash() {
$msg = isset( $_GET['sz_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['sz_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$map = array(
'deleted'  => array( true, 'План удалён.' ),
'copied'   => array( true, 'Копия создана — она в списке первым планом.' ),
'notfound' => array( false, 'План не найден или не принадлежит вам.' ),
);
return isset( $map[ $msg ] ) ? array( 'ok' => $map[ $msg ][0], 'text' => $map[ $msg ][1] ) : array( 'ok' => true, 'text' => '' );
}
/**
 * Подставляет шаблон «Мои планы» странице со слагом moi-plany
 * (как у планировщика — без ручного выбора шаблона в админке).
 *
 * @param string $template Путь шаблона, выбранный WordPress.
 * @return string
 */
function sad_znaniy_my_plans_template( $template ) {
if ( is_page() ) {
$post = get_queried_object();
if ( $post && isset( $post->post_name ) && 'moi-plany' === $post->post_name && ! is_page_template( 'template-my-plans.php' ) ) {
$own = get_template_directory() . '/template-my-plans.php';
if ( file_exists( $own ) ) {
return $own;
}
}
}
return $template;
}
add_filter( 'template_include', 'sad_znaniy_my_plans_template' );
/**
 * Создаёт служебную страницу «Мои планы» один раз (как install.php для терминов).
 *
 * @return void
 */
function sad_znaniy_planner_maybe_create_my_plans_page() {
if ( get_page_by_path( 'moi-plany' ) ) {
update_option( 'sad_znaniy_my_plans_page_ready', 1, false );
return;
}
$id = wp_insert_post(
array(
'post_title'   => 'Мои планы участка',
'post_name'    => 'moi-plany',
'post_status'  => 'publish',
'post_type'    => 'page',
'post_content' => '',
)
);
if ( $id && ! is_wp_error( $id ) ) {
update_option( 'sad_znaniy_my_plans_page_ready', 1, false );
}
}
add_action( 'admin_init', 'sad_znaniy_planner_maybe_create_my_plans_page' );
