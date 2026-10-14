<?php
/**
 * ЭТАП 7.3 — МОНИТОРИНГ ЦЕЛОСТНОСТИ (партия S4, 14.10.2026).
 *
 * Раз в сутки сверяем:
 *   • ядро WordPress — контрольные суммы файлов из официального набора
 *     (api.wordpress.org/core/checksums), находим изменённые и лишние;
 *   • файлы темы — по «базовой линии» (слепок md5), показываем изменения.
 *
 * Результат — в опции и на экране «🛡 Безопасность»: «целостность: ок» или
 * список изменённых файлов. Внешний запрос делается только при обновлении
 * набора сумм и по кнопке (не на обычных страницах сайта).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Настройки мониторинга.
 *
 * @return array
 */
function sad_znaniy_integrity_settings() {
	return apply_filters(
		'sad_znaniy_integrity_settings',
		array(
			'hour'    => 4,   // час ежедневной проверки
			'max_show' => 15, // сколько файлов показывать в списке
		)
	);
}

/**
 * Планирует ежедневную проверку целостности.
 *
 * @return void
 */
function sad_znaniy_integrity_schedule() {
	if ( wp_next_scheduled( 'sz_integrity_check' ) ) {
		return;
	}
	$settings = sad_znaniy_integrity_settings();
	$stamp    = strtotime( current_time( 'Y-m-d' ) . ' ' . (int) $settings['hour'] . ':30:00' );
	if ( ! $stamp || $stamp < time() ) {
		$stamp = time() + HOUR_IN_SECONDS;
	}
	wp_schedule_event( $stamp, 'daily', 'sz_integrity_check' );
}
add_action( 'init', 'sad_znaniy_integrity_schedule' );

/* === Ядро: контрольные суммы ============================================= */

/**
 * Набор контрольных сумм ядра для установленной версии (кэшируется в опции).
 *
 * @param bool $force Обновить набор принудительно.
 * @return array array( string $version, string $locale, array $map, string $note )
 */
function sad_znaniy_integrity_core_checksums( $force = false ) {
	$version = get_bloginfo( 'version' );
	$cached  = get_option( 'sad_znaniy_core_checksums' );

	if ( ! $force && is_array( $cached ) && isset( $cached['version'], $cached['map'] ) && $cached['version'] === $version ) {
		return $cached;
	}

	$locales = array( get_locale(), 'en_US' );
	foreach ( $locales as $locale ) {
		$url = add_query_arg(
			array(
				'version' => $version,
				'locale'  => $locale,
			),
			'https://api.wordpress.org/core/checksums/1.0/'
		);
		$res = wp_remote_get( $url, array( 'timeout' => 30 ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			continue;
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $json ) || empty( $json['checksums'] ) || ! is_array( $json['checksums'] ) ) {
			continue;
		}
		$data = array(
			'version' => $version,
			'locale'  => $locale,
			'map'     => $json['checksums'],
			'time'    => current_time( 'mysql' ),
			'note'    => 'файлов в наборе: ' . count( $json['checksums'] ),
		);
		update_option( 'sad_znaniy_core_checksums', $data, false );
		return $data;
	}

	return array(
		'version' => $version,
		'locale'  => '',
		'map'     => array(),
		'time'    => current_time( 'mysql' ),
		'note'    => 'набор контрольных сумм недоступен (нет связи с api.wordpress.org)',
	);
}

/**
 * Сверяет файлы ядра с официальными контрольными суммами.
 *
 * @return array
 */
/**
 * Что считать нормой: проверяем только сам ядро (без wp-content), учитываем
 * осознанно удалённые файлы из S2 и служебный файл LocalWP.
 *
 * @return array
 */
function sad_znaniy_integrity_ignore() {
	return apply_filters(
		'sad_znaniy_integrity_ignore',
		array(
			'skip_prefixes' => array( 'wp-content/' ),
			'missing_ok'    => array( 'readme.html', 'license.txt', 'wp-config-sample.php' ),
			'extra_ok'      => array( 'local-xdebuginfo.php' ),
		)
	);
}

/**
 * Сверяет файлы ядра с официальными контрольными суммами.
 *
 * @return array
 */
