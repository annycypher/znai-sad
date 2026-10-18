<?php
/**
 * Движок конструктора букета (Этап 6.5, Партия 1).
 *
 * Подбор по матрице «повод × кому», конфликты, нечётность, генератор названий,
 * сохранение букета и REST (подбор, сохранение, чтение, PDF-данные).
 *
 * Спека: _design/bouquet-spec.md, §3–§7.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Матрица 11×6: base + модификатор адресата (0–5).
 *
 * @return array
 */
function sad_znaniy_bouquet_matrix() {
    static $matrix = null;
    if ( null !== $matrix ) {
        return $matrix;
    }

    $base   = sad_znaniy_bouquet_matrix_base();
    $mods   = sad_znaniy_bouquet_matrix_modifiers();
    $cats   = array( 'love', 'friendship', 'respect', 'new', 'memory' );
    $matrix = array();

    foreach ( $base as $occasion => $vector ) {
        foreach ( $mods as $to => $modifier ) {
            $cell = array();
            foreach ( $cats as $cat ) {
                $value        = (int) $vector[ $cat ] + (int) $modifier[ $cat ];
                $cell[ $cat ] = max( 0, min( 5, $value ) );
            }
            $matrix[ $occasion ][ $to ] = $cell;
        }
    }

    return $matrix;
}

/**
 * Вектор ячейки «повод × кому» (нули, если ключи не заданы).
 *
 * @param string $occasion Повод.
 * @param string $to       Адресат.
 * @return array
 */
function sad_znaniy_bouquet_cell( $occasion, $to ) {
    $matrix = sad_znaniy_bouquet_matrix();
    if ( isset( $matrix[ $occasion ][ $to ] ) ) {
        return $matrix[ $occasion ][ $to ];
    }
    return array(
        'love'       => 0,
        'friendship' => 0,
        'respect'    => 0,
        'new'        => 0,
        'memory'     => 0,
    );
}
/**
 * Нормализует запись цветка для клиента.
 *
 * @param WP_Post $post Запись.
 * @return array
 */
function sad_znaniy_bouquet_flower_data( $post ) {
    $id       = (int) $post->ID;
    $title    = (string) get_the_title( $id );
    $cats     = array_filter( explode( ',', (string) get_post_meta( $id, '_sz_fl_category', true ) ) );
    $season   = array_filter( explode( ',', (string) get_post_meta( $id, '_sz_fl_season', true ) ) );
    $bad      = array_filter( explode( ',', (string) get_post_meta( $id, '_sz_fl_bad', true ) ) );
    $plant_id = (int) get_post_meta( $id, '_sz_fl_plant', true );
    $photo    = function_exists( 'sad_znaniy_flower_photo_url' ) ? sad_znaniy_flower_photo_url( $id ) : '';

    return array(
        'id'        => $id,
        'slug'      => (string) $post->post_name,
        'title'     => $title,
        'meaning'   => (string) get_post_meta( $id, '_sz_fl_meaning', true ),
        'desc'      => (string) get_post_meta( $id, '_sz_fl_desc', true ),
        'cats'      => array_values( array_map( 'sanitize_key', $cats ) ),
        'rare'      => '1' === (string) get_post_meta( $id, '_sz_fl_rare', true ),
        'season'    => array_values( array_map( 'absint', $season ) ),
        'plant'     => $plant_id,
        'plant_url' => $plant_id ? (string) get_permalink( $plant_id ) : '',
        'bad'       => array_values( array_map( 'sanitize_title', $bad ) ),
        'warn'      => (string) get_post_meta( $id, '_sz_fl_warn', true ),
        'variant'   => (string) get_post_meta( $id, '_sz_fl_variant_of', true ),
        'genitive'  => (string) get_post_meta( $id, '_sz_fl_genitive', true ),
        'sort'      => (int) get_post_meta( $id, '_sz_fl_sort', true ),
        'photo'     => $photo,
        'initial'   => mb_strtoupper( mb_substr( $title, 0, 1, 'UTF-8' ), 'UTF-8' ),
        'url'       => (string) get_permalink( $id ),
    );
}

/**
 * Все опубликованные цветы (slug => данные), по `_sz_fl_sort`.
 *
 * @return array
 */
function sad_znaniy_bouquet_get_flowers() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }

    $ids = get_posts(
        array(
            'post_type'      => 'flower',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        )
    );

    $out = array();
    foreach ( $ids as $id ) {
        $post = get_post( $id );
        if ( $post ) {
            $out[ $post->post_name ] = sad_znaniy_bouquet_flower_data( $post );
        }
    }

    uasort(
        $out,
        function ( $a, $b ) {
            if ( (int) $a['sort'] === (int) $b['sort'] ) {
                return strcasecmp( (string) $a['title'], (string) $b['title'] );
            }
            return (int) $a['sort'] <=> (int) $b['sort'];
        }
    );

    $cache = $out;
    return $cache;
}

