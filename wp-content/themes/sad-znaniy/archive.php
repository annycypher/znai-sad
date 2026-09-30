<?php
/**
 * Архивы: рубрики, метки, таксономии растения, даты, архивы CPT.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

	<section class="section">
		<div class="section-head">
			<h1 class="h-cap"><?php the_archive_title(); ?></h1>
		</div>

		<?php
		$sad_znaniy_desc = get_the_archive_description();
		if ( $sad_znaniy_desc ) {
			echo '<div class="hero-sub" style="max-width:none;margin-bottom:20px;">' . wp_kses_post( $sad_znaniy_desc ) . '</div>';
		}

		get_template_part( 'template-parts/loop' );
		?>
	</section>

<?php
get_footer();
