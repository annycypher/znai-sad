<?php
/**
 * Страница конструктора букета (Этап 6.5, Партия 2).
 *
 * Служебная страница /konstruktor-buketa/, шаблон и данные для клиента.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Это страница конструктора?
 *
 * @return bool
 */
function sad_znaniy_is_bouquet_page() {
    if ( is_page_template( 'template-bouquet.php' ) ) {
        return true;
    }
    return is_page( 'konstruktor-buketa' );
}

/**
 * Подставляет шаблон странице /konstruktor-buketa/.
 *
 * @param string $template Путь шаблона.
 * @return string
 */
function sad_znaniy_bouquet_template( $template ) {
    if ( sad_znaniy_is_bouquet_page() && ! is_page_template( 'template-bouquet.php' ) ) {
        $own = get_template_directory() . '/template-bouquet.php';
        if ( file_exists( $own ) ) {
            return $own;
        }
    }
    return $template;
}
add_filter( 'template_include', 'sad_znaniy_bouquet_template' );

/**
 * Создаёт служебную страницу «Конструктор букета» один раз.
 */
function sad_znaniy_bouquet_maybe_create_page() {
    if ( get_option( 'sad_znaniy_bouquet_page_ready' ) ) {
        return;
    }
    if ( get_page_by_path( 'konstruktor-buketa' ) ) {
        update_option( 'sad_znaniy_bouquet_page_ready', 1, false );
        return;
    }

    $id = wp_insert_post(
        array(
            'post_title'   => 'Конструктор букета',
            'post_name'    => 'konstruktor-buketa',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ),
        true
    );

    if ( $id && ! is_wp_error( $id ) ) {
        flush_rewrite_rules();
        update_option( 'sad_znaniy_bouquet_page_ready', 1, false );
    }
}
add_action( 'wp_loaded', 'sad_znaniy_bouquet_maybe_create_page' );

/**
 * Карточка цветка для сетки.
 *
 * @param array $flower        Данные цветка.
 * @param int   $current_month Текущий месяц.
 */
function sad_znaniy_bouquet_render_flower_card( $flower, $current_month ) {
    $cats      = implode( ',', (array) $flower['cats'] );
    $season    = (array) $flower['season'];
    $in_season = $season && in_array( (int) $current_month, array_map( 'intval', $season ), true );
    ?>
    <button class="bk-flower" type="button" data-slug="<?php echo esc_attr( (string) $flower['slug'] ); ?>" data-cats="<?php echo esc_attr( $cats ); ?>" aria-label="<?php echo esc_attr( $flower['title'] . ' — ' . $flower['meaning'] ); ?>">
        <span class="bk-flower__ring">
            <span class="bk-flower__media">
                <?php if ( ! empty( $flower['photo'] ) ) : ?>
                    <img src="<?php echo esc_url( (string) $flower['photo'] ); ?>" alt="" width="88" height="88" loading="lazy">
                <?php else : ?>
                    <span class="bk-flower__letter" aria-hidden="true"><?php echo esc_html( (string) $flower['initial'] ); ?></span>
                <?php endif; ?>
            </span>
            <?php if ( ! empty( $flower['rare'] ) ) : ?>
                <span class="bk-flower__rare" title="<?php esc_attr_e( 'Редкий цветок', 'sad-znaniy' ); ?>">✦</span>
            <?php endif; ?>
            <?php if ( $season ) : ?>
                <span class="bk-flower__season <?php echo $in_season ? 'is-in' : 'is-out'; ?>"><?php echo $in_season ? esc_html__( 'в сезоне', 'sad-znaniy' ) : esc_html__( 'вне сезона', 'sad-znaniy' ); ?></span>
            <?php endif; ?>
        </span>
        <span class="bk-flower__name"><?php echo esc_html( (string) $flower['title'] ); ?></span>
        <span class="bk-flower__meaning"><?php echo esc_html( (string) $flower['meaning'] ); ?></span>
    </button>
    <?php
}
/**
 * Данные конструктора для клиента.
 *
 * @return array
 */
/**
 * Настройки гейта регистрации на PDF.
 *
 * @return array
 */
function sad_znaniy_bouquet_gate() {
    $after = add_query_arg( 'print', '1', sad_znaniy_bouquet_page_url() );
    return array(
        'required'    => function_exists( 'sad_znaniy_bouquet_gate_required' ) ? sad_znaniy_bouquet_gate_required() : (bool) get_option( 'sad_znaniy_bouquet_gate', 1 ),
        'canRegister' => (bool) get_option( 'users_can_register' ),
        'loginUrl'    => wp_login_url(),
        'registerUrl' => add_query_arg( 'redirect_to', $after, wp_registration_url() ),
        'afterUrl'    => $after,
    );
}