function sad_znaniy_integrity_check_core() {
	$data = sad_znaniy_integrity_core_checksums();
	$out  = array(
		'version'  => $data['version'],
		'note'     => $data['note'],
		'checked'  => 0,
		'modified' => array(),
		'missing'  => array(),
		'extra'    => array(),
	);

	if ( empty( $data['map'] ) ) {
		return $out;
	}

	$ignore = sad_znaniy_integrity_ignore();

	foreach ( $data['map'] as $rel => $md5 ) {
		if ( in_array( $rel, $ignore['missing_ok'], true ) ) {
			continue; // удалено осознанно в S2: readme.html, license.txt, wp-config-sample.php
		}
		$is_core = true;
		foreach ( $ignore['skip_prefixes'] as $prefix ) {
			if ( 0 === strpos( $rel, $prefix ) ) {
				$is_core = false;
				break;
			}
		}
		if ( ! $is_core ) {
			continue; // wp-content (темы, языковые пакеты) — это не ядро
		}

		$file = ABSPATH . $rel;
		if ( ! is_file( $file ) ) {
			$out['missing'][] = $rel;
			continue;
		}
		$out['checked']++;
		if ( md5_file( $file ) !== $md5 ) {
			$out['modified'][] = $rel;
		}
	}

	// Лишние php-файлы в корне (кроме служебного файла LocalWP и wp-config).
	foreach ( glob( ABSPATH . '*.php' ) as $file ) {
		$rel = basename( $file );
		if ( isset( $data['map'][ $rel ] ) || 'wp-config.php' === $rel || in_array( $rel, $ignore['extra_ok'], true ) ) {
			continue;
		}
		$out['extra'][] = $rel;
	}

	return $out;
}

/* === Тема: базовая линия ================================================= */

/**
 * Хеши всех файлов темы (кроме служебных папок разработки).
 *
 * @return array
 */
function sad_znaniy_integrity_theme_hashes() {
	$dir    = rtrim( get_template_directory(), '/\\' );
	$skip   = array( '/_tools/', '/_docs/', '/_design/', '/node_modules/', '/.git/' );
	$map    = array();
	$it     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = str_replace( '\\', '/', $file->getPathname() );
		$bad  = false;
		foreach ( $skip as $needle ) {
			if ( false !== strpos( $path, $needle ) ) {
				$bad = true;
				break;
			}
		}
		if ( $bad || ! $file->isFile() ) {
			continue;
		}
		$rel        = ltrim( substr( $path, strlen( $dir ) ), '/' );
		$map[ $rel ] = md5_file( $path );
	}
	ksort( $map );
	return $map;
}

/**
 * Обновляет базовую линию темы (делается осознанно: после своих правок).
 *
 * @return int Сколько файлов запомнили.
 */
function sad_znaniy_integrity_theme_baseline() {
	$map = sad_znaniy_integrity_theme_hashes();
	update_option(
		'sad_znaniy_theme_baseline',
		array(
			'time' => current_time( 'mysql' ),
			'map'  => $map,
		),
		false
	);
	return count( $map );
}

/**
 * Сравнивает файлы темы с базовой линией.
 *
 * @return array
 */
function sad_znaniy_integrity_check_theme() {
	$baseline = get_option( 'sad_znaniy_theme_baseline' );
	$now      = sad_znaniy_integrity_theme_hashes();
	$out      = array(
		'baseline_time' => '',
		'changed'       => array(),
		'added'         => array(),
		'removed'       => array(),
		'files'         => count( $now ),
	);

	if ( ! is_array( $baseline ) || empty( $baseline['map'] ) ) {
		$out['baseline_time'] = 'базовая линия ещё не снята';
		return $out;
	}

	$out['baseline_time'] = (string) $baseline['time'];
	$old                  = $baseline['map'];

	foreach ( $now as $rel => $hash ) {
		if ( ! isset( $old[ $rel ] ) ) {
			$out['added'][] = $rel;
		} elseif ( $old[ $rel ] !== $hash ) {
			$out['changed'][] = $rel;
		}
	}
	foreach ( $old as $rel => $hash ) {
		if ( ! isset( $now[ $rel ] ) ) {
			$out['removed'][] = $rel;
		}
	}

	return $out;
}

/* === Общая проверка ====================================================== */

/**
 * Выполняет обе проверки и сохраняет результат для экрана.
 *
 * @param bool $force_checksums Обновить набор сумм ядра принудительно.
 * @return array
 */
function sad_znaniy_integrity_run( $force_checksums = false ) {
	if ( $force_checksums ) {
		sad_znaniy_integrity_core_checksums( true );
	}

	$core   = sad_znaniy_integrity_check_core();
	$theme  = sad_znaniy_integrity_check_theme();
	$issues = count( $core['modified'] ) + count( $core['missing'] ) + count( $core['extra'] )
		+ count( $theme['changed'] ) + count( $theme['added'] ) + count( $theme['removed'] );

	$status = array(
		'time'   => current_time( 'mysql' ),
		'core'   => $core,
		'theme'  => $theme,
		'issues' => $issues,
		'ok'     => ( 0 === $issues && ! empty( $core['checked'] ) ),
	);

	update_option( 'sad_znaniy_integrity_status', $status, false );
	return $status;
}

/**
 * Ежедневная проверка по расписанию.
 *
 * @return void
 */
