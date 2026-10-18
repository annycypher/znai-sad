<?php
/**
 * Письмо с букетом (Этап 6.5, Партия 3).
 *
 * POST /sad-znaniy/v1/bouquets/mail?id=<uuid>
 * В письме: название, повод, кому, состав, ссылка на read-only просмотр.
 *
 * Защита: только владелец, is_email, запрет служебного адреса сайта,
 * общий лимит с планировщиком (10 писем в час).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Лимит писем в час (общий с планировщиком).
 *
 * @return int
 */
function sad_znaniy_bouquet_mail_limit() {
    if ( function_exists( 'sad_znaniy_planner_mail_limit' ) ) {
        return (int) sad_znaniy_planner_mail_limit();
    }
    $limit = (int) get_option( 'sad_znaniy_planner_mail_limit', 10 );
    return 0 < $limit ? $limit : 10;
}

/**
 * Квота пользователя (общая с планировщиком).
 *
 * @param int $user_id Пользователь.
 * @return array
 */
function sad_znaniy_bouquet_mail_quota( $user_id ) {
    if ( function_exists( 'sad_znaniy_planner_mail_quota' ) ) {
        return sad_znaniy_planner_mail_quota( $user_id );
    }
    return array(
        'sent' => 0,
        'left' => sad_znaniy_bouquet_mail_limit(),
        'log'  => array(),
    );
}

/**
 * Состав букета для письма.
 *
 * @param array $bouquet Букет.
 * @return array { stems, text }
 */
function sad_znaniy_bouquet_mail_summary( $bouquet ) {
    $flowers = sad_znaniy_bouquet_get_flowers();
    $lines   = array();
    $stems   = 0;

    foreach ( (array) $bouquet['items'] as $item ) {
        $slug = isset( $item['slug'] ) ? (string) $item['slug'] : '';
        if ( ! isset( $flowers[ $slug ] ) ) {
            continue;
        }
        $count  = isset( $item['count'] ) ? (int) $item['count'] : 1;
        $stems += $count;
        $lines[] = '• ' . $flowers[ $slug ]['title'] . ' — ' . $count . ' шт. (' . $flowers[ $slug ]['meaning'] . ')';
    }

    return array(
        'stems' => $stems,
        'text'  => implode( "\n", $lines ),
    );
}

/**
 * Регистрация маршрута письма.
 */
function sad_znaniy_bouquet_mail_routes() {
    register_rest_route(
        'sad-znaniy/v1',
        '/bouquets/mail',
        array(
            array(
                'methods'             => 'POST',
                'callback'            => 'sad_znaniy_bouquet_rest_mail',
                'permission_callback' => 'sad_znaniy_bouquet_rest_can',
            ),
        )
    );
}
add_action( 'rest_api_init', 'sad_znaniy_bouquet_mail_routes' );

/**
 * POST /bouquets/mail — отправить письмо.
 *
 * @param WP_REST_Request $req Запрос.
 * @return WP_REST_Response|WP_Error
 */
function sad_znaniy_bouquet_rest_mail( $req ) {
    $id = (string) $req->get_param( 'id' );
    if ( ! sad_znaniy_bouquet_is_id( $id ) ) {
        return new WP_Error( 'sz_bq_bad_id', 'Некорректный идентификатор букета.', array( 'status' => 400 ) );
    }

    $bouquet = sad_znaniy_bouquet_get_own( $id, get_current_user_id() );
    if ( ! $bouquet ) {
        return new WP_Error( 'sz_bq_notfound', 'Букет не найден.', array( 'status' => 404 ) );
    }

    $to = sanitize_email( (string) $req->get_param( 'to' ) );
    if ( '' === $to || ! is_email( $to ) ) {
        return new WP_Error( 'sz_bq_bad_email', 'Проверьте адрес: похоже, в нём опечатка.', array( 'status' => 400 ) );
    }

    $self = array(
        strtolower( (string) get_option( 'admin_email' ) ),
        strtolower( (string) get_site_option( 'admin_email' ) ),
    );
    if ( in_array( strtolower( $to ), $self, true ) ) {
        return new WP_Error( 'sz_bq_self_email', 'Это служебный адрес сайта — укажите адрес получателя.', array( 'status' => 400 ) );
    }

    $quota = sad_znaniy_bouquet_mail_quota( get_current_user_id() );
    if ( 0 >= (int) $quota['left'] ) {
        return new WP_Error( 'sz_bq_rate_limited', 'Лимит ' . sad_znaniy_bouquet_mail_limit() . ' писем в час исчерпан. Попробуйте позже.', array( 'status' => 429 ) );
    }

    $occasions  = sad_znaniy_bouquet_occasions();
    $recipients = sad_znaniy_bouquet_recipients();
    $site       = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
    $title      = isset( $bouquet['title'] ) ? (string) $bouquet['title'] : '';

    if ( '' === $title ) {
        $title = sad_znaniy_bouquet_generate_title(
            array(
                'occasion' => $bouquet['occasion'],
                'to'       => $bouquet['to'],
                'items'    => $bouquet['items'],
            )
        );
    }

    $occasion  = isset( $occasions[ $bouquet['occasion'] ] ) ? $occasions[ $bouquet['occasion'] ] : '';
    $recipient = isset( $recipients[ $bouquet['to'] ] ) ? $recipients[ $bouquet['to'] ] : '';
    $summary   = sad_znaniy_bouquet_mail_summary( $bouquet );
    $link      = sad_znaniy_bouquet_share_url( $id );

    $subject = 'Букет «' . $title . '» — ' . $site;
    $lines   = array(
        'Здравствуйте!',
        '',
        'Вам отправили букет «' . $title . '» с сайта ' . $site . '.',
        '',
        'Повод: ' . $occasion,
        'Кому: ' . $recipient,
        'Стеблей: ' . $summary['stems'],
        '',
        'Состав:',
        $summary['text'],
        '',
        'Открыть букет (просмотр): ' . $link,
        '',
        'Значения цветов и уход — на сайте ' . home_url( '/' ) . '.',
        '',
        '— ' . $site . ', ' . home_url( '/' ),
    );

    $sent = wp_mail( $to, $subject, implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
    if ( ! $sent ) {
        return new WP_Error( 'sz_bq_mail_failed', 'Письмо не ушло: сервер не принял отправку. Попробуйте позже.', array( 'status' => 500 ) );
    }

    $log   = (array) $quota['log'];
    $log[] = time();
    update_user_meta( get_current_user_id(), '_sz_plan_mail_log', array_slice( $log, -50 ) );

    return rest_ensure_response(
        array(
            'ok'      => true,
            'sent_to' => $to,
            'left'    => max( 0, (int) $quota['left'] - 1 ),
        )
    );
}