function sad_znaniy_bouquet_contract() {
    $saved    = null;
    $readonly = false;

    if ( isset( $_GET['bouquet'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = sanitize_text_field( wp_unslash( (string) $_GET['bouquet'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( sad_znaniy_bouquet_is_id( $id ) ) {
            if ( is_user_logged_in() ) {
                $own = sad_znaniy_bouquet_get_own( $id, get_current_user_id() );
                if ( $own ) {
                    $saved = $own;
                }
            }
            if ( ! $saved ) {
                $public = sad_znaniy_bouquet_get_public( $id );
                if ( $public ) {
                    $saved    = $public;
                    $readonly = true;
                }
            }
        }
    }

    $user = wp_get_current_user();

    return array(
        'flowers'      => array_values( sad_znaniy_bouquet_get_flowers() ),
        'occasions'    => sad_znaniy_bouquet_occasions(),
        'recipients'   => sad_znaniy_bouquet_recipients(),
        'categories'   => sad_znaniy_bouquet_categories(),
        'hints'        => sad_znaniy_bouquet_hints(),
        'limits'       => sad_znaniy_bouquet_limits(),
        'recommendUrl' => esc_url_raw( rest_url( 'sad-znaniy/v1/bouquets/recommend' ) ),
        'saveUrl'      => esc_url_raw( rest_url( 'sad-znaniy/v1/bouquets' ) ),
        'viewUrl'      => esc_url_raw( rest_url( 'sad-znaniy/v1/bouquets/view' ) ),
        'mailUrl'      => esc_url_raw( rest_url( 'sad-znaniy/v1/bouquets/mail' ) ),
        'nonce'        => wp_create_nonce( 'wp_rest' ),
        'loggedIn'     => is_user_logged_in(),
        'userName'     => is_user_logged_in() ? (string) $user->display_name : '',
        'pageUrl'      => sad_znaniy_bouquet_page_url(),
        'gate'         => sad_znaniy_bouquet_gate(),
        'currentMonth' => (int) current_time( 'n' ),
        'readonly'     => $readonly,
        'saved'        => $saved,
        'decorLeft'    => get_template_directory_uri() . '/assets/img/decor/branch-left.svg',
        'decorRight'   => get_template_directory_uri() . '/assets/img/decor/branch-right.svg',
    );
}
/**
 * SEO-заголовок страницы конструктора и страниц цветов.
 *
 * @param string $title Текущий заголовок.
 * @return string
 */
function sad_znaniy_bouquet_document_title( $title ) {
    if ( sad_znaniy_is_bouquet_page() ) {
        $custom = get_post_meta( get_queried_object_id(), '_sz_seo_title', true );
        if ( $custom ) {
            return (string) $custom;
        }
        return 'Конструктор букета онлайн — собрать букет по смыслу и значению цветов | Сад знаний';
    }

    if ( is_singular( 'flower' ) ) {
        $custom = get_post_meta( get_queried_object_id(), '_sz_seo_title', true );
        if ( $custom ) {
            return (string) $custom;
        }
        $flower = sad_znaniy_bouquet_flower_data( get_queried_object() );
        return 'Значение цветка ' . $flower['title'] . ' — ' . $flower['meaning'] . ' | Сад знаний';
    }

    return $title;
}
add_filter( 'pre_get_document_title', 'sad_znaniy_bouquet_document_title', 20 );

/**
 * Мета-описание конструктора и страниц цветов.
 */
function sad_znaniy_bouquet_meta_description() {
    if ( sad_znaniy_is_bouquet_page() ) {
        $custom = get_post_meta( get_queried_object_id(), '_sz_seo_description', true );
        if ( $custom ) {
            return;
        }
        $desc = 'Соберите букет по поводу и адресату: подбор по значению цветов, проверка сочетаемости, нечётное число, готовый PDF с легендой и QR. Бесплатно, без регистрации для сборки.';
        echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
        return;
    }

    if ( is_singular( 'flower' ) ) {
        $custom = get_post_meta( get_queried_object_id(), '_sz_seo_description', true );
        if ( $custom ) {
            return;
        }
        $flower = sad_znaniy_bouquet_flower_data( get_queried_object() );
        $desc   = mb_substr( (string) $flower['desc'], 0, 160, 'UTF-8' );
        echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
    }
}
add_action( 'wp_head', 'sad_znaniy_bouquet_meta_description', 2 );

/**
 * Schema.org WebApplication для страницы конструктора.
 */
function sad_znaniy_bouquet_schema() {
    if ( ! sad_znaniy_is_bouquet_page() ) {
        return;
    }

    $schema = array(
        '@context'            => 'https://schema.org',
        '@type'               => 'WebApplication',
        'name'                => 'Конструктор букета',
        'url'                 => sad_znaniy_bouquet_page_url(),
        'description'         => 'Собрать букет по поводу и значению цветов: подбор, сочетаемость, нечётное число, PDF с легендой и QR.',
        'applicationCategory' => 'DesignApplication',
        'operatingSystem'     => 'Web',
        'inLanguage'          => 'ru-RU',
        'offers'              => array(
            '@type'         => 'Offer',
            'price'         => '0',
            'priceCurrency' => 'RUB',
        ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
add_action( 'wp_head', 'sad_znaniy_bouquet_schema', 20 );