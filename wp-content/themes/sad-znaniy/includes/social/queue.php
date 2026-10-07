<?php
/**
 * Этап 8.5 — Автопостинг: очередь, построение поста, слоты, дедуп, защита.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ближайший слот строго после указанного времени (+ смещение по слотам).
 *
 * @param int $after_ts Метка времени, после которой ищем.
 * @param int $offset   Сколько слотов пропустить (развод задач одного дня).
 * @return string Локальное время «Y-m-d H:i:s».
 */
function sad_znaniy_social_slot_after( $after_ts, $offset = 0 ) {
	$times  = sad_znaniy_social_slots_list();
	$tz     = wp_timezone();
	$after  = ( new DateTimeImmutable( '@' . (int) $after_ts ) )->setTimezone( $tz );
	$cursor = $after;
	$found  = 0;

	for ( $loop = 0; $loop < 60; $loop++ ) {
		$day = $cursor->setTime( 0, 0, 0 );
		foreach ( $times as $t ) {
			list( $h, $m ) = array_map( 'intval', explode( ':', $t ) );
			$slot          = $day->setTime( $h, $m, 0 );
			if ( $slot <= $after ) {
				continue;
			}
			if ( $found === (int) $offset ) {
				return $slot->format( 'Y-m-d H:i:s' );
			}
			$found++;
		}
		$cursor = $cursor->modify( '+1 day' );
	}

	return gmdate( 'Y-m-d H:i:s', (int) $after_ts + HOUR_IN_SECONDS );
}

/**
 * Обрезает текст по границе предложения, иначе по слову.
 *
 * @param string $text  Текст.
 * @param int    $limit Лимит символов.
 * @return string
 */
function sad_znaniy_social_trim_sentence( $text, $limit ) {
	if ( mb_strlen( $text ) <= $limit ) {
		return $text;
	}
	$cut = mb_substr( $text, 0, $limit );
	$pos = max( (int) mb_strrpos( $cut, '.' ), (int) mb_strrpos( $cut, '!' ), (int) mb_strrpos( $cut, '?' ) );
	if ( $pos > (int) ( $limit * 0.4 ) ) {
		return trim( mb_substr( $cut, 0, $pos + 1 ) );
	}
	$sp = mb_strrpos( $cut, ' ' );
	if ( false !== $sp ) {
		$cut = mb_substr( $cut, 0, $sp );
	}
	return rtrim( $cut, ' ,;:—-–' ) . '…';
}

/**
 * Анонс: для Telegram/MAX — до 3 предложений, для VK/OK — до 200 символов.
 *
 * @param int    $post_id ID записи.
 * @param string $network Ключ сети.
 * @return string
 */
function sad_znaniy_social_excerpt( $post_id, $network ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$raw = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : wp_strip_all_tags( (string) $post->post_content );
	$raw = trim( preg_replace( '/\s+/u', ' ', (string) $raw ) );
	if ( '' === $raw ) {
		return '';
	}

	if ( in_array( $network, array( 'vk', 'ok' ), true ) ) {
		return sad_znaniy_social_trim_sentence( $raw, 200 );
	}

	$sentences = preg_split( '/(?<=[.!?])\s+/u', $raw );
	$out       = '';
	foreach ( (array) $sentences as $sentence ) {
		$sentence = trim( (string) $sentence );
		if ( '' === $sentence ) {
			continue;
		}
		$candidate = ( '' === $out ) ? $sentence : $out . ' ' . $sentence;
		if ( mb_strlen( $candidate ) > 420 ) {
			break;
		}
		$out = $candidate;
		if ( preg_match_all( '/[.!?]/u', $out ) >= 3 ) {
			break;
		}
	}

	return '' !== $out ? trim( $out ) : sad_znaniy_social_trim_sentence( $raw, 420 );
}

/**
 * Хештеги: из меток записи, иначе дефолтный набор из настроек.
 *
 * @param int $post_id ID записи.
 * @return string[]
 */
