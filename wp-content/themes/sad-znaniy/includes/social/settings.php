<?php
/**
 * Этап 8.5 — Автопостинг: сети, настройки, мастер-выключатель «СТАРТ».
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Сети модуля. `ready` — отправка реализована (по партиям этапа).
 *
 * @return array
 */
function sad_znaniy_social_networks() {
	return array(
		'telegram' => array( 'label' => 'Telegram', 'ready' => true, 'party' => 1 ),
		'max'      => array( 'label' => 'MAX', 'ready' => false, 'party' => 2 ),
		'vk'       => array( 'label' => 'ВКонтакте', 'ready' => false, 'party' => 3 ),
	);
}

/**
 * Сеть реализована?
 *
 * @param string $network Ключ сети.
 * @return bool
 */
function sad_znaniy_social_network_ready( $network ) {
	$all = sad_znaniy_social_networks();
	return ! empty( $all[ $network ]['ready'] );
}

/**
 * Шаблоны постов по умолчанию.
 *
 * @return array
 */
function sad_znaniy_social_templates_default() {
	return array(
		'telegram' => "{emoji} {title}\n\n{excerpt}\n\nЧитать полностью → {link}",
		'max'      => "{emoji} {title}\n\n{excerpt}\n\nЧитать полностью → {link}",
		'vk'       => "{title} {emoji}\n\n{excerpt}\n\nПолный разбор: {link}\n\n{tags}\n{question}",
		'ok'       => "{title} {emoji}\n\n{excerpt}\n\nПодробности: {link}\n\n{tags}",
	);
}

/**
 * Шаблон для сети.
 *
 * @param string $network Ключ сети.
 * @return string
 */
function sad_znaniy_social_template( $network ) {
	$opt = get_option( 'sz_social_templates', array() );
	$def = sad_znaniy_social_templates_default();
	if ( is_array( $opt ) && ! empty( $opt[ $network ] ) ) {
		return (string) $opt[ $network ];
	}
	return isset( $def[ $network ] ) ? $def[ $network ] : $def['telegram'];
}

/**
 * Опция слотов/лимитов/игнора.
 *
 * @return array
 */
function sad_znaniy_social_slots_option() {
	$opt = get_option( 'sz_social_slots', array() );
	return is_array( $opt ) ? $opt : array();
}

/**
 * Слоты дня (строки «HH:MM», по возрастанию).
 *
 * @return string[]
 */
function sad_znaniy_social_slots_list() {
	$opt = sad_znaniy_social_slots_option();
	$t   = isset( $opt['times'] ) && is_array( $opt['times'] ) ? $opt['times'] : array( '09:00', '13:00', '19:00' );
	$out = array();
	foreach ( $t as $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^\d{1,2}:\d{2}$/', $v ) ) {
			list( $h, $m ) = array_map( 'intval', explode( ':', $v ) );
			if ( $h <= 23 && $m <= 59 ) {
				$out[] = sprintf( '%02d:%02d', $h, $m );
			}
		}
	}
	if ( ! $out ) {
		$out = array( '09:00', '13:00', '19:00' );
	}
	sort( $out );
	return $out;
}

/**
 * Лимит постов в день на сеть.
 *
 * @return int
 */
function sad_znaniy_social_daily_limit() {
	$opt = sad_znaniy_social_slots_option();
	$n   = isset( $opt['limit'] ) ? (int) $opt['limit'] : 3;
	return ( $n > 0 && $n <= 50 ) ? $n : 3;
}

/**
 * Игнорируемые категории (слаги).
 *
 * @return string[]
 */
function sad_znaniy_social_ignore_slugs() {
	$opt = sad_znaniy_social_slots_option();
	$out = array();
	$raw = isset( $opt['ignore'] ) ? (string) $opt['ignore'] : '';
	foreach ( preg_split( '/[\s,]+/', $raw ) as $s ) {
		$s = sanitize_title( $s );
		if ( '' !== $s ) {
			$out[] = $s;
		}
	}
	return $out;
}

/**
 * Дефолтные хештеги.
 *
 * @return string
 */
function sad_znaniy_social_default_hashtags() {
	$opt = sad_znaniy_social_slots_option();
	$def = isset( $opt['hashtags'] ) ? trim( (string) $opt['hashtags'] ) : '';
	return '' !== $def ? $def : '#дача #сад #огород #советы';
}

/**
 * Настройки каналов.
 *
 * @return array
 */
function sad_znaniy_social_channels() {
	$opt = get_option( 'sz_social_channels', array() );
	return is_array( $opt ) ? $opt : array();
}

/**
 * Канал включён?
 *
 * @param string $network Ключ сети.
 * @return bool
 */
function sad_znaniy_social_channel_enabled( $network ) {
	$ch = sad_znaniy_social_channels();
	return ! empty( $ch[ $network ]['enabled'] );
}

/**
 * Токен канала.
 *
 * @param string $network Ключ сети.
 * @return string
 */
function sad_znaniy_social_channel_token( $network ) {
	$ch = sad_znaniy_social_channels();
	return isset( $ch[ $network ]['token'] ) ? (string) $ch[ $network ]['token'] : '';
}

/**
 * chat_id / owner_id канала.
 *
 * @param string $network Ключ сети.
 * @return string
 */
function sad_znaniy_social_channel_target( $network ) {
	$ch = sad_znaniy_social_channels();
	return isset( $ch[ $network ]['chat_id'] ) ? (string) $ch[ $network ]['chat_id'] : '';
}

