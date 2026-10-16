<?php
/**
 * Страница «Калькуляторы»: SEO-теги и напоминание в админке (Этап 6/7).
 *
 * Title и description выводятся ровно один раз и только для страницы kalkulyatory.
 * В админке — напоминание про размеры фото карточек и про устаревший список.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Это страница калькуляторов?
 *
 * @return bool
 */
function sad_znaniy_calc_is_page() {
return is_page( 'kalkulyatory' );
}

/**
 * SEO-заголовок страницы.
 *
 * Название сайта включено в строку вручную (через «|»), поэтому часть
 * $parts['site'] убираем — иначе WordPress допишет «— Сад знаний» вторым разом.
 *
 * @param array $parts Части заголовка.
 * @return array
 */
function sad_znaniy_calc_title_parts( $parts ) {
if ( ! sad_znaniy_calc_is_page() ) {
return $parts;
}
$parts['title'] = 'Калькуляторы для дачника — полив, удобрения, грунт, посев | Сад знаний';
unset( $parts['site'] );
return $parts;
}
add_filter( 'document_title_parts', 'sad_znaniy_calc_title_parts', 20 );

/**
 * Meta description страницы — своя, одна.
 */
function sad_znaniy_calc_description() {
if ( ! sad_znaniy_calc_is_page() ) {
return;
}
echo '<meta name="description" content="4 бесплатных калькулятора: расчёт полива, дозы удобрений, раскисление грунта и сроки посева — с учётом вашего региона. Без регистрации." />' . "\n";
}
add_action( 'wp_head', 'sad_znaniy_calc_description', 1 );

/**
 * Напоминание в админке на экране редактирования страницы «Калькуляторы».
 */
function sad_znaniy_calc_admin_notice() {
if ( ! current_user_can( 'edit_pages' ) ) {
return;
}
$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
if ( ! $screen || 'post' !== $screen->base ) {
return;
}
$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! $post_id ) {
return;
}
$post = get_post( $post_id );
if ( ! $post || 'kalkulyatory' !== $post->post_name ) {
return;
}
echo '<div class="notice notice-info"><p><strong>Калькуляторы — фото карточек:</strong> размер <strong>800×500 (16:10)</strong>, файлы в теме: <code>assets/img/calculators/</code> — <code>calc-water.jpg</code>, <code>calc-feed.jpg</code>, <code>calc-soil.jpg</code>, <code>calc-seeds.jpg</code>. Пока файла нет — показывается иконка, страница не ломается. Список ссылок в тексте этой страницы устарел: на сайте он не выводится, при желании удалите его из текста вручную.</p></div>';
}
add_action( 'admin_notices', 'sad_znaniy_calc_admin_notice' );