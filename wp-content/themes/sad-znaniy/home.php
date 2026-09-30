<?php
/**
 * Блог: список записей (страница «Все статьи»).
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
			<h1 class="h-cap">
				<?php
				$sad_znaniy_posts_page = (int) get_option( 'page_for_posts' );
				if ( $sad_znaniy_posts_page ) {
					echo esc_html( get_the_title( $sad_znaniy_posts_page ) );
				} else {
					esc_html_e( 'Блог и советы', 'sad-znaniy' );
				}
				?>
			</h1>
		</div>

		<?php get_template_part( 'template-parts/loop' ); ?>
	</section>

<?php
get_footer();