function sad_znaniy_social_hashtags( $post_id ) {
	$out  = array();
	$tags = get_the_tags( $post_id );
	foreach ( (array) ( is_array( $tags ) ? $tags : array() ) as $tag ) {
		$name = preg_replace( '/[^\p{L}\p{N}_]/u', '', (string) $tag->name );
		if ( '' !== $name ) {
			$out[] = '#' . $name;
		}
		if ( count( $out ) >= 6 ) {
			break;
		}
	}

	if ( ! $out ) {
		foreach ( preg_split( '/\s+/', sad_znaniy_social_default_hashtags() ) as $h ) {
			$h = trim( (string) $h );
			if ( '' !== $h ) {
				$out[] = ( '#' === mb_substr( $h, 0, 1 ) ) ? $h : '#' . $h;
			}
		}
	}

	return array_slice( $out, 0, 6 );
}

/**
 * Эмодзи записи (детерминированно по ID).
 *
 * @param int $post_id ID записи.
 * @return string
 */
function sad_znaniy_social_emoji( $post_id ) {
	$set = array( '🌱', '🌿', '🍅', '🥕', '🌸', '🍓', '🥒', '🌻', '🧅', '🌾', '🍎', '🌰' );
	$i   = abs( crc32( 'sz-' . (int) $post_id ) ) % count( $set );
	return $set[ $i ];
}

/**
 * Есть ли в записи ссылка не на свой домен (защита: партнёрские/чужие ссылки не постим).
 *
 * @param int $post_id ID записи.
 * @return bool
 */
function sad_znaniy_social_has_external_links( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || '' === (string) $post->post_content ) {
		return false;
	}

	$home  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$found = preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', (string) $post->post_content, $m );
	if ( ! $found ) {
		return false;
	}

	foreach ( $m[1] as $href ) {
		$href = trim( (string) $href );
		if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'mailto:' ) ) {
			continue;
		}
		if ( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) ) {
			continue;
		}
		$host = (string) wp_parse_url( $href, PHP_URL_HOST );
		if ( '' === $host || ( '' !== $home && strtolower( $host ) === strtolower( $home ) ) ) {
			continue;
		}
		return true;
	}

	return false;
}

/**
 * Готовый payload поста: текст, ссылка с UTM, картинка.
 *
 * @param int    $post_id ID записи.
 * @param string $network Ключ сети.
 * @return array
 */
function sad_znaniy_social_build_payload( $post_id, $network ) {
	$link = add_query_arg(
		array(
			'utm_source'   => $network,
			'utm_medium'   => 'social',
			'utm_campaign' => 'auto',
		),
		(string) get_permalink( $post_id )
	);

	$map = array(
		'{emoji}'    => sad_znaniy_social_emoji( $post_id ),
		'{title}'    => (string) get_the_title( $post_id ),
		'{excerpt}'  => sad_znaniy_social_excerpt( $post_id, $network ),
		'{link}'     => $link,
		'{tags}'     => implode( ' ', sad_znaniy_social_hashtags( $post_id ) ),
		'{question}' => (string) get_post_meta( $post_id, '_sz_social_question', true ),
	);

	// Telegram/MAX: HTML-разметка (заголовок жирным) — экранируем динамические части.
	if ( in_array( $network, array( 'telegram', 'max' ), true ) ) {
		$map['{title}']    = '<b>' . esc_html( (string) get_the_title( $post_id ) ) . '</b>';
		$map['{excerpt}']  = esc_html( (string) $map['{excerpt}'] );
		$map['{question}'] = esc_html( (string) $map['{question}'] );
		$map['{tags}']     = esc_html( (string) $map['{tags}'] );
		$map['{link}']     = str_replace( '&', '&amp;', (string) $map['{link}'] );
	}

	$image = get_the_post_thumbnail_url( $post_id, 'large' );

	return array(
		'post_id' => (int) $post_id,
		'network' => (string) $network,
		'text'    => trim( strtr( sad_znaniy_social_template( $network ), $map ) ),
		'link'    => $link,
		'image'   => $image ? (string) $image : '',
	);
}

/**
 * Пишет задание в очередь.
 *
 * @param int    $post_id   ID записи.
 * @param string $network   Ключ сети.
 * @param array  $payload   Данные поста.
 * @param string $status    Статус (pending / manual).
 * @param string $scheduled Локальное время отправки.
 * @return int ID задания (0 — не записано).
 */
