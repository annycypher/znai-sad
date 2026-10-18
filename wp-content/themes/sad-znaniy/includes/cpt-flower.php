<?php
/**
 * CPT «Цветок» (flower) — справочник конструктора букета.
 *
 * Этап 6.5, Партия 1. Трио: (1) значения цветов публичны и используются
 * конструктором; (2) поля и фото правятся в админке; (3) раздел в ADMINGUIDE.
 *
 * Спека: _design/bouquet-spec.md, §1–§1б.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует тип записи «Цветок».
 */
function sad_znaniy_register_flower() {
	register_post_type(
		'flower',
		array(
			'labels'        => array(
				'name'               => __( 'Цветы и значения', 'sad-znaniy' ),
				'singular_name'      => __( 'Цветок', 'sad-znaniy' ),
				'add_new'            => __( 'Добавить цветок', 'sad-znaniy' ),
				'add_new_item'       => __( 'Новый цветок', 'sad-znaniy' ),
				'edit_item'          => __( 'Редактировать цветок', 'sad-znaniy' ),
				'new_item'           => __( 'Новый цветок', 'sad-znaniy' ),
				'view_item'          => __( 'Просмотреть цветок', 'sad-znaniy' ),
				'search_items'       => __( 'Искать цветы', 'sad-znaniy' ),
				'not_found'          => __( 'Цветов не найдено', 'sad-znaniy' ),
				'not_found_in_trash' => __( 'В корзине цветов нет', 'sad-znaniy' ),
				'all_items'          => __( 'Все цветы', 'sad-znaniy' ),
				'menu_name'          => __( 'Цветы и значения', 'sad-znaniy' ),
			),
			'public'        => true,
			'show_in_rest'  => true,
			'has_archive'   => false,
			'rewrite'       => array(
				'slug'       => 'tsvety-znachenie',
				'with_front' => false,
			),
			'menu_icon'     => 'dashicons-heart',
			'menu_position' => 23,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
		)
	);
}
add_action( 'init', 'sad_znaniy_register_flower' );

/**
 * Метабоксы цветка.
 */
