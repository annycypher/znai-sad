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
			<?php $sad_znaniy_img = sad_znaniy_card_image( get_the_ID() ); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
				<?php if ( $sad_znaniy_img ) : ?>
					<a class="post-card__media" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1"><?php echo sad_znaniy_card_image_tag( $sad_znaniy_img ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- безопасная разметка из помощника. ?></a>
				<?php endif; ?>
				<div class="post-body">
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p><?php echo esc_html( sad_znaniy_announce_short( get_the_ID() ) ); ?></p>
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