function sad_znaniy_social_insert( $post_id, $network, $payload, $status, $scheduled ) {
	global $wpdb;

	$ok = $wpdb->insert( // phpcs:ignore WordPress.DB
		$wpdb->prefix . 'sz_social_queue',
		array(
			'post_id'      => (int) $post_id,
			'network'      => (string) $network,
			'payload'      => (string) wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ),
			'status'       => (string) $status,
			'attempts'     => 0,
			'scheduled_at' => (string) $scheduled,
			'error'        => '',
			'created'      => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
	);

	return $ok ? (int) $wpdb->insert_id : 0;
}

/**
 * Ставит запись в очередь по всем включённым сетям.
 *
 * @param int  $post_id ID записи.
 * @param bool $force   true — игнорировать мастер-выключатель и границу старта (ручной репост).
 * @return int Сколько заданий добавлено.
 */
function sad_znaniy_social_enqueue_post( $post_id, $force = false, $post = null ) {
	$post_id = (int) $post_id;
	if ( ! $post instanceof WP_Post ) {
		$post = get_post( $post_id );
	}
	if ( ! $post || 'publish' !== $post->post_status ) {
		return 0;
	}
	if ( ! in_array( $post->post_type, array( 'post', 'plant' ), true ) ) {
		return 0;
	}

	if ( ! $force ) {
		if ( ! sad_znaniy_social_is_enabled() ) {
			return 0;
		}
		$started = sad_znaniy_social_started_at();
		if ( '' !== $started ) {
			$published = (int) strtotime( $post->post_date_gmt . ' UTC' );
			$start_ts  = (int) strtotime( $started );
			if ( $published && $start_ts && $published < $start_ts ) {
				return 0;
			}
		}
	}

	$ignore = sad_znaniy_social_ignore_slugs();
	if ( $ignore && has_category( $ignore, $post_id ) ) {
		return 0;
	}

	$status = sad_znaniy_social_has_external_links( $post_id ) ? 'manual' : 'pending';
	$posted = array_filter( array_map( 'strval', (array) get_post_meta( $post_id, '_sz_social_posted', true ) ), 'strlen' );
	$after  = (int) strtotime( $post->post_date_gmt . ' UTC' );
	if ( ! $after ) {
		$after = time();
	}

	$added = 0;
	$skip  = 0;
	foreach ( sad_znaniy_social_networks() as $key => $net ) {
		if ( empty( $net['ready'] ) ) {
			continue;
		}
		if ( in_array( $key, $posted, true ) ) {
			continue;
		}
		if ( ! $force && ! sad_znaniy_social_channel_enabled( $key ) ) {
			continue;
		}
		$scheduled = sad_znaniy_social_slot_after( $after, $skip );
		$skip++;
		if ( sad_znaniy_social_insert( $post_id, $key, sad_znaniy_social_build_payload( $post_id, $key ), $status, $scheduled ) ) {
			$added++;
		}
	}

	return $added;
}

/**
 * Хук публикации: новые статьи и растения попадают в очередь.
 *
 * @param string  $new_status Новый статус.
 * @param string  $old_status Прежний статус.
 * @param WP_Post $post       Запись.
 */
function sad_znaniy_social_on_transition( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	sad_znaniy_social_enqueue_post( $post->ID, false, $post );
}
add_action( 'transition_post_status', 'sad_znaniy_social_on_transition', 20, 3 );

/**
 * Помечает сеть как отправленную (дедуп при повторной публикации).
 *
 * @param int    $post_id ID записи.
 * @param string $network Ключ сети.
 */
function sad_znaniy_social_mark_posted( $post_id, $network ) {
	$posted = array_filter( array_map( 'strval', (array) get_post_meta( $post_id, '_sz_social_posted', true ) ), 'strlen' );
	if ( ! in_array( (string) $network, $posted, true ) ) {
		$posted[] = (string) $network;
	}
	update_post_meta( $post_id, '_sz_social_posted', array_values( array_unique( $posted ) ) );
}

/**
 * Строки очереди с фильтрами.
 *
 * @param array $args status / network / search / limit / offset.
 * @return array
 */
