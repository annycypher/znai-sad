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
?>

	<section class="section">
		<div class="section-head">
			<h1 class="h-cap"><?php echo esc_html( $sz_ctx['page']['phrase'] ); ?></h1>
		</div>
		<?php get_template_part( 'templates/programmatic/parts/plant-meta' ); ?>
		<?php get_template_part( 'templates/programmatic/parts/body' ); ?>
	</section>

<?php
get_footer();
