<?php
/**
 * Fallback-шаблон: простой вывод списка записей.
 *
 * Используется для архивов/поиска, пока не созданы отдельные шаблоны (Этап 2).
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
				if ( is_home() && ! is_front_page() ) {
					single_post_title();
				} elseif ( is_search() ) {
					/* translators: %s: поисковый запрос */
					printf( esc_html__( 'Поиск: %s', 'sad-znaniy' ), esc_html( get_search_query() ) );
				} else {
					the_archive_title();
				}
				?>
			</h1>
		</div>

		<?php get_template_part( 'template-parts/loop' ); ?>
	</section>

<?php
get_footer();