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
require_once SAD_ZNANIY_DIR . '/includes/security.php';
require_once SAD_ZNANIY_DIR . '/includes/backup.php';
require_once SAD_ZNANIY_DIR . '/includes/integrity.php';
require_once SAD_ZNANIY_DIR . '/includes/mail-log.php';
require_once SAD_ZNANIY_DIR . '/includes/helpers.php';
require_once SAD_ZNANIY_DIR . '/includes/cache.php';
require_once SAD_ZNANIY_DIR . '/includes/ical.php';
require_once SAD_ZNANIY_DIR . '/includes/assets.php';
require_once SAD_ZNANIY_DIR . '/includes/menus.php';
require_once SAD_ZNANIY_DIR . '/includes/cpt-calendar-event.php';
require_once SAD_ZNANIY_DIR . '/includes/cpt-plant.php';
require_once SAD_ZNANIY_DIR . '/includes/admin-image-hints.php';
require_once SAD_ZNANIY_DIR . '/includes/image-compress.php';
require_once SAD_ZNANIY_DIR . '/includes/image-nextgen.php';
require_once SAD_ZNANIY_DIR . '/includes/excerpt.php';
require_once SAD_ZNANIY_DIR . '/includes/cards.php';
require_once SAD_ZNANIY_DIR . '/includes/install.php';
require_once SAD_ZNANIY_DIR . '/includes/options.php';
require_once SAD_ZNANIY_DIR . '/includes/patterns.php';
require_once SAD_ZNANIY_DIR . '/includes/dashboard.php';
require_once SAD_ZNANIY_DIR . '/includes/calendar.php';
require_once SAD_ZNANIY_DIR . '/includes/programmatic.php';
require_once SAD_ZNANIY_DIR . '/includes/programmatic-admin.php';
require_once SAD_ZNANIY_DIR . '/includes/seo.php';
require_once SAD_ZNANIY_DIR . '/includes/seo-hub.php';
require_once SAD_ZNANIY_DIR . '/includes/seo-keys.php';
require_once SAD_ZNANIY_DIR . '/includes/seo-index.php';
require_once SAD_ZNANIY_DIR . '/includes/seo-views.php';
require_once SAD_ZNANIY_DIR . '/includes/calculators.php';
require_once SAD_ZNANIY_DIR . '/includes/moon.php';
require_once SAD_ZNANIY_DIR . '/includes/season.php';
require_once SAD_ZNANIY_DIR . '/includes/region.php';
require_once SAD_ZNANIY_DIR . '/includes/planner.php';
require_once SAD_ZNANIY_DIR . '/includes/planner-cabinet.php';
require_once SAD_ZNANIY_DIR . '/includes/planner-ics.php';
require_once SAD_ZNANIY_DIR . '/includes/tool-icons.php';
require_once SAD_ZNANIY_DIR . '/includes/site-rail.php';
require_once SAD_ZNANIY_DIR . '/includes/related-tools.php';
require_once SAD_ZNANIY_DIR . '/includes/planner-mail.php';
require_once SAD_ZNANIY_DIR . '/includes/calculators-seo.php';
require_once SAD_ZNANIY_DIR . '/includes/social/db.php';
require_once SAD_ZNANIY_DIR . '/includes/social/settings.php';
require_once SAD_ZNANIY_DIR . '/includes/social/queue.php';
require_once SAD_ZNANIY_DIR . '/includes/social/sender.php';
require_once SAD_ZNANIY_DIR . '/includes/social/aff.php';
require_once SAD_ZNANIY_DIR . '/includes/social/admin.php';
