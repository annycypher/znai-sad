<?php
/**
 * Программатик: «Уход за растением: месяц».
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sz_ctx = isset( $GLOBALS['sz_pg'] ) ? $GLOBALS['sz_pg'] : null;

get_header();
global $wp_locale;
$month_name = mb_strtolower( $wp_locale->month[ zeroise( $sz_ctx['month'], 2 ) ], 'UTF-8' );
$plant_name = mb_strtolower( $sz_ctx['plant']->post_title, 'UTF-8' );
?>

	<section class="section">
		<div class="section-head">
			<h1 class="h-cap"><?php printf( esc_html__( 'Уход за %s: %s', 'sad-znaniy' ), esc_html( $plant_name ), esc_html( $month_name ) ); ?></h1>
		</div>
		<div class="tasks"><?php get_template_part( 'templates/programmatic/parts/cards' ); ?></div>
	</section>

<?php
get_footer();
