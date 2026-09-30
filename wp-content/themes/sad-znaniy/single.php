<?php
/**
 * Одиночная запись: статья блога или рецепт.
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
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'article' ); ?>>
		<div class="section-head">
			<div class="article-head">
				<div class="article-meta">
					<?php the_category( ', ' ); ?>
					<span aria-hidden="true">·</span>
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				</div>
				<h1 class="h-cap"><?php the_title(); ?></h1>
			</div>
		</div>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="article-media"><?php the_post_thumbnail( 'large' ); ?></div>
		<?php endif; ?>

		<div class="article-body">
			<?php
			the_content();
			wp_link_pages(
				array(
					'before' => '<div class="article-pages">',
					'after'  => '</div>',
				)
			);
			?>
		</div>

		<?php the_tags( '<div class="article-tags">', '', '</div>' ); ?>
	</article>
	<?php
endwhile;

get_footer();
