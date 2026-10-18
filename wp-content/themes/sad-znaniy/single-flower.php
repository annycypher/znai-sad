<?php
/**
 * Страница значения цветка (CPT flower).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();
    $flower = sad_znaniy_bouquet_flower_data( get_post() );
    $cats   = sad_znaniy_bouquet_categories();
    $season = (array) $flower['season'];
    $months = array(
        1 => 'январь', 2 => 'февраль', 3 => 'март', 4 => 'апрель',
        5 => 'май', 6 => 'июнь', 7 => 'июль', 8 => 'август',
        9 => 'сентябрь', 10 => 'октябрь', 11 => 'ноябрь', 12 => 'декабрь',
    );
    $season_text = array();
    foreach ( $season as $month ) {
        $month = (int) $month;
        if ( isset( $months[ $month ] ) ) {
            $season_text[] = $months[ $month ];
        }
    }
    $url = add_query_arg( 'flower', $flower['slug'], sad_znaniy_bouquet_page_url() );
    ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class( 'flower-single' ); ?>>
        <section class="section">
            <div class="section-head">
                <div class="article-head">
                    <div class="meta-line">
                        <a href="<?php echo esc_url( sad_znaniy_bouquet_page_url() ); ?>"><?php esc_html_e( 'Конструктор букета', 'sad-znaniy' ); ?></a>
                        <span aria-hidden="true">·</span> <?php esc_html_e( 'Значение цветка', 'sad-znaniy' ); ?>
                    </div>
                    <h1 class="h-cap"><?php echo esc_html( $flower['title'] ); ?></h1>
                </div>
                <a class="btn btn-light" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Собрать букет', 'sad-znaniy' ); ?> →</a>
            </div>

            <div class="plant-grid">
                <div class="plant-media">
                    <?php if ( ! empty( $flower['photo'] ) ) : ?>
                        <img src="<?php echo esc_url( (string) $flower['photo'] ); ?>" alt="" width="480" height="480" loading="lazy">
                    <?php else : ?>
                        <span class="flower-single__letter" aria-hidden="true"><?php echo esc_html( (string) $flower['initial'] ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="plant-content">
                    <div class="article-body">
                        <p class="flower-single__meaning"><?php echo esc_html( $flower['meaning'] ); ?></p>
                        <p><?php echo esc_html( $flower['desc'] ); ?></p>
                    </div>

                    <div class="calc-tiles plant-params">
                        <?php if ( ! empty( $flower['rare'] ) ) : ?>
                            <div class="tile"><strong><?php esc_html_e( 'Редкость', 'sad-znaniy' ); ?></strong><span class="plant-param-value">✦ <?php esc_html_e( 'редкий', 'sad-znaniy' ); ?></span></div>
                        <?php endif; ?>
                        <?php if ( $season_text ) : ?>
                            <div class="tile"><strong><?php esc_html_e( 'Сезон', 'sad-znaniy' ); ?></strong><span class="plant-param-value"><?php echo esc_html( implode( ', ', $season_text ) ); ?></span></div>
                        <?php endif; ?>
                        <?php foreach ( (array) $flower['cats'] as $cat ) : ?>
                            <?php if ( isset( $cats[ $cat ] ) && 'filler' !== $cat ) : ?>
                                <div class="tile"><strong><?php esc_html_e( 'Значение', 'sad-znaniy' ); ?></strong><span class="plant-param-value"><?php echo esc_html( $cats[ $cat ] ); ?></span></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="flower-single__actions">
                        <a class="btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( 'Собрать букет: ' . $flower['title'] ); ?> →</a>
                        <?php if ( ! empty( $flower['plant_url'] ) ) : ?>
                            <a class="btn btn-light" href="<?php echo esc_url( (string) $flower['plant_url'] ); ?>"><?php esc_html_e( 'О растении', 'sad-znaniy' ); ?> →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php sad_znaniy_related_tools( 'plant' ); ?>
        </section>
    </article>
    <?php
endwhile;

get_footer();