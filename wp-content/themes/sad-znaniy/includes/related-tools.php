<?php
/**
 * Блок «Смежные инструменты» для карточек статьи и растения (Этап 6/7, блок C).
 *
 * Перелинковка: каждая статья и каждое растение ведут на разделы сайта
 * (уход, календарь, лунный календарь, калькуляторы, планировщик). Разметка
 * использует уже готовые стили .also-row / .also-link / .also-ico, иконки —
 * общий набор sad_znaniy_tool_icon() из includes/tool-icons.php.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Наборы ссылок по контексту.
 *
 * @param string $context article|plant.
 * @return array Массив [подпись, URL, ключ иконки].
 */
function sad_znaniy_related_tools_links( $context ) {
	$tools = array(
		'uhod' => array( 'Уход за садом', home_url( '/uhod/' ), 'i-book' ),
		'cal'  => array( 'Календарь дачника', home_url( '/kalendar/' ), 'i-cal' ),
		'moon' => array( 'Лунный календарь', home_url( '/lunnyj-kalendar/' ), 'i-moon' ),
		'calc' => array( 'Калькуляторы', home_url( '/kalkulyatory/' ), 'i-calc' ),
		'plan' => array( 'Планировщик участка', home_url( '/planirovshchik/' ), 'i-plan' ),
	);

	$keys = ( 'plant' === $context )
		? array( 'uhod', 'cal', 'calc', 'plan' )
		: array( 'uhod', 'cal', 'moon', 'calc' );

	$out = array();
	foreach ( $keys as $key ) {
		if ( isset( $tools[ $key ] ) ) {
			$out[] = $tools[ $key ];
		}
	}
	return $out;
}

/**
 * Печатает блок «Смежные инструменты».
 *
 * @param string $context article|plant.
 */
function sad_znaniy_related_tools( $context = 'article' ) {
	$links = sad_znaniy_related_tools_links( $context );
	if ( ! $links ) {
		return;
	}
	?>
	<section class="section" aria-labelledby="relatedTools">
		<div class="section-head"><h2 class="h-cap" id="relatedTools"><?php esc_html_e( 'Смежные инструменты', 'sad-znaniy' ); ?></h2></div>
		<div class="also-row">
			<?php foreach ( $links as $link ) : ?>
				<a class="also-link" href="<?php echo esc_url( $link[1] ); ?>"><span class="also-ico" aria-hidden="true"><?php echo sad_znaniy_tool_icon( $link[2], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG из общего набора темы. ?></span><?php echo esc_html( $link[0] ); ?></a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