function sad_znaniy_flower_add_meta_boxes() {
	add_meta_box(
		'sad-znaniy-flower-value',
		__( 'Значение цветка', 'sad-znaniy' ),
		'sad_znaniy_flower_value_box',
		'flower',
		'normal',
		'high'
	);
	add_meta_box(
		'sad-znaniy-flower-photo',
		__( 'Фото', 'sad-znaniy' ),
		'sad_znaniy_flower_photo_box',
		'flower',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'sad_znaniy_flower_add_meta_boxes' );

/**
 * Поле «Значение цветка».
 *
 * @param WP_Post $post Запись.
 */
function sad_znaniy_flower_value_box( $post ) {
	wp_nonce_field( 'sad_znaniy_flower_save', 'sad_znaniy_flower_nonce' );

	$meaning  = (string) get_post_meta( $post->ID, '_sz_fl_meaning', true );
	$desc     = (string) get_post_meta( $post->ID, '_sz_fl_desc', true );
	$cats     = array_filter( explode( ',', (string) get_post_meta( $post->ID, '_sz_fl_category', true ) ) );
	$rare     = (string) get_post_meta( $post->ID, '_sz_fl_rare', true );
	$season   = (string) get_post_meta( $post->ID, '_sz_fl_season', true );
	$plant_id = (int) get_post_meta( $post->ID, '_sz_fl_plant', true );
	$bad      = (string) get_post_meta( $post->ID, '_sz_fl_bad', true );
	$warn     = (string) get_post_meta( $post->ID, '_sz_fl_warn', true );
	$alias    = (string) get_post_meta( $post->ID, '_sz_fl_alias', true );
	$variant  = (string) get_post_meta( $post->ID, '_sz_fl_variant_of', true );
	$genitive = (string) get_post_meta( $post->ID, '_sz_fl_genitive', true );
	$sort     = (int) get_post_meta( $post->ID, '_sz_fl_sort', true );
	$desc_fp  = (string) get_post_meta( $post->ID, '_sz_fl_desc_fp', true );

	$plants = get_posts(
		array(
			'post_type'      => 'plant',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	?>
	<p><strong><?php esc_html_e( 'Значимость (1–2 слова)', 'sad-znaniy' ); ?></strong><br>
		<input type="text" class="widefat" name="sz_fl_meaning" value="<?php echo esc_attr( $meaning ); ?>"></p>

	<p><strong><?php esc_html_e( 'Описание (2–3 предложения)', 'sad-znaniy' ); ?></strong><br>
		<textarea class="widefat" rows="4" name="sz_fl_desc"><?php echo esc_textarea( $desc ); ?></textarea></p>

	<p><strong><?php esc_html_e( 'Категории значения', 'sad-znaniy' ); ?></strong><br>
		<?php foreach ( sad_znaniy_bouquet_categories() as $key => $label ) : ?>
			<label style="display:inline-block;margin:0 14px 6px 0;">
				<input type="checkbox" name="sz_fl_category[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $cats, true ) ); ?>>
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>
	</p>

	<p>
		<label><input type="checkbox" name="sz_fl_rare" value="1" <?php checked( '1', $rare ); ?>> <?php esc_html_e( 'Редкий (бейдж ✦)', 'sad-znaniy' ); ?></label>
		&nbsp;&nbsp;
		<label><input type="checkbox" name="sz_fl_desc_fp" value="1" <?php checked( '1', $desc_fp ); ?>> <?php esc_html_e( 'ФП: текст не вычитан', 'sad-znaniy' ); ?></label>
	</p>

	<p><strong><?php esc_html_e( 'Сезон цветения (месяцы через запятую)', 'sad-znaniy' ); ?></strong><br>
		<input type="text" class="regular-text" name="sz_fl_season" value="<?php echo esc_attr( $season ); ?>" placeholder="5,6,7"></p>

	<p><strong><?php esc_html_e( 'Связь с растением', 'sad-znaniy' ); ?></strong><br>
		<select name="sz_fl_plant" class="regular-text">
			<option value="0" <?php selected( 0, $plant_id ); ?>><?php esc_html_e( '— нет связи —', 'sad-znaniy' ); ?></option>
			<?php foreach ( $plants as $plant ) : ?>
				<option value="<?php echo esc_attr( (string) $plant->ID ); ?>" <?php selected( (int) $plant->ID, $plant_id ); ?>><?php echo esc_html( get_the_title( $plant ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>

	<p><strong><?php esc_html_e( 'Конфликты: слаги цветов через запятую', 'sad-znaniy' ); ?></strong><br>
		<input type="text" class="widefat" name="sz_fl_bad" value="<?php echo esc_attr( $bad ); ?>" placeholder="landysh,roza-krasnaya"></p>

	<p><strong><?php esc_html_e( 'Мягкое предупреждение (текст для админки)', 'sad-znaniy' ); ?></strong><br>
		<textarea class="widefat" rows="2" name="sz_fl_warn"><?php echo esc_textarea( $warn ); ?></textarea></p>

	<p><strong><?php esc_html_e( 'Алиасы (через запятую)', 'sad-znaniy' ); ?></strong><br>
		<input type="text" class="regular-text" name="sz_fl_alias" value="<?php echo esc_attr( $alias ); ?>" placeholder="лютик"></p>

	<p><strong><?php esc_html_e( 'Вариант базового цветка (slug)', 'sad-znaniy' ); ?></strong><br>
		<input type="text" class="regular-text" name="sz_fl_variant_of" value="<?php echo esc_attr( $variant ); ?>" placeholder="roza-krasnaya"></p>

	<p><strong><?php esc_html_e( 'Родительный падеж (для названий)', 'sad-znaniy' ); ?></strong><br>
		<input type="text" class="regular-text" name="sz_fl_genitive" value="<?php echo esc_attr( $genitive ); ?>" placeholder="розы"></p>

	<p><strong><?php esc_html_e( 'Порядок в справочнике', 'sad-znaniy' ); ?></strong><br>
		<input type="number" name="sz_fl_sort" value="<?php echo esc_attr( (string) $sort ); ?>" min="0" step="10"></p>
	<?php
}

/**
 * Блок «Фото» в сайдбаре записи.
 *
 * @param WP_Post $post Запись.
 */
function sad_znaniy_flower_photo_box( $post ) {
	$photo = sad_znaniy_flower_photo_url( $post->ID );
	$path  = (string) get_post_meta( $post->ID, '_sz_fl_photo', true );

	if ( $photo ) {
		echo '<p><img src="' . esc_url( $photo ) . '" alt="" style="max-width:100%;height:auto;border-radius:50%;"></p>';
	} else {
		echo '<p>' . esc_html__( 'Фото нет — в конструкторе будет стеклянный круг с буквой.', 'sad-znaniy' ) . '</p>';
	}

	if ( '' !== $path ) {
		echo '<p><code>' . esc_html( $path ) . '</code></p>';
	}

	$scan = add_query_arg(
		array(
			'post_type' => 'flower',
			'page'      => 'sz-flower-photo-scan',
		),
		admin_url( 'edit.php' )
	);
	echo '<p><a class="button" href="' . esc_url( $scan ) . '">' . esc_html__( 'Открыть «Фото-скан»', 'sad-znaniy' ) . '</a></p>';
	echo '<p class="description">' . esc_html__( 'Миниатюра записи важнее файла из папки. Круг делается выводом, файл не обрезается.', 'sad-znaniy' ) . '</p>';
}

/**
 * Сохранение метаполей цветка.
 *
 * @param int $post_id ID записи.
 */
function sad_znaniy_flower_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['sad_znaniy_flower_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( (string) $_POST['sad_znaniy_flower_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'sad_znaniy_flower_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$meaning = isset( $_POST['sz_fl_meaning'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sz_fl_meaning'] ) ) : '';
	$desc    = isset( $_POST['sz_fl_desc'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['sz_fl_desc'] ) ) : '';
	$warn    = isset( $_POST['sz_fl_warn'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['sz_fl_warn'] ) ) : '';
	$alias   = isset( $_POST['sz_fl_alias'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sz_fl_alias'] ) ) : '';
	$bad     = isset( $_POST['sz_fl_bad'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sz_fl_bad'] ) ) : '';
	$genitive = isset( $_POST['sz_fl_genitive'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sz_fl_genitive'] ) ) : '';
	$variant = isset( $_POST['sz_fl_variant_of'] ) ? sanitize_title( wp_unslash( (string) $_POST['sz_fl_variant_of'] ) ) : '';
	$sort    = isset( $_POST['sz_fl_sort'] ) ? absint( $_POST['sz_fl_sort'] ) : 0;
	$rare    = isset( $_POST['sz_fl_rare'] ) ? '1' : '';
	$desc_fp = isset( $_POST['sz_fl_desc_fp'] ) ? '1' : '';

	$raw_cats = ( isset( $_POST['sz_fl_category'] ) && is_array( $_POST['sz_fl_category'] ) ) ? wp_unslash( $_POST['sz_fl_category'] ) : array();
	$allowed  = array_keys( sad_znaniy_bouquet_categories() );
	$cats     = array();
	foreach ( $raw_cats as $cat ) {
		$cat = sanitize_key( (string) $cat );
		if ( in_array( $cat, $allowed, true ) ) {
			$cats[] = $cat;
		}
	}

	$raw_season = isset( $_POST['sz_fl_season'] ) ? (string) wp_unslash( $_POST['sz_fl_season'] ) : '';
	$months     = array();
	foreach ( explode( ',', $raw_season ) as $month ) {
		$month = (int) trim( $month );
		if ( $month >= 1 && $month <= 12 ) {
			$months[] = $month;
		}
	}
	$months = array_values( array_unique( $months ) );
	sort( $months );

	$plant_id = isset( $_POST['sz_fl_plant'] ) ? absint( $_POST['sz_fl_plant'] ) : 0;
	if ( $plant_id > 0 && 'plant' !== get_post_type( $plant_id ) ) {
		$plant_id = 0;
	}

	update_post_meta( $post_id, '_sz_fl_meaning', $meaning );
	update_post_meta( $post_id, '_sz_fl_desc', $desc );
	update_post_meta( $post_id, '_sz_fl_category', implode( ',', $cats ) );
	update_post_meta( $post_id, '_sz_fl_rare', $rare );
	update_post_meta( $post_id, '_sz_fl_desc_fp', $desc_fp );
	update_post_meta( $post_id, '_sz_fl_season', implode( ',', $months ) );
	update_post_meta( $post_id, '_sz_fl_plant', $plant_id );
	update_post_meta( $post_id, '_sz_fl_bad', $bad );
	update_post_meta( $post_id, '_sz_fl_warn', $warn );
	update_post_meta( $post_id, '_sz_fl_alias', $alias );
	update_post_meta( $post_id, '_sz_fl_variant_of', $variant );
	update_post_meta( $post_id, '_sz_fl_genitive', $genitive );
	update_post_meta( $post_id, '_sz_fl_sort', $sort );
}
add_action( 'save_post_flower', 'sad_znaniy_flower_save_meta' );

/**
 * Колонки списка цветов.
 *
 * @param array $columns Существующие колонки.
 * @return array
 */
function sad_znaniy_flower_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['sz_fl_photo'] = __( 'Фото', 'sad-znaniy' );
		}
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['sz_fl_category'] = __( 'Категория', 'sad-znaniy' );
			$new['sz_fl_rare']     = __( 'Редкость', 'sad-znaniy' );
			$new['sz_fl_season']   = __( 'Сезон', 'sad-znaniy' );
			$new['sz_fl_plant']    = __( 'Растение', 'sad-znaniy' );
		}
	}
	return $new;
}
add_filter( 'manage_flower_posts_columns', 'sad_znaniy_flower_columns' );

/**
 * Значения колонок списка цветов.
 *
 * @param string $column  Колонка.
 * @param int    $post_id ID записи.
 */
function sad_znaniy_flower_column_content( $column, $post_id ) {
	if ( 'sz_fl_photo' === $column ) {
		$url = sad_znaniy_flower_photo_url( $post_id );
		if ( $url ) {
			echo '<img src="' . esc_url( $url ) . '" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">';
		} else {
			echo '&mdash;';
		}
		return;
	}

	if ( 'sz_fl_category' === $column ) {
		$cats  = array_filter( explode( ',', (string) get_post_meta( $post_id, '_sz_fl_category', true ) ) );
		$all   = sad_znaniy_bouquet_categories();
		$parts = array();
		foreach ( $cats as $cat ) {
			if ( isset( $all[ $cat ] ) ) {
				$parts[] = (string) $all[ $cat ];
			}
		}
		echo $parts ? esc_html( implode( ', ', $parts ) ) : '&mdash;';
		return;
	}

	if ( 'sz_fl_rare' === $column ) {
		echo '1' === (string) get_post_meta( $post_id, '_sz_fl_rare', true ) ? '✦' : '&mdash;';
		return;
	}

	if ( 'sz_fl_season' === $column ) {
		$months = array_filter( explode( ',', (string) get_post_meta( $post_id, '_sz_fl_season', true ) ) );
		echo $months ? esc_html( implode( ', ', $months ) ) : '&mdash;';
		return;
	}

	if ( 'sz_fl_plant' === $column ) {
		$plant_id = (int) get_post_meta( $post_id, '_sz_fl_plant', true );
		$plant    = $plant_id ? get_post( $plant_id ) : null;
		if ( $plant && 'plant' === $plant->post_type ) {
			echo '<a href="' . esc_url( (string) get_edit_post_link( $plant->ID ) ) . '">' . esc_html( get_the_title( $plant ) ) . '</a>';
		} else {
			echo '&mdash;';
		}
	}
}
add_action( 'manage_flower_posts_custom_column', 'sad_znaniy_flower_column_content', 10, 2 );

/**
 * URL фото цветка: привязанный файл → миниатюра → пусто.
 *
 * @param int $post_id ID записи.
 * @return string
 */
function sad_znaniy_flower_photo_url( $post_id ) {
	$rel = (string) get_post_meta( $post_id, '_sz_fl_photo', true );
	if ( '' !== $rel ) {
		$rel = ltrim( $rel, '/' );
		if ( file_exists( get_template_directory() . '/' . $rel ) ) {
			return get_template_directory_uri() . '/' . $rel;
		}
	}

	if ( has_post_thumbnail( $post_id ) ) {
		$url = get_the_post_thumbnail_url( $post_id, 'medium' );
		if ( $url ) {
			return (string) $url;
		}
	}

	return '';
}


/**
 * Файлы в папке assets/img/flowers/.
 *
 * @return array Имена файлов.
 */
function sad_znaniy_flower_scan_dir() {
	$dir = get_template_directory() . '/assets/img/flowers';
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$out = array();
	foreach ( (array) scandir( $dir ) as $file ) {
		if ( '.' === $file || '..' === $file ) {
			continue;
		}
		$ext = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			$out[] = $file;
		}
	}
	sort( $out );
	return $out;
}

/**
 * Ключи для сопоставления файла с цветком.
 *
 * @param WP_Post $post Запись.
 * @return array
 */
function sad_znaniy_flower_photo_candidates( $post ) {
	$title = (string) get_the_title( $post );
	$parts = preg_split( '/\s+/u', trim( $title ) );
	$first = ( is_array( $parts ) && isset( $parts[0] ) ) ? (string) $parts[0] : '';

	$out = array(
		(string) $post->post_name,
		sad_znaniy_translit( $title ),
		sad_znaniy_translit( $first ),
	);
	return array_values( array_unique( array_filter( $out ) ) );
}

/**
 * Ищет файл для цветка (регистронезависимо, по первому слову).
 *
 * @param WP_Post $post  Запись.
 * @param array   $files Файлы папки.
 * @return string Имя файла или пустая строка.
 */
function sad_znaniy_flower_find_file( $post, $files ) {
	$candidates = sad_znaniy_flower_photo_candidates( $post );
	foreach ( (array) $files as $file ) {
		$key = sad_znaniy_translit( (string) pathinfo( $file, PATHINFO_FILENAME ) );
		foreach ( $candidates as $candidate ) {
			if ( $key === $candidate || 0 === strpos( $key, $candidate . '-' ) || 0 === strpos( $candidate, $key . '-' ) ) {
				return (string) $file;
			}
		}
	}
	return '';
}

/**
 * Пункт меню «Фото-скан».
 */
function sad_znaniy_flower_photo_menu() {
	add_submenu_page(
		'edit.php?post_type=flower',
		__( 'Фото-скан', 'sad-znaniy' ),
		__( 'Фото-скан', 'sad-znaniy' ),
		'manage_options',
		'sz-flower-photo-scan',
		'sad_znaniy_flower_photo_page'
	);
}
add_action( 'admin_menu', 'sad_znaniy_flower_photo_menu' );

/**
 * Экран «Фото-скан»: сопоставление файлов папки с цветами.
 */
function sad_znaniy_flower_photo_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice = '';
	if ( isset( $_POST['sz_flower_photo_action'] ) ) {
		check_admin_referer( 'sz_flower_photo_scan' );
		$fid    = isset( $_POST['flower_id'] ) ? absint( $_POST['flower_id'] ) : 0;
		$action = isset( $_POST['sz_flower_photo_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['sz_flower_photo_action'] ) ) : '';

		if ( $fid > 0 && 'flower' === get_post_type( $fid ) ) {
			if ( 'clear' === $action ) {
				delete_post_meta( $fid, '_sz_fl_photo' );
				$notice = __( 'Фото отвязано.', 'sad-znaniy' );
			} else {
				$file  = isset( $_POST['file'] ) ? wp_basename( sanitize_text_field( wp_unslash( (string) $_POST['file'] ) ) ) : '';
				$files = sad_znaniy_flower_scan_dir();
				if ( in_array( $file, $files, true ) ) {
					update_post_meta( $fid, '_sz_fl_photo', 'assets/img/flowers/' . $file );
					$notice = __( 'Фото привязано.', 'sad-znaniy' );
				}
			}
		}
	}

	$files = sad_znaniy_flower_scan_dir();
	$dir   = get_template_directory() . '/assets/img/flowers';
	$posts = get_posts(
		array(
			'post_type'      => 'flower',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Фото-скан цветов', 'sad-znaniy' ); ?></h1>
		<p><?php esc_html_e( 'Сканируется папка assets/img/flowers/. Файл ищется по слагу, названию и первому слову (транслитерация, регистр не важен). Привязка не копирует файл — только путь.', 'sad-znaniy' ); ?></p>

		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! is_dir( $dir ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Папка assets/img/flowers/ не создана. Создайте её и положите файлы.', 'sad-znaniy' ); ?></p></div>
		<?php elseif ( ! $files ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'В папке нет файлов jpg/jpeg/png/webp.', 'sad-znaniy' ); ?></p></div>
		<?php endif; ?>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Цветок', 'sad-znaniy' ); ?></th>
					<th><?php esc_html_e( 'Сейчас', 'sad-znaniy' ); ?></th>
					<th><?php esc_html_e( 'Найденный файл', 'sad-znaniy' ); ?></th>
					<th><?php esc_html_e( 'Действие', 'sad-znaniy' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $posts ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Цветов пока нет.', 'sad-znaniy' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $posts as $post ) : ?>
					<?php
					$current   = (string) get_post_meta( $post->ID, '_sz_fl_photo', true );
					$matched   = sad_znaniy_flower_find_file( $post, $files );
					$has_thumb = has_post_thumbnail( $post->ID );
					?>
					<tr>
						<td><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong><br><code><?php echo esc_html( $post->post_name ); ?></code></td>
						<td>
							<?php
							if ( '' !== $current ) {
								echo '<code>' . esc_html( wp_basename( $current ) ) . '</code>';
							} elseif ( $has_thumb ) {
								esc_html_e( 'миниатюра записи', 'sad-znaniy' );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td>
							<?php if ( '' !== $matched ) : ?>
								<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/flowers/' . rawurlencode( $matched ) ); ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:8px;">
								<code><?php echo esc_html( $matched ); ?></code>
							<?php else : ?>
								<span style="color:#b32d2e;"><?php esc_html_e( 'не найден', 'sad-znaniy' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( '' !== $matched && 'assets/img/flowers/' . $matched !== $current ) : ?>
								<form method="post" style="display:inline-block;margin:0 6px 4px 0;">
									<?php wp_nonce_field( 'sz_flower_photo_scan' ); ?>
									<input type="hidden" name="flower_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">
									<input type="hidden" name="file" value="<?php echo esc_attr( $matched ); ?>">
									<button type="submit" class="button button-primary" name="sz_flower_photo_action" value="attach"><?php esc_html_e( 'Привязать', 'sad-znaniy' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( '' !== $current ) : ?>
								<form method="post" style="display:inline-block;margin:0;">
									<?php wp_nonce_field( 'sz_flower_photo_scan' ); ?>
									<input type="hidden" name="flower_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">
									<button type="submit" class="button" name="sz_flower_photo_action" value="clear"><?php esc_html_e( 'Очистить', 'sad-znaniy' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * ID карточки растения по слагу.
 *
 * @param string $slug Слаг.
 * @return int
 */
function sad_znaniy_flower_plant_id( $slug ) {
	if ( '' === (string) $slug ) {
		return 0;
	}
	$plant = get_page_by_path( (string) $slug, OBJECT, 'plant' );
	return $plant ? (int) $plant->ID : 0;
}

/**
 * Создаёт записи цветов по словарю (идемпотентно, один раз).
 */
function sad_znaniy_maybe_install_flowers() {
	if ( get_option( 'sad_znaniy_flowers_ready' ) ) {
		return;
	}
	if ( ! post_type_exists( 'flower' ) || ! function_exists( 'sad_znaniy_bouquet_flowers' ) ) {
		return;
	}

	foreach ( sad_znaniy_bouquet_flowers() as $row ) {
		if ( empty( $row['slug'] ) || empty( $row['title'] ) ) {
			continue;
		}
		if ( get_page_by_path( (string) $row['slug'], OBJECT, 'flower' ) ) {
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'flower',
				'post_status'  => 'publish',
				'post_title'   => (string) $row['title'],
				'post_name'    => (string) $row['slug'],
				'post_content' => '',
				'menu_order'   => isset( $row['sort'] ) ? (int) $row['sort'] : 0,
			),
			true
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}

		$cats   = isset( $row['cats'] ) ? (array) $row['cats'] : array();
		$season = isset( $row['season'] ) ? (array) $row['season'] : array();
		$bad    = isset( $row['bad'] ) ? (array) $row['bad'] : array();

		update_post_meta( $post_id, '_sz_fl_meaning', isset( $row['meaning'] ) ? (string) $row['meaning'] : '' );
		update_post_meta( $post_id, '_sz_fl_desc', isset( $row['desc'] ) ? (string) $row['desc'] : '' );
		update_post_meta( $post_id, '_sz_fl_category', implode( ',', array_map( 'sanitize_key', $cats ) ) );
		update_post_meta( $post_id, '_sz_fl_rare', ! empty( $row['rare'] ) ? '1' : '' );
		update_post_meta( $post_id, '_sz_fl_season', implode( ',', array_map( 'absint', $season ) ) );
		update_post_meta( $post_id, '_sz_fl_plant', sad_znaniy_flower_plant_id( isset( $row['plant'] ) ? (string) $row['plant'] : '' ) );
		update_post_meta( $post_id, '_sz_fl_bad', implode( ',', array_map( 'sanitize_title', $bad ) ) );
		update_post_meta( $post_id, '_sz_fl_warn', '' );
		update_post_meta( $post_id, '_sz_fl_photo', '' );
		update_post_meta( $post_id, '_sz_fl_alias', '' );
		update_post_meta( $post_id, '_sz_fl_variant_of', isset( $row['variant'] ) ? (string) $row['variant'] : '' );
		update_post_meta( $post_id, '_sz_fl_genitive', isset( $row['genitive'] ) ? (string) $row['genitive'] : '' );
		update_post_meta( $post_id, '_sz_fl_sort', isset( $row['sort'] ) ? (int) $row['sort'] : 0 );
		update_post_meta( $post_id, '_sz_fl_desc_fp', '1' );
	}

	flush_rewrite_rules();
	update_option( 'sad_znaniy_flowers_ready', 1, false );
}
add_action( 'wp_loaded', 'sad_znaniy_maybe_install_flowers' );

/**
 * Количество записей цветов (для служебных проверок).
 *
 * @param string $status Статус.
 * @return int
 */
function sad_znaniy_flower_count( $status = 'publish' ) {
	$counts = (array) wp_count_posts( 'flower' );
	return isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
}






