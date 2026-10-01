<?php
/**
 * Программатик: «Работы дачника: регион, месяц».
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sz_ctx = isset( $GLOBALS['sz_pg'] ) ? $GLOBALS['sz_pg'] : null;

get_header();
global $wp_locale;
$region_label = sad_znaniy_option_label( sad_znaniy_region_keys(), $sz_ctx['region'] );
$month_name   = mb_strtolower( $wp_locale->month[ zeroise( $sz_ctx['month'], 2 ) ], 'UTF-8' );
?>

	<section class="section">
		<div class="section-head">
			<h1 class="h-cap"><?php printf( esc_html__( 'Работы дачника: %s, %s', 'sad-znaniy' ), esc_html( $region_label ), esc_html( $month_name ) ); ?></h1>
		</div>
		<?php get_template_part( 'templates/programmatic/parts/body' ); ?>
	</section>

<?php
get_footer();