function sad_znaniy_integrity_cron() {
	sad_znaniy_integrity_run( false );
}
add_action( 'sz_integrity_check', 'sad_znaniy_integrity_cron' );

/**
 * Короткая строка статуса для экрана и отчётов.
 *
 * @return string
 */
function sad_znaniy_integrity_summary() {
	$status = get_option( 'sad_znaniy_integrity_status' );
	if ( ! is_array( $status ) ) {
		return 'проверок ещё не было';
	}
	$when = isset( $status['time'] ) ? (string) $status['time'] : '';
	if ( ! empty( $status['ok'] ) ) {
		return 'целостность: ок (проверено ' . $when . ', файлов ядра: ' . (int) $status['core']['checked'] . ')';
	}
	return 'целостность: изменения — ' . (int) $status['issues'] . ' (проверено ' . $when . ')';
}

/* === Панель на экране «🛡 Безопасность» ================================== */

/**
 * Панель «Целостность файлов»: статус, кнопки и списки отличий.
 *
 * @return void
 */
function sad_znaniy_integrity_panel() {
	$status = get_option( 'sad_znaniy_integrity_status' );
	$max    = (int) sad_znaniy_integrity_settings()['max_show'];

	echo '<h3>Целостность файлов</h3>';
	echo '<form method="post" style="margin:6px 0 10px;">';
	wp_nonce_field( 'sz_security_action' );
	echo '<input type="hidden" name="sz_security_action" value="integrity_now">'
		. '<button class="button button-primary">Проверить сейчас</button> '
		. '<span class="description">сверяет ядро с официальными суммами и файлы темы с базовой линией; проверка идёт и сама — ежедневно в 04:30</span></form>';
	echo '<form method="post" style="margin:0 0 12px;">';
	wp_nonce_field( 'sz_security_action' );
	echo '<input type="hidden" name="sz_security_action" value="integrity_baseline">'
		. '<button class="button">Обновить базовую линию темы</button> '
		. '<span class="description">нажимайте после своих правок темы — тогда проверка снова покажет «ок»</span></form>';

	if ( ! is_array( $status ) ) {
		echo '<p>Проверок ещё не было.</p>';
		return;
	}

	$core  = isset( $status['core'] ) && is_array( $status['core'] ) ? $status['core'] : array();
	$theme = isset( $status['theme'] ) && is_array( $status['theme'] ) ? $status['theme'] : array();
	$clean = static function ( $arr ) {
		return ! is_array( $arr ) || 0 === count( $arr );
	};

	echo '<p><strong>' . esc_html( sad_znaniy_integrity_summary() ) . '</strong></p>';
	echo '<table class="widefat striped"><tbody>';
	echo '<tr><td>Ядро WordPress ' . esc_html( isset( $core['version'] ) ? (string) $core['version'] : '' ) . '</td><td>'
		. ( ( $clean( $core['modified'] ?? array() ) && $clean( $core['missing'] ?? array() ) && $clean( $core['extra'] ?? array() ) ) ? '✔ без изменений' : '❌ есть отличия' ) . '</td><td>'
		. esc_html( isset( $core['note'] ) ? (string) $core['note'] : '' ) . ', проверено файлов: ' . (int) ( $core['checked'] ?? 0 ) . '</td></tr>';
	echo '<tr><td>Файлы темы sad-znaniy</td><td>'
		. ( ( $clean( $theme['changed'] ?? array() ) && $clean( $theme['added'] ?? array() ) && $clean( $theme['removed'] ?? array() ) ) ? '✔ без изменений' : '❌ есть отличия' ) . '</td><td>файлов: '
		. (int) ( $theme['files'] ?? 0 ) . ', базовая линия: ' . esc_html( isset( $theme['baseline_time'] ) ? (string) $theme['baseline_time'] : '' ) . '</td></tr>';
	echo '</tbody></table>';

	$blocks = array(
		'Изменены в ядре'      => $core['modified'] ?? array(),
		'Пропали из ядра'      => $core['missing'] ?? array(),
		'Лишние php в корне'   => $core['extra'] ?? array(),
		'Изменены в теме'      => $theme['changed'] ?? array(),
		'Добавлены в теме'     => $theme['added'] ?? array(),
		'Удалены из темы'      => $theme['removed'] ?? array(),
	);
	foreach ( $blocks as $title => $list ) {
		if ( empty( $list ) ) {
			continue;
		}
		echo '<p><strong>' . esc_html( $title ) . ' (' . count( $list ) . '):</strong> <code>'
			. esc_html( implode( '</code>, <code>', array_slice( $list, 0, $max ) ) ) . '</code>'
			. ( count( $list ) > $max ? ' …' : '' ) . '</p>';
	}
}
