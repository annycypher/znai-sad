<?php
/**
 * Template Name: Калькуляторы (фото-карточки)
 *
 * Страница /kalkulyatory/: вводный текст владельца без устаревшего списка-дубля,
 * карточки: медиа-зона сверху (фото или иконка — одна высота), центрированный
 * текст и стрелка снизу; блок «Считайте точнее…».
 * Данные карточек — includes/tool-icons.php, SEO — includes/calculators-seo.php.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$sz_calc_content = (string) get_the_content();
// Устаревший список-дубль: на сайте не выводим (в админке есть напоминание).
$sz_calc_content = preg_replace( '#<!--\s*/?wp:list[^>]*-->\s*#i', '', $sz_calc_content );
$sz_calc_content = preg_replace( '#<ul\b.*?</ul>#is', '', $sz_calc_content, 1 );
?>
<section class="section">
    <div class="section-head">
        <h1 class="h-cap"><?php the_title(); ?></h1>
    </div>
    <?php if ( "" !== trim( wp_strip_all_tags( $sz_calc_content ) ) ) : ?>
        <div class="article-body"><?php echo apply_filters( 'the_content', $sz_calc_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- фильтры WordPress. ?></div>
    <?php endif; ?>
</section>

<section class="section" aria-labelledby="calcHead">
    <div class="section-head"><h2 class="h-cap" id="calcHead"><?php esc_html_e( 'Калькуляторы', 'sad-znaniy' ); ?></h2></div>
    <div class="calc-grid">
        <?php foreach ( sad_znaniy_tool_tiles() as $sz_tile ) : ?>
            <?php
            $sz_shot = "";
            $sz_w    = 0;
            $sz_h    = 0;
            if ( ! empty( $sz_tile['img'] ) ) {
                $sz_file = get_template_directory() . '/assets/img/calculators/' . $sz_tile['img'];
                if ( file_exists( $sz_file ) ) {
                    $sz_shot = get_template_directory_uri() . '/assets/img/calculators/' . $sz_tile['img'];
                    $sz_size = @getimagesize( $sz_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
                    if ( is_array( $sz_size ) ) {
                        $sz_w = (int) $sz_size[0];
                        $sz_h = (int) $sz_size[1];
                    }
                }
            }
            $sz_dim = "";
            if ( $sz_w > 0 && $sz_h > 0 ) {
                $sz_dim = ' width="' . (int) $sz_w . '" height="' . (int) $sz_h . '"';
            }
            ?>
            <a class="tile calc-card" href="<?php echo esc_url( $sz_tile['url'] ); ?>">
                <span class="calc-shot">
                    <?php if ( "" !== $sz_shot ) : ?>
                        <img src="<?php echo esc_url( $sz_shot ); ?>" alt="<?php echo esc_attr( (string) $sz_tile['alt'] ); ?>"<?php echo $sz_dim; // phpcs:ignore WordPress.Security.EscapeOutput ?> loading="lazy" decoding="async" />
                    <?php else : ?>
                        <span class="calc-ico" aria-hidden="true"><?php echo sad_znaniy_tool_icon( $sz_tile['icon'], 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <?php endif; ?>
                </span>
                <span class="calc-card__body">
                    <strong><?php echo esc_html( $sz_tile['title'] ); ?></strong>
                    <span class="calc-desc"><?php echo esc_html( $sz_tile['desc'] ); ?></span>
                    <span class="circle-go" aria-hidden="true">→</span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" aria-labelledby="calcRead">
    <div class="section-head"><h2 class="h-cap" id="calcRead"><?php esc_html_e( 'Считайте точнее — прочитайте перед расчётом', 'sad-znaniy' ); ?></h2></div>
    <div class="also-row">
        <?php foreach ( sad_znaniy_calc_read_links() as $sz_link ) : ?>
            <a class="also-link" href="<?php echo esc_url( $sz_link[1] ); ?>"><span class="also-ico" aria-hidden="true"><?php echo sad_znaniy_tool_icon( $sz_link[2], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php echo esc_html( $sz_link[0] ); ?></a>
        <?php endforeach; ?>
    </div>
</section>
<?php
get_footer();