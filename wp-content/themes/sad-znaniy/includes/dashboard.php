<?php
/**
 * Виджет дашборда «Сад знаний».
 *
 * Показывает 3 последние статьи, 3 ближайших события и быстрые кнопки
 * «Написать статью», «Добавить событие», «Добавить растение».
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует виджет дашборда.
 */
function sad_znaniy_register_dashboard_widget() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'sad_znaniy_dashboard',
		__( 'Сад знаний', 'sad-znaniy' ),
		'sad_znaniy_render_dashboard_widget'
	);
}
add_action( 'wp_dashboard_setup', 'sad_znaniy_register_dashboard_widget' );

/**
 * Выводит содержимое виджета дашборда.
 */
function sad_znaniy_render_dashboard_widget() {
	$buttons = array(
		array(
			'url'   => admin_url( 'post-new.php' ),
			'label' => __( 'Написать статью', 'sad-znaniy' ),
		),
		array(
			'url'   => admin_url( 'post-new.php?post_type=calendar_event' ),
			'label' => __( 'Добавить событие', 'sad-znaniy' ),
		),
		array(
			'url'   => admin_url( 'post-new.php?post_type=plant' ),
			'label' => __( 'Добавить растение', 'sad-znaniy' ),
		),
	);
	?>
	<div class="sz-dash">
		<p class="sz-dash-buttons" style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px;">
			<?php foreach ( $buttons as $button ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $button['url'] ); ?>"><?php echo esc_html( $button['label'] ); ?></a>
			<?php endforeach; ?>
		</p>

		<h3 style="margin:14px 0 6px;"><?php esc_html_e( 'Последние статьи', 'sad-znaniy' ); ?></h3>
		<?php
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 3,
				'no_found_rows'  => true,
			)
		);

		if ( $posts ) :
			?>
			<ul style="margin:0 0 6px;">
				<?php foreach ( $posts as $item ) : ?>
					<li>
						<a href="<?php echo esc_url( get_edit_post_link( $item->ID ) ); ?>"><?php echo esc_html( get_the_title( $item ) ); ?></a>
						<span style="color:#646970;">— <?php echo esc_html( get_the_date( '', $item ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Статей пока нет.', 'sad-znaniy' ); ?></p>
		<?php endif; ?>

		<h3 style="margin:14px 0 6px;"><?php esc_html_e( 'Ближайшие события', 'sad-znaniy' ); ?></h3>
		<?php
		$events = get_posts(
			array(
				'post_type'      => 'calendar_event',
				'posts_per_page' => 3,
				'meta_key'       => '_sz_event_date_from',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_sz_event_date_from',
						'value'   => current_time( 'Y-m-d' ),
						'compare' => '>=',
						'type'    => 'DATE',
					),
				),
			)
		);

		if ( $events ) :
			?>
			<ul style="margin:0;">
				<?php
				foreach ( $events as $event ) :
					$from   = (string) get_post_meta( $event->ID, '_sz_event_date_from', true );
					$region = (string) get_post_meta( $event->ID, '_sz_event_region', true );
					?>
					<li>
						<a href="<?php echo esc_url( get_edit_post_link( $event->ID ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a>
						<span style="color:#646970;">
							—
							<?php echo esc_html( sad_znaniy_event_term_text( $from, '' ) ); ?><?php echo $region ? ', ' . esc_html( sad_znaniy_region_label( $region ) ) : ''; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Ближайших событий нет.', 'sad-znaniy' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}
