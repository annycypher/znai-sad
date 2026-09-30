<?php
/**
 * Шапка сайта: <head>, SVG-символ логотипа и шапка-пилюля.
 *
 * Классы полностью соответствуют макету _design/index.html (контракт).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main"><?php esc_html_e( 'Перейти к содержанию', 'sad-znaniy' ); ?></a>

<div class="ambient" aria-hidden="true"></div>

<svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">
	<symbol id="logo-sad" viewBox="0 0 44.17 51.39">
		<path fill="currentColor" fill-rule="evenodd" d="M22.09 51.39c0.05,0 0.11,0 0.16,0 3.99,-0.05 8.43,-1.2 11.36,-3.45 1.19,-0.9 2.57,-2.29 3.41,-3.52 0.3,-0.45 0.62,-0.88 0.9,-1.37 1.9,-3.25 2.88,-6.92 2.87,-10.88 -3.89,-0.13 -7.99,1.74 -10.23,3.51 -2.51,1.98 -4.27,4.1 -5.77,7.95l0.01 -11.77c0.78,-0.48 1.7,-1.05 2.65,-1.6 2.27,-1.32 1.74,-1.37 2.97,-0.88 2.48,1.01 4.91,1.28 7.47,0.42 2.12,-0.7 3.63,-2.09 4.8,-3.99 1.2,-1.93 1.58,-4.46 1.47,-7.26 -1.9,-1.06 -3.49,-2.06 -6.23,-2.18 -5.57,-0.24 -9.48,4 -9.83,9.07 -0.06,1 0.03,0.89 -0.72,1.35 -0.54,0.32 -2.27,1.44 -2.68,1.48 -0.05,-1.61 -0.01,-3.28 -0.01,-4.9 0,-0.42 0,-0.84 0,-1.25 -0.01,-1.01 0.04,-0.46 1.78,-1.9 1.77,-1.47 3.16,-3.24 4.04,-5.76 0.92,-2.65 0.52,-5.91 -0.62,-8.18 -1.07,-2.13 -2.83,-3.93 -4.61,-5.1 -1.42,-0.94 -2.87,-1.21 -3.19,-1.18 -0.33,-0.03 -1.78,0.24 -3.2,1.18 -1.77,1.17 -3.53,2.97 -4.61,5.1 -1.14,2.27 -1.54,5.53 -0.61,8.18 0.88,2.52 2.26,4.29 4.04,5.76 1.73,1.44 1.79,0.89 1.78,1.9 -0.01,0.41 -0.01,0.83 -0.01,1.25 0,1.62 0.04,3.29 -0.01,4.9 -0.41,-0.04 -2.14,-1.16 -2.68,-1.48 -0.75,-0.46 -0.65,-0.35 -0.72,-1.35 -0.34,-5.07 -4.26,-9.31 -9.82,-9.07 -2.75,0.12 -4.33,1.12 -6.23,2.18 -0.12,2.8 0.26,5.33 1.46,7.26 1.18,1.9 2.68,3.29 4.8,3.99 2.56,0.86 4.99,0.59 7.48,-0.42 1.22,-0.49 0.69,-0.44 2.97,0.88 0.94,0.55 1.86,1.12 2.65,1.6l0.01 11.77c-1.51,-3.85 -3.27,-5.97 -5.77,-7.95 -2.25,-1.77 -6.35,-3.64 -10.23,-3.51 -0.01,3.96 0.96,7.63 2.86,10.88 0.29,0.49 0.6,0.92 0.91,1.37 0.83,1.23 2.22,2.62 3.4,3.52 2.93,2.25 7.38,3.4 11.36,3.45 0.06,0 0.11,0 0.17,0z"/>
	</symbol>
</svg>

<svg class="leaf leaf-1" aria-hidden="true"><use href="#logo-sad"/></svg>
<svg class="leaf leaf-2" aria-hidden="true"><use href="#logo-sad"/></svg>

<div class="frame">

	<!-- ================= ШАПКА ================= -->
	<header class="topbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo" aria-label="<?php esc_attr_e( 'Сад знаний — на главную', 'sad-znaniy' ); ?>">
			<span class="logo-mark">
				<svg width="28" height="33" viewBox="0 0 44.17 51.39" aria-hidden="true"><use href="#logo-sad"/></svg>
			</span>
			<span>
				<span class="logo-name"><?php bloginfo( 'name' ); ?></span><br>
				<span class="logo-tag"><?php esc_html_e( '· полезная база садовода ·', 'sad-znaniy' ); ?></span>
			</span>
		</a>

		<input type="checkbox" id="nav-toggle" class="nav-toggle-input" aria-label="<?php esc_attr_e( 'Открыть меню', 'sad-znaniy' ); ?>">
		<label for="nav-toggle" class="nav-burger" aria-hidden="true">☰</label>

		<nav class="nav" aria-label="<?php esc_attr_e( 'Основное меню', 'sad-znaniy' ); ?>">
			<?php sad_znaniy_main_menu(); ?>
		</nav>

		<div class="topbar-actions">
			<button class="icon-btn" aria-label="<?php esc_attr_e( 'Поиск по сайту', 'sad-znaniy' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
			</button>
			<a href="<?php echo esc_url( home_url( '/wp-admin/profile.php' ) ); ?>" class="btn-cab" aria-label="<?php esc_attr_e( 'Личный кабинет', 'sad-znaniy' ); ?>">
				<span class="cab-ava" aria-hidden="true">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
				</span>
				<span><?php esc_html_e( 'Кабинет', 'sad-znaniy' ); ?></span>
			</a>
		</div>
	</header>

	<main id="main" class="inner">