/**
 * Цветок по слагу.
 *
 * @param string $slug Слаг.
 * @return array|null
 */
function sad_znaniy_bouquet_flower( $slug ) {
    $all = sad_znaniy_bouquet_get_flowers();
    return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
}

/**
 * Балл цветка для ячейки: primary×2 + secondary.
 *
 * @param array $flower Данные цветка.
 * @param array $cell   Вектор категорий.
 * @return int
 */
function sad_znaniy_bouquet_score( $flower, $cell ) {
    $weights = array();
    foreach ( (array) $flower['cats'] as $cat ) {
        if ( 'filler' === $cat ) {
            continue;
        }
        $weights[] = isset( $cell[ $cat ] ) ? (int) $cell[ $cat ] : 0;
    }
    if ( ! $weights ) {
        return 0;
    }
    rsort( $weights );
    $primary   = (int) $weights[0];
    $secondary = isset( $weights[1] ) ? (int) $weights[1] : 0;
    if ( $secondary < 3 ) {
        $secondary = 0;
    }
    return $primary * 2 + $secondary;
}
/**
 * Считает стебли по слагам из смешанного списка (slug или [slug,count]).
 *
 * @param array $items Список.
 * @return array slug => count.
 */
function sad_znaniy_bouquet_item_counts( $items ) {
    $out = array();
    foreach ( (array) $items as $key => $item ) {
        if ( is_string( $key ) && ! is_numeric( $key ) && is_numeric( $item ) ) {
            $slug  = sanitize_title( $key );
            $count = max( 1, (int) $item );
            if ( '' !== $slug ) {
                $out[ $slug ] = isset( $out[ $slug ] ) ? $out[ $slug ] + $count : $count;
            }
            continue;
        }
        if ( is_array( $item ) ) {
            $slug  = isset( $item['slug'] ) ? sanitize_title( (string) $item['slug'] ) : '';
            $count = isset( $item['count'] ) ? max( 1, (int) $item['count'] ) : 1;
        } else {
            $slug  = sanitize_title( (string) $item );
            $count = 1;
        }
        if ( '' === $slug ) {
            continue;
        }
        $out[ $slug ] = isset( $out[ $slug ] ) ? $out[ $slug ] + $count : $count;
    }
    return $out;
}

/**
 * Проверяет правило конфликта.
 *
 * @param array  $rule     Правило.
 * @param array  $counts   slug => count.
 * @param int    $total    Всего стеблей.
 * @param string $occasion Повод.
 * @return bool
 */
