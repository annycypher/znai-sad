<?php
/**
 * Карточки событий программатик-страницы (переиспользуемый компонент,
 * без чек-боксов). Ожидает $GLOBALS['sz_pg'] с ключом 'events'.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sz_events = isset( $GLOBALS['sz_pg']['events'] ) ? $GLOBALS['sz_pg']['events'] : array();
foreach ( $sz_events as $sz_ev ) :
	$sz_from  = (string) get_post_meta( $sz_ev->ID, '_sz_event_date_from', true );
	$sz_to    = (string) get_post_meta( $sz_ev->ID, '_sz_event_date_to', true );
	$sz_crop  = (int) get_post_meta( $sz_ev->ID, '_sz_event_crop', true );
	$sz_terms = get_the_terms( $sz_ev->ID, 'work_type' );
	$sz_type  = ( $sz_terms && ! is_wp_error( $sz_terms ) ) ? $sz_terms[0]->slug : 'podgotovka';
	?>
	<article class="task">
		<div class="t-body">
			<div class="t-top">
				<span class="t-title"><?php echo esc_html( get_the_title( $sz_ev ) ); ?></span>
				<span class="t-type <?php echo esc_attr( $sz_type ); ?>"><?php echo esc_html( sad_znaniy_option_label( sad_znaniy_work_types(), $sz_type ) ); ?></span>
			</div>
			<div class="t-meta">
				<span>📅 <?php echo esc_html( sad_znaniy_event_term_text( $sz_from, $sz_to ) ); ?></span>
				<?php if ( $sz_crop ) : ?><span>🌱 <?php echo esc_html( get_the_title( $sz_crop ) ); ?></span><?php endif; ?>
			</div>
		</div>
	</article>
	<?php
endforeach;
