<?php
/**
 * Параметры растения на программатик-странице: свет, полив, pH.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sz_ctx = isset( $GLOBALS['sz_pg'] ) ? $GLOBALS['sz_pg'] : null;
if ( empty( $sz_ctx['plant'] ) ) {
	return;
}

$sz_params = sad_znaniy_plant_params( $sz_ctx['plant']->ID );
if ( ! $sz_params ) {
	return;
}
?>
<div class="pg-plant-meta">
	<h2><?php esc_html_e( 'Параметры растения', 'sad-znaniy' ); ?></h2>
	<ul class="plant-params">
		<?php foreach ( $sz_params as $sz_param ) : ?>
			<li>
				<strong><?php echo esc_html( $sz_param[0] ); ?></strong>
				<span class="plant-param-value"><?php echo esc_html( $sz_param[1] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