function sad_znaniy_bouquet_rule_matches( $rule, $counts, $total, $occasion ) {
    $scope = isset( $rule['scope'] ) ? (string) $rule['scope'] : 'always';

    if ( 'share' === $scope ) {
        if ( ! in_array( $occasion, (array) $rule['occasions'], true ) ) {
            return false;
        }
        $sum = 0;
        foreach ( (array) $rule['group'] as $slug ) {
            if ( isset( $counts[ $slug ] ) ) {
                $sum += (int) $counts[ $slug ];
            }
        }
        return $total > 0 && ( $sum / $total ) * 100 > (int) $rule['percent'];
    }

    if ( 0 === strpos( $scope, 'occasion:' ) ) {
        $list = explode( ',', substr( $scope, 9 ) );
        if ( ! in_array( $occasion, $list, true ) ) {
            return false;
        }
    }

    $has_a = false;
    foreach ( (array) $rule['a'] as $slug ) {
        if ( isset( $counts[ $slug ] ) ) {
            $has_a = true;
            break;
        }
    }
    if ( ! $has_a ) {
        return false;
    }

    $b = (array) $rule['b'];
    if ( ! $b ) {
        return true;
    }
    foreach ( $b as $slug ) {
        if ( isset( $counts[ $slug ] ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Конфликты композиции (до 3, hard выше soft).
 *
 * @param array  $items    Список.
 * @param string $occasion Повод.
 * @return array
 */
function sad_znaniy_bouquet_check_conflicts( $items, $occasion = '' ) {
    $counts = sad_znaniy_bouquet_item_counts( $items );
    $total  = array_sum( $counts );
    $found  = array();

    foreach ( sad_znaniy_bouquet_conflicts() as $rule ) {
        if ( ! sad_znaniy_bouquet_rule_matches( $rule, $counts, $total, $occasion ) ) {
            continue;
        }
        $slugs = array_merge(
            (array) $rule['a'],
            (array) $rule['b'],
            isset( $rule['group'] ) ? (array) $rule['group'] : array()
        );
        $found[] = array(
            'id'    => (string) $rule['id'],
            'type'  => (string) $rule['type'],
            'text'  => (string) $rule['text'],
            'slugs' => array_values( array_unique( $slugs ) ),
        );
    }

    usort(
        $found,
        function ( $a, $b ) {
            $order = array( 'hard' => 0, 'soft' => 1 );
            return (int) $order[ $a['type'] ] <=> (int) $order[ $b['type'] ];
        }
    );

    return array_slice( $found, 0, 3 );
}

/**
 * Конфликт, который вызовет добавление цветка.
 *
 * @param string $slug     Кандидат.
 * @param array  $counts   Текущие стебли.
 * @param string $occasion Повод.
 * @return array|null
 */
function sad_znaniy_bouquet_candidate_conflict( $slug, $counts, $occasion ) {
    $next = $counts;
    $next[ $slug ] = isset( $next[ $slug ] ) ? $next[ $slug ] + 1 : 1;
    $total = array_sum( $next );

    foreach ( sad_znaniy_bouquet_conflicts() as $rule ) {
        if ( ! sad_znaniy_bouquet_rule_matches( $rule, $next, $total, $occasion ) ) {
            continue;
        }
        $slugs = array_merge(
            (array) $rule['a'],
            (array) $rule['b'],
            isset( $rule['group'] ) ? (array) $rule['group'] : array()
        );
        if ( in_array( $slug, $slugs, true ) ) {
            return array(
                'id'    => (string) $rule['id'],
                'type'  => (string) $rule['type'],
                'text'  => (string) $rule['text'],
                'slugs' => array_values( array_unique( $slugs ) ),
            );
        }
    }
    return null;
}

/**
 * Нечётность и режим солитёра.
 *
 * @param array $items Список.
 * @return array
 */
function sad_znaniy_bouquet_oddness( $items ) {
    $counts = sad_znaniy_bouquet_item_counts( $items );
    $total  = array_sum( $counts );
    $hints  = sad_znaniy_bouquet_hints();

    if ( $total < 1 ) {
        return array( 'count' => 0, 'state' => 'empty', 'hint' => $hints['empty'], 'solo' => false );
    }
    if ( $total > 25 ) {
        return array( 'count' => $total, 'state' => 'limit', 'hint' => $hints['limit'], 'solo' => false );
    }
    if ( 1 === $total ) {
        $slug   = (string) array_key_first( $counts );
        $flower = sad_znaniy_bouquet_flower( $slug );
        $name   = $flower ? (string) $flower['title'] : '';
        return array( 'count' => 1, 'state' => 'solo', 'hint' => str_replace( '{flower}', $name, $hints['solo'] ), 'solo' => true );
    }
    if ( 2 === $total ) {
        return array( 'count' => 2, 'state' => 'even', 'hint' => $hints['even'], 'solo' => false );
    }
    if ( $total >= 10 ) {
        return array( 'count' => $total, 'state' => 'large', 'hint' => $hints['large'], 'solo' => false );
    }
    return array( 'count' => $total, 'state' => 'ok', 'hint' => str_replace( '{n}', (string) $total, $hints['ok'] ), 'solo' => false );
}
/**
 * Генерирует название букета.
 *
 * @param array $args occasion, to, items, reroll.
 * @return string
 */
function sad_znaniy_bouquet_generate_title( $args ) {
    $args = wp_parse_args(
        (array) $args,
        array(
            'occasion' => '',
            'to'       => '',
            'items'    => array(),
            'reroll'   => 0,
        )
    );

    $counts  = sad_znaniy_bouquet_item_counts( $args['items'] );
    $total   = array_sum( $counts );
    $solo    = ( 1 === $total );
    $flowers = sad_znaniy_bouquet_get_flowers();
    $cell    = sad_znaniy_bouquet_cell( (string) $args['occasion'], (string) $args['to'] );

    $top       = null;
    $top_score = -1;
    foreach ( $counts as $slug => $count ) {
        if ( isset( $flowers[ $slug ] ) ) {
            $score = sad_znaniy_bouquet_score( $flowers[ $slug ], $cell );
            if ( $score > $top_score ) {
                $top_score = $score;
                $top       = $flowers[ $slug ];
            }
        }
    }

    $flower_name = $top ? (string) $top['title'] : '';
    $flower_gen  = ( $top && '' !== (string) $top['genitive'] ) ? (string) $top['genitive'] : $flower_name;

    if ( $solo && '' !== $flower_name ) {
        return ( ( (int) $args['reroll'] ) % 2 ) ? ( 'Один ' . $flower_name ) : ( 'Соло ' . $flower_gen );
    }

    $dominant = ( $top && ! empty( $top['cats'] ) ) ? (string) $top['cats'][0] : 'friendship';
    $matches  = array();

    foreach ( sad_znaniy_bouquet_name_templates() as $template ) {
        if ( ! empty( $template['solo'] ) ) {
            continue;
        }
        if ( ! empty( $template['occasions'] ) && ! in_array( (string) $args['occasion'], (array) $template['occasions'], true ) ) {
            continue;
        }
        if ( ! empty( $template['to'] ) && ! in_array( (string) $args['to'], (array) $template['to'], true ) ) {
            continue;
        }
        if ( ! empty( $template['category'] ) && (string) $template['category'] !== $dominant ) {
            continue;
        }
        $matches[] = $template;
    }

    if ( ! $matches ) {
        return 'Букет';
    }

    $max = 0;
    foreach ( $matches as $match ) {
        $max = max( $max, (int) $match['priority'] );
    }
    $pool = array();
    foreach ( $matches as $match ) {
        if ( (int) $match['priority'] >= $max - 10 ) {
            $pool[] = $match;
        }
    }
    if ( ! $pool ) {
        $pool = $matches;
    }

    $seed     = crc32( implode( ',', array_keys( $counts ) ) . '|' . implode( ',', $counts ) . '|' . (string) $args['occasion'] . '|' . (string) $args['to'] . '|' . (int) $args['reroll'] );
    $template = $pool[ $seed % count( $pool ) ];

    $parts   = sad_znaniy_bouquet_name_parts();
    $epithet = $parts['epithets'][ $seed % count( $parts['epithets'] ) ];
    $feeling = $parts['feelings'][ $seed % count( $parts['feelings'] ) ];
    $word    = $parts['words'][ $seed % count( $parts['words'] ) ];

    $title = str_replace(
        array( '{Эпитет}', '{цветка}', '{Цветок}', '{чувство}', '{Слово}' ),
        array( $epithet, $flower_gen, $flower_name, $feeling, $word ),
        (string) $template['template']
    );
    $title = trim( (string) preg_replace( '/[ ]+/u', ' ', $title ) );
    return mb_substr( $title, 0, 60, 'UTF-8' );
}

/**
 * Подбор цветов под повод и адресата.
 *
 * @param string $occasion Повод.
 * @param string $to       Адресат.
 * @param array  $items    Текущая композиция.
 * @return array
 */
function sad_znaniy_bouquet_recommend( $occasion = '', $to = '', $items = array() ) {
    $occasions  = sad_znaniy_bouquet_occasions();
    $recipients = sad_znaniy_bouquet_recipients();
    $occasion   = isset( $occasions[ $occasion ] ) ? (string) $occasion : '';
    $to         = isset( $recipients[ $to ] ) ? (string) $to : '';

    $cell    = sad_znaniy_bouquet_cell( $occasion, $to );
    $counts  = sad_znaniy_bouquet_item_counts( $items );
    $flowers = sad_znaniy_bouquet_get_flowers();
    $list    = array();

    foreach ( $flowers as $slug => $flower ) {
        if ( '' !== (string) $flower['variant'] ) {
            continue;
        }
        $score    = sad_znaniy_bouquet_score( $flower, $cell );
        $conflict = ( '' !== $occasion || $counts ) ? sad_znaniy_bouquet_candidate_conflict( $slug, $counts, $occasion ) : null;

        if ( $conflict && 'hard' === $conflict['type'] ) {
            $score = -1;
        } elseif ( $conflict ) {
            $score -= 4;
        }

        $flower['score']    = $score;
        $flower['conflict'] = $conflict;
        $list[]             = $flower;
    }

    usort(
        $list,
        function ( $a, $b ) {
            if ( (int) $a['score'] === (int) $b['score'] ) {
                if ( (int) $a['sort'] === (int) $b['sort'] ) {
                    return strcasecmp( (string) $a['title'], (string) $b['title'] );
                }
                return (int) $a['sort'] <=> (int) $b['sort'];
            }
            return (int) $b['score'] <=> (int) $a['score'];
        }
    );

    $recommended = array();
    foreach ( $list as $flower ) {
        if ( count( $recommended ) >= 8 ) {
            break;
        }
        if ( (int) $flower['score'] <= 0 ) {
            continue;
        }
        if ( $flower['conflict'] && 'hard' === $flower['conflict']['type'] ) {
            continue;
        }
        $recommended[] = (string) $flower['slug'];
    }

    return array(
        'occasion'    => $occasion,
        'to'          => $to,
        'cell'        => $cell,
        'recommended' => $recommended,
        'flowers'     => $list,
        'conflicts'   => $counts ? sad_znaniy_bouquet_check_conflicts( $counts, $occasion ) : array(),
        'oddness'     => $counts ? sad_znaniy_bouquet_oddness( $counts ) : null,
    );
}
/**
 * Лимиты хранения букетов.
 *
 * @return array
 */
function sad_znaniy_bouquet_limits() {
    return apply_filters(
        'sad_znaniy_bouquet_limits',
        array(
            'bouquets' => 20,
            'bytes'    => 30720,
            'items'    => 40,
            'title'    => 120,
            'stems'    => 25,
            'version'  => 1,
        )
    );
}

/**
 * Новый UUID букета: 32 hex-символа.
 *
 * @return string
 */
function sad_znaniy_bouquet_new_id() {
    return bin2hex( random_bytes( 16 ) );
}

/**
 * Корректный ли UUID.
 *
 * @param string $id Идентификатор.
 * @return bool
 */
function sad_znaniy_bouquet_is_id( $id ) {
    return (bool) preg_match( '/^[a-f0-9]{32}$/', (string) $id );
}

/**
 * Ключ меты букета.
 *
 * @param string $id UUID.
 * @return string
 */
function sad_znaniy_bouquet_meta_key( $id ) {
    return '_sz_bouquet_' . $id;
}

/**
 * Индекс букетов пользователя.
 *
 * @param int $user_id Пользователь.
 * @return array
 */
function sad_znaniy_bouquet_index( $user_id ) {
    $idx = get_user_meta( (int) $user_id, '_sz_bouquets', true );
    return is_array( $idx ) ? $idx : array();
}

/**
 * URL страницы конструктора.
 *
 * @return string
 */
function sad_znaniy_bouquet_page_url() {
    $page = get_page_by_path( 'konstruktor-buketa' );
    return $page ? (string) get_permalink( $page ) : home_url( '/konstruktor-buketa/' );
}

/**
 * Публичная ссылка на букет (read-only).
 *
 * @param string $id UUID.
 * @return string
 */
function sad_znaniy_bouquet_share_url( $id ) {
    return add_query_arg( 'bouquet', $id, sad_znaniy_bouquet_page_url() );
}

/**
 * Свой букет по UUID (строгая проверка владельца).
 *
 * @param string $id      UUID.
 * @param int    $user_id Пользователь.
 * @return array|null
 */
function sad_znaniy_bouquet_get_own( $id, $user_id ) {
    if ( ! sad_znaniy_bouquet_is_id( $id ) ) {
        return null;
    }
    $raw = get_user_meta( (int) $user_id, sad_znaniy_bouquet_meta_key( $id ), true );
    if ( ! is_string( $raw ) || '' === $raw ) {
        return null;
    }
    $bouquet = json_decode( $raw, true );
    if ( ! is_array( $bouquet ) || (int) ( isset( $bouquet['owner'] ) ? $bouquet['owner'] : 0 ) !== (int) $user_id ) {
        return null;
    }
    $bouquet['id'] = (string) $id;
    return $bouquet;
}

/**
 * Удаление своего букета.
 *
 * @param string $id      UUID.
 * @param int    $user_id Владелец.
 * @return bool
 */
function sad_znaniy_bouquet_delete( $id, $user_id ) {
    if ( null === sad_znaniy_bouquet_get_own( $id, $user_id ) ) {
        return false;
    }
    delete_user_meta( (int) $user_id, sad_znaniy_bouquet_meta_key( $id ) );
    $index = array();
    foreach ( sad_znaniy_bouquet_index( $user_id ) as $row ) {
        if ( isset( $row['id'] ) && (string) $row['id'] === (string) $id ) {
            continue;
        }
        $index[] = $row;
    }
    update_user_meta( (int) $user_id, '_sz_bouquets', $index );
    return true;
}

/**
 * Публичное чтение по UUID (владелец не отдаётся).
 *
 * @param string $id UUID.
 * @return array|null
 */
function sad_znaniy_bouquet_get_public( $id ) {
    if ( ! sad_znaniy_bouquet_is_id( $id ) ) {
        return null;
    }
    global $wpdb;
    $key = sad_znaniy_bouquet_meta_key( $id );
    $raw = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM ' . $wpdb->usermeta . ' WHERE meta_key = %s LIMIT 1', $key ) );
    if ( ! is_string( $raw ) || '' === $raw ) {
        return null;
    }
    $bouquet = json_decode( $raw, true );
    if ( ! is_array( $bouquet ) || ! isset( $bouquet['items'] ) ) {
        return null;
    }
    $bouquet['id'] = (string) $id;
    unset( $bouquet['owner'] );
    return $bouquet;
}
/**
 * Валидация и нормализация букета (белый список, диапазоны, лимиты).
 *
 * @param mixed $raw   Данные букета.
 * @param int   $owner Владелец.
 * @return array|WP_Error
 */
function sad_znaniy_bouquet_normalize( $raw, $owner ) {
    $limits = sad_znaniy_bouquet_limits();
    if ( ! is_array( $raw ) ) {
        return new WP_Error( 'sz_bq_schema', 'Некорректные данные букета.' );
    }

    $occasions  = sad_znaniy_bouquet_occasions();
    $recipients = sad_znaniy_bouquet_recipients();
    $flowers    = sad_znaniy_bouquet_get_flowers();

    $occasion = isset( $raw['occasion'] ) ? sanitize_key( (string) $raw['occasion'] ) : '';
    if ( ! isset( $occasions[ $occasion ] ) ) {
        $occasion = '';
    }
    $to = isset( $raw['to'] ) ? sanitize_key( (string) $raw['to'] ) : '';
    if ( ! isset( $recipients[ $to ] ) ) {
        $to = '';
    }

    $items_raw = ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) ? $raw['items'] : array();
    $items     = array();
    $stems     = 0;

    foreach ( $items_raw as $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }
        $slug = isset( $item['slug'] ) ? sanitize_title( (string) $item['slug'] ) : '';
        if ( '' === $slug || ! isset( $flowers[ $slug ] ) ) {
            continue;
        }
        $count = isset( $item['count'] ) ? (int) $item['count'] : 1;
        $count = max( 1, min( (int) $limits['stems'], $count ) );
        if ( isset( $items[ $slug ] ) ) {
            $items[ $slug ]['count'] += $count;
        } else {
            $items[ $slug ] = array( 'slug' => $slug, 'count' => $count );
        }
        $stems += $count;
        if ( count( $items ) >= (int) $limits['items'] ) {
            break;
        }
    }

    $items = array_values( $items );
    $stems = 0;
    foreach ( $items as $index => $item ) {
        $left = (int) $limits['stems'] - $stems;
        if ( (int) $item['count'] > $left ) {
            $items[ $index ]['count'] = max( 0, $left );
        }
        $stems += (int) $items[ $index ]['count'];
    }
    $items = array_values(
        array_filter(
            $items,
            function ( $item ) {
                return (int) $item['count'] > 0;
            }
        )
    );

    $title = isset( $raw['title'] ) ? sanitize_text_field( (string) $raw['title'] ) : '';
    $title = mb_substr( $title, 0, (int) $limits['title'], 'UTF-8' );

    $id  = ( isset( $raw['id'] ) && sad_znaniy_bouquet_is_id( $raw['id'] ) ) ? (string) $raw['id'] : sad_znaniy_bouquet_new_id();
    $now = current_time( 'mysql' );

    return array(
        'version'   => (int) $limits['version'],
        'id'        => $id,
        'title'     => $title,
        'generated' => isset( $raw['generated'] ) ? (bool) $raw['generated'] : true,
        'occasion'  => $occasion,
        'to'        => $to,
        'solo'      => ( 1 === $stems ),
        'items'     => $items,
        'reroll'    => isset( $raw['reroll'] ) ? max( 0, (int) $raw['reroll'] ) : 0,
        'created'   => $now,
        'updated'   => $now,
        'owner'     => (int) $owner,
    );
}

/**
 * Сохранение букета в кабинет.
 *
 * @param mixed $raw     Данные.
 * @param int   $user_id Владелец.
 * @return array|WP_Error
 */
function sad_znaniy_bouquet_save( $raw, $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id <= 0 ) {
        return new WP_Error( 'sz_bq_auth', 'Нужен вход в аккаунт.' );
    }

    $limits = sad_znaniy_bouquet_limits();
    if ( is_string( $raw ) && strlen( $raw ) > (int) $limits['bytes'] ) {
        return new WP_Error( 'sz_bq_size', 'Букет слишком большой.' );
    }
    if ( is_array( $raw ) && strlen( (string) wp_json_encode( $raw ) ) > (int) $limits['bytes'] ) {
        return new WP_Error( 'sz_bq_size', 'Букет слишком большой.' );
    }

    $bouquet = sad_znaniy_bouquet_normalize( $raw, $user_id );
    if ( is_wp_error( $bouquet ) ) {
        return $bouquet;
    }

    $id    = (string) $bouquet['id'];
    $index = sad_znaniy_bouquet_index( $user_id );
    $known = false;
    foreach ( $index as $row ) {
        if ( isset( $row['id'] ) && (string) $row['id'] === $id ) {
            $known = true;
            break;
        }
    }
    if ( $known && null === sad_znaniy_bouquet_get_own( $id, $user_id ) ) {
        return new WP_Error( 'sz_bq_owner', 'Этот букет вам не принадлежит.' );
    }
    if ( ! $known && count( $index ) >= (int) $limits['bouquets'] ) {
        return new WP_Error( 'sz_bq_limit', 'Достигнут лимит букетов — удалите ненужные.' );
    }

    if ( $known ) {
        $prev = sad_znaniy_bouquet_get_own( $id, $user_id );
        if ( $prev && ! empty( $prev['created'] ) ) {
            $bouquet['created'] = (string) $prev['created'];
        }
    }

    $json = (string) wp_json_encode( $bouquet );
    if ( strlen( $json ) > (int) $limits['bytes'] ) {
        return new WP_Error( 'sz_bq_size', 'Букет слишком большой.' );
    }
    update_user_meta( $user_id, sad_znaniy_bouquet_meta_key( $id ), wp_slash( $json ) );

    $stems = 0;
    foreach ( $bouquet['items'] as $item ) {
        $stems += (int) $item['count'];
    }
    $row = array(
        'id'       => $id,
        'title'    => $bouquet['title'],
        'occasion' => $bouquet['occasion'],
        'to'       => $bouquet['to'],
        'stems'    => $stems,
        'solo'     => (bool) $bouquet['solo'],
        'created'  => $bouquet['created'],
        'updated'  => $bouquet['updated'],
    );

    $new_index = array();
    foreach ( $index as $item ) {
        if ( isset( $item['id'] ) && (string) $item['id'] === $id ) {
            continue;
        }
        $new_index[] = $item;
    }
    array_unshift( $new_index, $row );
    update_user_meta( $user_id, '_sz_bouquets', array_slice( $new_index, 0, (int) $limits['bouquets'] ) );

    return array(
        'bouquet' => $bouquet,
        'row'     => $row,
        'url'     => sad_znaniy_bouquet_share_url( $id ),
    );
}

/**
 * Список букетов пользователя.
 *
 * @param int $user_id Пользователь.
 * @return array
 */
function sad_znaniy_bouquet_my_list( $user_id ) {
    $out = array();
    foreach ( sad_znaniy_bouquet_index( $user_id ) as $row ) {
        if ( empty( $row['id'] ) || ! sad_znaniy_bouquet_is_id( $row['id'] ) ) {
            continue;
        }
        $out[] = array(
            'id'       => (string) $row['id'],
            'title'    => isset( $row['title'] ) ? (string) $row['title'] : 'Мой букет',
            'occasion' => isset( $row['occasion'] ) ? (string) $row['occasion'] : '',
            'to'       => isset( $row['to'] ) ? (string) $row['to'] : '',
            'stems'    => isset( $row['stems'] ) ? (int) $row['stems'] : 0,
            'solo'     => ! empty( $row['solo'] ),
            'created'  => isset( $row['created'] ) ? (string) $row['created'] : '',
            'updated'  => isset( $row['updated'] ) ? (string) $row['updated'] : '',
            'edit'     => add_query_arg( 'bouquet', $row['id'], sad_znaniy_bouquet_page_url() ),
            'share'    => sad_znaniy_bouquet_share_url( (string) $row['id'] ),
        );
    }
    return $out;
}
/* REST section follows. */

/**
 * Доступ к приватным маршрутам букета.
 *
 * @return bool
 */
function sad_znaniy_bouquet_rest_can() {
    return is_user_logged_in();
}

/**
 * Регистрация маршрутов конструктора.
 *
 * ID передаётся параметром запроса (view/delete/pdf), а не сегментом пути:
 * так маршрут не содержит символа «больше» и проходит транспорт без ложных
 * срабатываний на перенаправление вывода.
 */
function sad_znaniy_bouquet_rest_routes() {
    register_rest_route(
        'sad-znaniy/v1',
        '/bouquets/recommend',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'sad_znaniy_bouquet_rest_recommend',
                'permission_callback' => '__return_true',
            ),
        )
    );

    register_rest_route(
        'sad-znaniy/v1',
        '/bouquets',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'sad_znaniy_bouquet_rest_list',
                'permission_callback' => 'sad_znaniy_bouquet_rest_can',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => 'sad_znaniy_bouquet_rest_save',
                'permission_callback' => 'sad_znaniy_bouquet_rest_can',
            ),
        )
    );

    register_rest_route(
        'sad-znaniy/v1',
        '/bouquets/view',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'sad_znaniy_bouquet_rest_get',
                'permission_callback' => '__return_true',
            ),
        )
    );

    register_rest_route(
        'sad-znaniy/v1',
        '/bouquets/delete',
        array(
            array(
                'methods'             => 'DELETE',
                'callback'            => 'sad_znaniy_bouquet_rest_delete',
                'permission_callback' => 'sad_znaniy_bouquet_rest_can',
            ),
        )
    );

    register_rest_route(
        'sad-znaniy/v1',
        '/bouquets/pdf',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'sad_znaniy_bouquet_rest_pdf',
                'permission_callback' => 'sad_znaniy_bouquet_rest_can',
            ),
        )
    );
}
add_action( 'rest_api_init', 'sad_znaniy_bouquet_rest_routes' );
/**
 * GET /bouquets/recommend — подбор.
 *
 * @param WP_REST_Request $req Запрос.
 * @return WP_REST_Response
 */