function sad_znaniy_social_queue_rows( $args = array() ) {
	global $wpdb;

	$table  = $wpdb->prefix . 'sz_social_queue';
	$status = isset( $args['status'] ) ? (string) $args['status'] : '';
	$net    = isset( $args['network'] ) ? (string) $args['network'] : '';
	$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
	$limit  = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
	$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

	$where  = 'WHERE 1=1';
	$params = array();

	if ( '' !== $status ) {
		$where   .= ' AND q.status = %s';
		$params[] = $status;
	}
	if ( '' !== $net ) {
		$where   .= ' AND q.network = %s';
		$params[] = $net;
	}
	if ( '' !== $search ) {
		$where   .= " AND q.post_id IN ( SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s )";
		$params[] = '%' . $wpdb->esc_like( $search ) . '%';
	}

	$params[] = $limit;
	$params[] = $offset;

	// phpcs:ignore WordPress.DB.PreparedSQL
	return $wpdb->get_results(
		$wpdb->prepare( "SELECT q.* FROM {$table} q {$where} ORDER BY q.scheduled_at DESC, q.id DESC LIMIT %d OFFSET %d", $params )
	);
}

/**
 * Сколько всего заданий по статусу (для сводки).
 *
 * @return array
 */
function sad_znaniy_social_queue_counts() {
	global $wpdb;
	$table = $wpdb->prefix . 'sz_social_queue';
	$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status" ); // phpcs:ignore WordPress.DB
	$out   = array( 'pending' => 0, 'sent' => 0, 'error' => 0, 'manual' => 0, 'skip' => 0 );
	foreach ( (array) $rows as $r ) {
		$out[ (string) $r->status ] = (int) $r->n;
	}
	return $out;
}

/**
 * Обработчик кнопок очереди: повторить / отменить / отправить сейчас.
 */
function sad_znaniy_social_handle_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'sad-znaniy' ) );
	}
	check_admin_referer( 'sz_social_action' );

	global $wpdb;
	$table = $wpdb->prefix . 'sz_social_queue';
	$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$do    = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	$msg   = '';

	if ( $id > 0 && 'cancel' === $do ) {
		$wpdb->update( $table, array( 'status' => 'skip', 'error' => '' ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$msg = 'cancelled';
	} elseif ( $id > 0 && ( 'repeat' === $do || 'now' === $do ) ) {
		$wpdb->update( // phpcs:ignore WordPress.DB
			$table,
			array(
				'status'       => 'pending',
				'attempts'     => 0,
				'error'        => '',
				'scheduled_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
		$msg = ( 'now' === $do ) ? 'queued_now' : 'queued';
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'sz-distribution', 'tab' => 'queue', 'sz_msg' => $msg ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_sz_social_action', 'sad_znaniy_social_handle_action' );

/**
 * Статистика за N дней: по сетям и по дням (для мини-графика).
 *
 * @param int $days Период в днях.
 * @return array
 */
function sad_znaniy_social_stats( $days = 30 ) {
	global $wpdb;

	$table  = $wpdb->prefix . 'sz_social_queue';
	$days   = max( 1, min( 365, (int) $days ) );
	$from   = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days', strtotime( current_time( 'mysql' ) ) ) );
	$result = array( 'networks' => array(), 'days' => array() );

	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT network, status, COUNT(*) AS n FROM {$table} WHERE created >= %s GROUP BY network, status", $from ) ); // phpcs:ignore WordPress.DB
	foreach ( (array) $rows as $r ) {
		$net = (string) $r->network;
		if ( ! isset( $result['networks'][ $net ] ) ) {
			$result['networks'][ $net ] = array( 'sent' => 0, 'error' => 0, 'pending' => 0, 'manual' => 0 );
		}
		$key = isset( $result['networks'][ $net ][ $r->status ] ) ? (string) $r->status : 'pending';
		$result['networks'][ $net ][ $key ] += (int) $r->n;
	}

	$series = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created) AS d, status, COUNT(*) AS n FROM {$table} WHERE created >= %s GROUP BY DATE(created), status", $from ) ); // phpcs:ignore WordPress.DB
	foreach ( (array) $series as $s ) {
		$day = (string) $s->d;
		if ( ! isset( $result['days'][ $day ] ) ) {
			$result['days'][ $day ] = array( 'sent' => 0, 'error' => 0 );
		}
		if ( 'sent' === (string) $s->status ) {
			$result['days'][ $day ]['sent'] += (int) $s->n;
		} elseif ( 'error' === (string) $s->status ) {
			$result['days'][ $day ]['error'] += (int) $s->n;
		}
	}
	ksort( $result['days'] );

	return $result;
}



