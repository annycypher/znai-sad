<?php
/**
 * Одиночная страница растения: параметры выращивания и совместимость.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$plant_id = get_the_ID();

	$light   = (string) get_post_meta( $plant_id, '_sz_plant_light', true );
	$water   = (string) get_post_meta( $plant_id, '_sz_plant_water', true );
	$ph_from = (string) get_post_meta( $plant_id, '_sz_plant_ph_from', true );
	$ph_to   = (string) get_post_meta( $plant_id, '_sz_plant_ph_to', true );
	$soil    = (string) get_post_meta( $plant_id, '_sz_plant_soil', true );
	$good    = (string) get_post_meta( $plant_id, '_sz_plant_neighbors_good', true );
	$bad     = (string) get_post_meta( $plant_id, '_sz_plant_neighbors_bad', true );

	$light_label = sad_znaniy_option_label( sad_znaniy_light_options(), $light );
	$water_label = sad_znaniy_option_label( sad_znaniy_water_options(), $water );

	$ph_parts = array();
	if ( '' !== $ph_from ) {
		$ph_parts[] = number_format_i18n( (float) $ph_from, 1 );
	}
	if ( '' !== $ph_to ) {
		$ph_parts[] = number_format_i18n( (float) $ph_to, 1 );
	}
	$ph_label = $ph_parts ? implode( '–', $ph_parts ) : '';

	$params = array();
	if ( $light_label ) {
		$params[] = array( __( 'Свет', 'sad-znaniy' ), $light_label );
	}
	if ( $water_label ) {
		$params[] = array( __( 'Полив', 'sad-znaniy' ), $water_label );
	}
	if ( $ph_label ) {
		$params[] = array( __( 'Кислотность (pH)', 'sad-znaniy' ), $ph_label );
	}
	if ( $soil ) {
		$params[] = array( __( 'Почва', 'sad-znaniy' ), $soil );
	}

	$archive_url = get_post_type_archive_link( 'plant' );
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'plant' ); ?>>
		<section class="section">
			<div class="section-head">
				<div class="article-head">
					<div class="meta-line">
						<?php
						$terms = get_the_term_list( $plant_id, 'plant_section', '', ', ' );
						if ( $terms && ! is_wp_error( $terms ) ) {
							echo wp_kses_post( $terms );
						}
						$type_terms = get_the_term_list( $plant_id, 'plant_type', '', ', ' );
						if ( $type_terms && ! is_wp_error( $type_terms ) ) {
							echo ' <span aria-hidden="true">·</span> ' . wp_kses_post( $type_terms );
						}
						?>
					</div>
					<h1 class="h-cap"><?php the_title(); ?></h1>
				</div>
				<?php if ( $archive_url ) : ?>
					<a href="<?php echo esc_url( $archive_url ); ?>" class="btn btn-light"><?php esc_html_e( 'Все растения', 'sad-znaniy' ); ?></a>
				<?php endif; ?>
			</div>

			<div class="plant-grid">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="plant-media"><?php the_post_thumbnail( 'large' ); ?></div>
				<?php endif; ?>

				<div class="plant-content">
					<div class="article-body"><?php the_content(); ?></div>

					<?php if ( $params ) : ?>
						<div class="calc-tiles plant-params">
							<?php foreach ( $params as $param ) : ?>
								<div class="tile">
									<strong><?php echo esc_html( $param[0] ); ?></strong>
									<span class="plant-param-value"><?php echo esc_html( $param[1] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $good || $bad ) : ?>
				<div class="about-panel plant-compat">
					<div>
						<h2><?php esc_html_e( 'Хорошие соседи', 'sad-znaniy' ); ?></h2>
						<?php if ( $good ) : ?>
							<p><?php echo nl2br( esc_html( $good ) ); ?></p>
						<?php else : ?>
							<p>&mdash;</p>
						<?php endif; ?>
					</div>
					<div>
						<h2><?php esc_html_e( 'Плохие соседи', 'sad-znaniy' ); ?></h2>
						<?php if ( $bad ) : ?>
							<p><?php echo nl2br( esc_html( $bad ) ); ?></p>
						<?php else : ?>
							<p>&mdash;</p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
	</article>
	<?php
endwhile;

get_footer();