function sad_znaniy_bouquet_rest_recommend( $req ) {
    $occasion = (string) $req->get_param( 'occasion' );
    $to       = (string) $req->get_param( 'to' );
    $items    = $req->get_param( 'items' );
    if ( is_string( $items ) ) {
        $items = array_filter( array_map( 'trim', explode( ',', $items ) ) );
    }
    return rest_ensure_response( sad_znaniy_bouquet_recommend( $occasion, $to, (array) $items ) );
}

/**
 * GET /bouquets — мои букеты.
 *
 * @return WP_REST_Response
 */
function sad_znaniy_bouquet_rest_list() {
    return rest_ensure_response(
        array(
            'items' => sad_znaniy_bouquet_my_list( get_current_user_id() ),
            'limit' => (int) sad_znaniy_bouquet_limits()['bouquets'],
        )
    );
}

/**
 * POST /bouquets — сохранить букет.
 *
 * @param WP_REST_Request $req Запрос.
 * @return WP_REST_Response|WP_Error
 */
function sad_znaniy_bouquet_rest_save( $req ) {
    $params = $req->get_json_params();
    if ( ! is_array( $params ) || ! $params ) {
        $params = $req->get_params();
    }
    $res = sad_znaniy_bouquet_save( $params, get_current_user_id() );
    if ( is_wp_error( $res ) ) {
        return $res;
    }
    return rest_ensure_response( $res );
}

