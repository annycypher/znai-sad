<?php
/**
 * ОБЩИЙ НАБОР ИКОНОК ИНСТРУМЕНТОВ (Этап 6/7).
 *
 * Инлайн-SVG 24×24 без CDN и спрайтов: используется плитками калькуляторов,
 * планировщиком и печатными карточками. Здесь же — данные плиток страницы
 * «Калькуляторы» и авто-подстановка её шаблона по слагу (как у планировщика).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Пути иконок (d-атрибуты, stroke-стиль).
 *
 * @return array
 */
function sad_znaniy_tool_icon_paths() {
return array(
'i-drop'  => '<path d="M12 3c3.5 4 6 7.2 6 10.5a6 6 0 0 1-12 0C6 10.2 8.5 7 12 3Z"/>',
'i-bag'   => '<path d="M6 8h12l-1 13H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
'i-calc'  => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h.01"/>',
'i-flask' => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-9V3"/><path d="M7 15h10"/>',
'i-cal'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
'i-moon'  => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/>',
'i-book'  => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>',
'i-plan'  => '<path d="M9 20 3 17V4l6 3 6-3 6 3v13l-6-3-6 3Z"/><path d="M9 7v13M15 4v13"/>',
);
}

/**
 * Иконка инструмента.
 *
 * @param string $key  Ключ (i-drop…).
 * @param int    $size Размер, px.
 * @return string SVG или пустая строка.
 */
function sad_znaniy_tool_icon( $key, $size = 21 ) {
$paths = sad_znaniy_tool_icon_paths();
if ( ! isset( $paths[ $key ] ) ) {
return '';
}
$size = max( 14, min( 48, (int) $size ) );
return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $key ] . '</svg>';
}

/**
 * Плитки инструментов: калькуляторы и разделы (данные для страницы и блоков).
 *
 * @return array
 */
function sad_znaniy_tool_tiles() {
return array(
array( 'title' => 'Расчёт полива', 'desc' => 'Сколько и как часто поливать с учётом культуры, почвы и погоды', 'url' => home_url( '/kalkulyator-poliva/' ), 'icon' => 'i-drop' ),
array( 'title' => 'Удобрения', 'desc' => 'Дозы и сроки подкормок по культуре и площади грядки', 'url' => home_url( '/kalkulyator-udobreniy/' ), 'icon' => 'i-bag' ),
array( 'title' => 'Посев семян', 'desc' => 'Дата посева на рассаду и в грунт по вашему региону', 'url' => home_url( '/kalkulyator-poseva/' ), 'icon' => 'i-calc' ),
array( 'title' => 'Грунт и pH', 'desc' => 'Раскисление, состав и норма внесения на сотку', 'url' => home_url( '/kalkulyator-grunta-i-ph/' ), 'icon' => 'i-flask' ),
);
}

/**
 * Разделы для блока «Смотрите также» (перелинковка с калькуляторов).
 *
 * @return array
 */
function sad_znaniy_also_links() {
return array(
array( 'title' => 'Календарь дачника', 'url' => home_url( '/kalendar/' ), 'icon' => 'i-cal' ),
array( 'title' => 'Лунный календарь', 'url' => home_url( '/lunnyj-kalendar/' ), 'icon' => 'i-moon' ),
array( 'title' => 'Уход за садом', 'url' => home_url( '/uhod/' ), 'icon' => 'i-book' ),
array( 'title' => 'Планировщик участка', 'url' => home_url( '/planirovshchik/' ), 'icon' => 'i-plan' ),
);
}

/**
 * Подставляет шаблон «Калькуляторы» странице со слагом kalkulyatory.
 *
 * @param string $template Путь шаблона.
 * @return string
 */
function sad_znaniy_calculators_template( $template ) {
if ( is_page() ) {
$post = get_queried_object();
if ( $post && isset( $post->post_name ) && 'kalkulyatory' === $post->post_name && ! is_page_template( 'template-calculators.php' ) ) {
$own = get_template_directory() . '/template-calculators.php';
if ( file_exists( $own ) ) {
return $own;
}
}
}
return $template;
}
add_filter( 'template_include', 'sad_znaniy_calculators_template' );