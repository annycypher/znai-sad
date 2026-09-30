<?php
/**
 * Шаблонная часть: сетка записей + пагинация.
 *
 * Используется в home.php, archive.php и index.php.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( have_posts() ) :
	?>
	<div class="posts">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>">
						<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
					</a>
				<?php endif; ?>
				<div class="post-body">
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
					<span class="post-date"><?php echo esc_html( get_the_date() ); ?></span>
				</div>
			</article>
			<?php
		endwhile;
		?>
	</div>

	<div class="section-head">
		<?php
		the_posts_pagination(
			array(
				'prev_text' => esc_html__( 'Назад', 'sad-znaniy' ),
				'next_text' => esc_html__( 'Вперёд', 'sad-znaniy' ),
			)
		);
		?>
	</div>
	<?php
else :
	?>
	<p class="hero-sub"><?php esc_html_e( 'Записей пока нет.', 'sad-znaniy' ); ?></p>
	<?php
endif;