/**
 * GET /bouquets/view — публичное чтение по параметру id.
 *
 * @param WP_REST_Request $req Запрос.
 * @return WP_REST_Response|WP_Error
 */
function sad_znaniy_bouquet_rest_get( $req ) {
    $bouquet = sad_znaniy_bouquet_get_public( (string) $req->get_param( 'id' ) );
    if ( ! $bouquet ) {
        return new WP_Error( 'sz_bq_notfound', 'Букет не найден.', array( 'status' => 404 ) );
    }
    return rest_ensure_response( $bouquet );
}

/**
 * DELETE /bouquets/delete — удалить свой букет.
 *
 * @param WP_REST_Request $req Запрос.
 * @return WP_REST_Response|WP_Error
 */
function sad_znaniy_bouquet_rest_delete( $req ) {
    $id = (string) $req->get_param( 'id' );
    if ( ! sad_znaniy_bouquet_delete( $id, get_current_user_id() ) ) {
        return new WP_Error( 'sz_bq_notfound', 'Букет не найден или не принадлежит вам.', array( 'status' => 404 ) );
    }
    return rest_ensure_response( array( 'ok' => true ) );
}

/**
 * GET /bouquets/pdf — данные для PDF (только владелец).
 *
 * @param WP_REST_Request $req Запрос.
 * @return WP_REST_Response|WP_Error
 */
