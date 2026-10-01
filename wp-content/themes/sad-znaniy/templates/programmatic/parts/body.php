<?php
/**
 * Общая разметка программатик-страницы: хлебные крошки, интро,
 * карточки событий, блок «Сроки по регионам», ссылки.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sz_ctx  = isset( $GLOBALS['sz_pg'] ) ? $GLOBALS['sz_pg'] : null;
$sz_page = $sz_ctx['page'];
?>

<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'Хлебные крошки', 'sad-znaniy' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Главная', 'sad-znaniy' ); ?></a> ›
	<a href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>"><?php esc_html_e( 'Календарь дачника', 'sad-znaniy' ); ?></a> ›
	<span><?php echo esc_html( $sz_page['phrase'] ); ?></span>
</nav>

<div class="article-body">
	<?php echo wp_kses_post( wpautop( $sz_page['intro'] ) ); ?>
</div>

<div class="tasks"><?php get_template_part( 'templates/programmatic/parts/cards' ); ?></div>

<?php
$sz_related = sad_znaniy_programmatic_related( $sz_ctx );
if ( $sz_related ) :
	?>
	<div class="pg-related">
		<h2><?php esc_html_e( 'Сроки по регионам', 'sad-znaniy' ); ?></h2>
		<ul>
			<?php foreach ( $sz_related as $sz_r ) : ?>
				<li><a href="<?php echo esc_url( $sz_r['url'] ); ?>"><?php echo esc_html( $sz_r['phrase'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php
$sz_links = sad_znaniy_programmatic_page_links( $sz_ctx );
if ( $sz_links ) :
	?>
	<div class="t-links"><?php esc_html_e( 'Подробнее:', 'sad-znaniy' ); ?>
		<?php
		foreach ( $sz_links as $sz_i => $sz_l ) :
			echo ( $sz_i > 0 ? ', ' : ' ' );
			?>
			<a href="<?php echo esc_url( $sz_l[1] ); ?>"><?php echo esc_html( $sz_l[0] ); ?></a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