/**
 * Мастер-выключатель: включён ли автопостинг.
 *
 * @return bool
 */
function sad_znaniy_social_is_enabled() {
	return '1' === (string) get_option( 'sz_social_enabled', '0' );
}

/**
 * Момент нажатия «СТАРТ».
 *
 * @return string
 */
function sad_znaniy_social_started_at() {
	return (string) get_option( 'sz_social_started_at', '' );
}

/**
 * Маскирует секрет для показа в админке.
 *
 * @param string $secret Секрет.
 * @return string
 */
function sad_znaniy_social_mask( $secret ) {
	$secret = (string) $secret;
	$len    = mb_strlen( $secret );
	if ( '' === $secret ) {
		return '';
	}
	if ( $len <= 6 ) {
		return str_repeat( '•', $len );
	}
	return str_repeat( '•', 8 ) . mb_substr( $secret, -4 );
}

/**
 * Регистрирует опции модуля.
 */
function sad_znaniy_social_register_settings() {
	register_setting( 'sad_znaniy_social', 'sz_social_channels', array( 'type' => 'array', 'sanitize_callback' => 'sad_znaniy_social_sanitize_channels', 'default' => array() ) );
	register_setting( 'sad_znaniy_social', 'sz_social_templates', array( 'type' => 'array', 'sanitize_callback' => 'sad_znaniy_social_sanitize_templates', 'default' => array() ) );
	register_setting( 'sad_znaniy_social', 'sz_social_slots', array( 'type' => 'array', 'sanitize_callback' => 'sad_znaniy_social_sanitize_slots', 'default' => array() ) );
}
add_action( 'admin_init', 'sad_znaniy_social_register_settings' );

/**
 * Очищает каналы: маскированное значение («•») не затирает секрет, «-» очищает.
 *
 * @param mixed $input Ввод.
 * @return array
 */
function sad_znaniy_social_sanitize_channels( $input ) {
	$input = is_array( $input ) ? $input : array();
	$old   = sad_znaniy_social_channels();
	$out   = array();

	foreach ( array_keys( sad_znaniy_social_networks() ) as $key ) {
		$row      = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
		$old_tok  = isset( $old[ $key ]['token'] ) ? (string) $old[ $key ]['token'] : '';
		$incoming = isset( $row['token'] ) ? trim( (string) $row['token'] ) : '';

		if ( '-' === $incoming ) {
			$token = '';
		} elseif ( '' === $incoming || false !== mb_strpos( $incoming, '•' ) ) {
			$token = $old_tok;
		} else {
			$token = sanitize_text_field( $incoming );
		}

		$out[ $key ] = array(
			'enabled' => empty( $row['enabled'] ) ? 0 : 1,
			'token'   => $token,
			'chat_id' => isset( $row['chat_id'] ) ? trim( sanitize_text_field( $row['chat_id'] ) ) : '',
		);
	}

	return $out;
}

/**
 * Очищает шаблоны.
 *
 * @param mixed $input Ввод.
 * @return array
 */
function sad_znaniy_social_sanitize_templates( $input ) {
	$input = is_array( $input ) ? $input : array();
	$out   = array();
	foreach ( array( 'telegram', 'max', 'vk', 'ok' ) as $key ) {
		$out[ $key ] = isset( $input[ $key ] ) ? sanitize_textarea_field( (string) $input[ $key ] ) : '';
	}
	return $out;
}

/**
 * Очищает слоты, лимит, игнор-категории и хештеги.
 *
 * @param mixed $input Ввод.
 * @return array
 */
function sad_znaniy_social_sanitize_slots( $input ) {
	$input = is_array( $input ) ? $input : array();
	$times = array();
	$raw   = isset( $input['times'] ) ? (array) $input['times'] : array();
	foreach ( $raw as $t ) {
		$t = trim( (string) $t );
		if ( preg_match( '/^\d{1,2}:\d{2}$/', $t ) ) {
			list( $h, $m ) = array_map( 'intval', explode( ':', $t ) );
			if ( $h <= 23 && $m <= 59 ) {
				$times[] = sprintf( '%02d:%02d', $h, $m );
			}
		}
	}
	if ( ! $times ) {
		$times = array( '09:00', '13:00', '19:00' );
	}
	return array(
		'times'    => $times,
		'limit'    => max( 1, min( 50, (int) ( isset( $input['limit'] ) ? $input['limit'] : 3 ) ) ),
		'ignore'   => sanitize_text_field( (string) ( isset( $input['ignore'] ) ? $input['ignore'] : '' ) ),
		'hashtags' => sanitize_text_field( (string) ( isset( $input['hashtags'] ) ? $input['hashtags'] : '' ) ),
	);
}

/**
 * Обработчик «СТАРТ / СТОП / Сдвинуть границу».
 */
function sad_znaniy_social_handle_switch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'sad-znaniy' ) );
	}
	check_admin_referer( 'sz_social_switch' );

	$to  = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
	$msg = $to;

	if ( 'start' === $to ) {
		update_option( 'sz_social_enabled', '1', false );
		if ( '' === sad_znaniy_social_started_at() ) {
			update_option( 'sz_social_started_at', current_time( 'mysql' ), false );
		}
	} elseif ( 'stop' === $to ) {
		update_option( 'sz_social_enabled', '0', false );
	} elseif ( 'reset' === $to ) {
		update_option( 'sz_social_started_at', current_time( 'mysql' ), false );
	}

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'sz-distribution', 'tab' => 'queue', 'sz_msg' => $msg ),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_sz_social_switch', 'sad_znaniy_social_handle_switch' );

