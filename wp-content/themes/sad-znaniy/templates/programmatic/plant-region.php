<?php
/**
 * Программатик: «Когда сажать растение (регион)».
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sz_ctx = isset( $GLOBALS['sz_pg'] ) ? $GLOBALS['sz_pg'] : null;

get_header();
$region_label = sad_znaniy_option_label( sad_znaniy_region_keys(), $sz_ctx['region'] );
$plant_name   = mb_strtolower( $sz_ctx['plant']->post_title, 'UTF-8' );
?>

	<section class="section">
		<div class="section-head">
			<h1 class="h-cap"><?php printf( esc_html__( 'Когда сажать %s (%s)', 'sad-znaniy' ), esc_html( $plant_name ), esc_html( $region_label ) ); ?></h1>
		</div>
		<?php get_template_part( 'templates/programmatic/parts/body' ); ?>
	</section>

<?php
get_footer();