function sad_znaniy_bouquet_rest_pdf( $req ) {
    $id      = (string) $req->get_param( 'id' );
    $bouquet = sad_znaniy_bouquet_get_own( $id, get_current_user_id() );
    if ( ! $bouquet ) {
        return new WP_Error( 'sz_bq_notfound', 'Букет не найден.', array( 'status' => 404 ) );
    }

    $occasions  = sad_znaniy_bouquet_occasions();
    $recipients = sad_znaniy_bouquet_recipients();
    $flowers    = sad_znaniy_bouquet_get_flowers();
    $items      = array();

    foreach ( (array) $bouquet['items'] as $item ) {
        $slug = isset( $item['slug'] ) ? (string) $item['slug'] : '';
        if ( isset( $flowers[ $slug ] ) ) {
            $flower          = $flowers[ $slug ];
            $flower['count'] = isset( $item['count'] ) ? (int) $item['count'] : 1;
            $items[]         = $flower;
        }
    }

    $user  = wp_get_current_user();
    $title = (string) $bouquet['title'];
    if ( '' === $title ) {
        $title = sad_znaniy_bouquet_generate_title(
            array(
                'occasion' => $bouquet['occasion'],
                'to'       => $bouquet['to'],
                'items'    => $bouquet['items'],
            )
        );
    }

    return rest_ensure_response(
        array(
            'bouquet'   => $bouquet,
            'title'     => $title,
            'occasion'  => isset( $occasions[ $bouquet['occasion'] ] ) ? $occasions[ $bouquet['occasion'] ] : '',
            'to'        => isset( $recipients[ $bouquet['to'] ] ) ? $recipients[ $bouquet['to'] ] : '',
            'items'     => $items,
            'conflicts' => sad_znaniy_bouquet_check_conflicts( $bouquet['items'], $bouquet['occasion'] ),
            'oddness'   => sad_znaniy_bouquet_oddness( $bouquet['items'] ),
            'user'      => array(
                'loggedIn' => is_user_logged_in(),
                'name'     => (string) $user->display_name,
                'email'    => (string) $user->user_email,
            ),
            'site'      => array(
                'name' => (string) get_bloginfo( 'name' ),
                'url'  => home_url( '/' ),
            ),
            'date'      => current_time( 'd.m.Y' ),
            'share'     => sad_znaniy_bouquet_share_url( $id ),
            'gate'      => (bool) get_option( 'sad_znaniy_bouquet_gate', 1 ),
            'qr'        => get_template_directory_uri() . '/assets/img/qr-site.svg',
        )
    );
}