<?php
/**
 * Sad Znaniy — точка входа темы.
 *
 * Файл намеренно тонкий: вся логика вынесена в includes/*.php.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAD_ZNANIY_VERSION', '1.0.0' );
define( 'SAD_ZNANIY_DIR', get_template_directory() );

require_once SAD_ZNANIY_DIR . '/includes/setup.php';
require_once SAD_ZNANIY_DIR . '/includes/helpers.php';
require_once SAD_ZNANIY_DIR . '/includes/assets.php';
require_once SAD_ZNANIY_DIR . '/includes/menus.php';
require_once SAD_ZNANIY_DIR . '/includes/cpt-calendar-event.php';
require_once SAD_ZNANIY_DIR . '/includes/cpt-plant.php';
require_once SAD_ZNANIY_DIR . '/includes/install.php';
require_once SAD_ZNANIY_DIR . '/includes/options.php';
require_once SAD_ZNANIY_DIR . '/includes/patterns.php';
require_once SAD_ZNANIY_DIR . '/includes/dashboard.php';
require_once SAD_ZNANIY_DIR . '/includes/calendar.php';
require_once SAD_ZNANIY_DIR . '/includes/seo.php';