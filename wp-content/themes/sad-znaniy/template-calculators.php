<?php
/**
 * Template Name: Калькуляторы (плитки)
 *
 * Страница /kalkulyatory/: вводный текст владельца сохраняется, а список
 * блоков заменяется сеткой стеклянных плиток с иконками (как «Инструменты»
 * на главной). Плитки и иконки — из includes/tool-icons.php.
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
				<h1 class="h-cap"><?php the_title(); ?></h1>
		</div>
		<?php if ( '' !== trim( (string) get_the_content() ) ) : ?>
				<div class="article-body"><?php the_content(); ?></div>
		<?php endif; ?>
</section>

<section class="section" aria-labelledby="calcHead">
		<div class="section-head"><h2 class="h-cap" id="calcHead">Калькуляторы</h2></div>
		<div class="calc-grid">
				<?php foreach ( sad_znaniy_tool_tiles() as $tile ) : ?>
						<a class="tile calc-tile" href="<?php echo esc_url( $tile['url'] ); ?>">
								<span class="tile-ico" aria-hidden="true"><?php echo sad_znaniy_tool_icon( $tile['icon'], 21 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<strong><?php echo esc_html( $tile['title'] ); ?></strong>
								<span class="calc-desc"><?php echo esc_html( $tile['desc'] ); ?></span>
								<span class="circle-go" aria-hidden="true">→</span>
						</a>
				<?php endforeach; ?>
		</div>
</section>

<section class="section" aria-labelledby="alsoHead">
		<div class="section-head"><h2 class="h-cap" id="alsoHead">Смотрите также</h2></div>
		<div class="also-row">
				<?php foreach ( sad_znaniy_also_links() as $tile ) : ?>
						<a class="also-link" href="<?php echo esc_url( $tile['url'] ); ?>"><span class="also-ico" aria-hidden="true"><?php echo sad_znaniy_tool_icon( $tile['icon'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php echo esc_html( $tile['title'] ); ?></a>
				<?php endforeach; ?>
		</div>
</section>
<?php
get_footer();