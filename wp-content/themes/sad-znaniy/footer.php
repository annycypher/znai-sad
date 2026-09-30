<?php
/**
 * Футер сайта: закрывает .frame и подключает wp_footer().
 *
 * Классы полностью соответствуют макету _design/index.html (контракт).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	</main>

	<!-- ================= ФУТЕР ================= -->
	<footer class="footer">
		<div class="footer-grid">
			<div>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo" style="margin-bottom:8px;">
					<span class="logo-mark">
						<svg width="24" height="28" viewBox="0 0 44.17 51.39" aria-hidden="true"><use href="#logo-sad"/></svg>
					</span>
					<span>
						<span class="logo-name" style="font-size:14px;"><?php bloginfo( 'name' ); ?></span>
					</span>
				</a>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Разделы', 'sad-znaniy' ); ?></h4>
				<?php
				if ( has_nav_menu( 'footer-menu' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'footer-menu',
							'container'      => false,
							'menu_class'     => '',
							'depth'          => 1,
						)
					);
				} else {
					?>
					<ul>
						<li><a href="#"><?php esc_html_e( 'Сад', 'sad-znaniy' ); ?></a></li>
						<li><a href="#"><?php esc_html_e( 'Огород', 'sad-znaniy' ); ?></a></li>
						<li><a href="#"><?php esc_html_e( 'Цветы', 'sad-znaniy' ); ?></a></li>
						<li><a href="#"><?php esc_html_e( 'Рецепты', 'sad-znaniy' ); ?></a></li>
					</ul>
					<?php
				}
				?>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Инструменты', 'sad-znaniy' ); ?></h4>
				<ul>
					<li><a href="#tools"><?php esc_html_e( 'Калькуляторы', 'sad-znaniy' ); ?></a></li>
					<li><a href="#tools"><?php esc_html_e( 'Планировщик', 'sad-znaniy' ); ?></a></li>
					<li><a href="#calendar"><?php esc_html_e( 'Календарь', 'sad-znaniy' ); ?></a></li>
					<li><a href="#sadvogorod"><?php esc_html_e( 'База знаний', 'sad-znaniy' ); ?></a></li>
				</ul>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Контакты', 'sad-znaniy' ); ?></h4>
				<a class="footer-phone" href="mailto:info@znai-sad.ru">info@znai-sad.ru</a>
				<a class="footer-phone" href="#"><?php esc_html_e( 'Telegram-канал', 'sad-znaniy' ); ?></a>
			</div>
		</div>
		<div class="footer-bottom">
			<span>© 2026 «<?php bloginfo( 'name' ); ?>» · znai-sad.ru</span>
			<span class="spacer">
				<a href="#" class="link-plain"><?php esc_html_e( 'Карта сайта', 'sad-znaniy' ); ?></a>
				<button type="button" id="bvi-toggle" class="link-plain" aria-pressed="false"><?php esc_html_e( 'Версия для слабовидящих', 'sad-znaniy' ); ?></button>
				<a href="#" class="link-plain"><?php esc_html_e( 'Политика конфиденциальности', 'sad-znaniy' ); ?></a>
			</span>
		</div>
	</footer>

</div><!-- .frame -->

<?php wp_footer(); ?>
</body>
</html>