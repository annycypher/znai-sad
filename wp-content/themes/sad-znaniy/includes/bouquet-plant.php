<?php
/**
 * Блок «Язык цветов» на карточке растения (Этап 6.5, Партия 4).
 *
 * Связь: CPT `flower` → мета `_sz_fl_plant` → карточка `plant`.
 * Показываем значение, описание, оттенки и ссылку в конструктор.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Цветы, привязанные к растению (базовый — первым).
 *
 * @param int $plant_id ID растения.
 * @return array
 */
function sad_znaniy_bouquet_flowers_for_plant( $plant_id ) {
    $out = array();
    foreach ( sad_znaniy_bouquet_get_flowers() as $flower ) {
        if ( (int) $flower['plant'] === (int) $plant_id ) {
            $out[] = $flower;
        }
    }

    usort(
        $out,
        function ( $a, $b ) {
            $a_variant = '' !== (string) $a['variant'];
            $b_variant = '' !== (string) $b['variant'];
            if ( $a_variant !== $b_variant ) {
                return $a_variant ? 1 : -1;
            }
            return (int) $a['sort'] <=> (int) $b['sort'];
        }
    );

    return $out;
}

/**
 * Печатает блок «Язык цветов» на карточке растения.
 *
 * @param int $plant_id ID растения.
 */
function sad_znaniy_bouquet_plant_block( $plant_id ) {
    $flowers = sad_znaniy_bouquet_flowers_for_plant( $plant_id );
    if ( ! $flowers ) {
        return;
    }

    $main     = $flowers[0];
    $variants = array_slice( $flowers, 1 );
    $url      = add_query_arg( 'flower', $main['slug'], sad_znaniy_bouquet_page_url() );
    ?>
    <section class="plant-language" aria-labelledby="plantLanguageTitle">
        <div class="plant-language__head">
            <div>
                <h2 id="plantLanguageTitle" class="h-sub"><?php esc_html_e( 'Язык цветов', 'sad-znaniy' ); ?></h2>
                <p class="plant-language__kicker"><?php echo esc_html( 'Что означает ' . $main['title'] ); ?></p>
            </div>
            <a class="plant-language__all" href="<?php echo esc_url( sad_znaniy_bouquet_page_url() ); ?>"><?php esc_html_e( 'Все значения', 'sad-znaniy' ); ?> →</a>
        </div>

        <div class="plant-language__main">
            <div class="plant-language__media">
                <?php if ( ! empty( $main['photo'] ) ) : ?>
                    <img src="<?php echo esc_url( (string) $main['photo'] ); ?>" alt="" width="96" height="96" loading="lazy">
                <?php else : ?>
                    <span class="plant-language__letter" aria-hidden="true"><?php echo esc_html( (string) $main['initial'] ); ?></span>
                <?php endif; ?>
            </div>
            <div class="plant-language__body">
                <p class="plant-language__meaning"><?php echo esc_html( (string) $main['meaning'] ); ?></p>
                <p class="plant-language__desc"><?php echo esc_html( (string) $main['desc'] ); ?></p>
                <div class="plant-language__actions">
                    <a class="btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( 'Собрать букет: ' . $main['title'] ); ?> →</a>
                    <a class="btn btn-light" href="<?php echo esc_url( (string) $main['url'] ); ?>"><?php esc_html_e( 'Значение и традиции', 'sad-znaniy' ); ?> →</a>
                </div>
            </div>
        </div>

        <?php if ( $variants ) : ?>
            <div class="plant-language__variants">
                <span class="plant-language__variants-label"><?php esc_html_e( 'Оттенки значения:', 'sad-znaniy' ); ?></span>
                <?php foreach ( $variants as $variant ) : ?>
                    <a href="<?php echo esc_url( (string) $variant['url'] ); ?>"><?php echo esc_html( $variant['title'] . ' — ' . $variant['meaning'] ); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}