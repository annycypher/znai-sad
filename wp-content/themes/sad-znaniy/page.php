<?php
/**
 * Обычная страница сайта (заглушки разделов и т.п.).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="section">
		<div class="section-head">
			<h1 class="h-cap"><?php the_title(); ?></h1>
		</div>
		<div class="article-body">
			<?php the_content(); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
