<?php
/**
 * Подсказки по изображениям в админке: формат, размер, вес, alt и title.
 *
 * Показывает требования к изображению рядом со штатным полем «Изображение
 * записи» и отдельным метабоксом в редакторе записей, растений и страниц,
 * а также автоматически формирует подсказку alt/title и имя файла.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Требования к изображению под конкретный тип записи.
 *
 * @param WP_Post|int $post Запись или её ID.
 * @return array{label:string,size:string,ratio:string,weight:string,minw:int,note:string}
 */
function sad_znaniy_image_spec( $post ) {
	if ( is_string( $post ) ) {
		$type = sanitize_key( $post );
	} else {
		$post = get_post( $post );
		$type = $post ? $post->post_type : 'post';
	}

	if ( 'plant' === $type ) {
		return array(
			'label'  => __( 'Карточка растения', 'sad-znaniy' ),
			'size'   => '1200×900 px',
			'ratio'  => '4:3',
			'weight' => __( 'до 250 КБ (цель 100–200 КБ)', 'sad-znaniy' ),
			'minw'   => 1200,
			'note'   => __( 'Одно растение крупно и по центру кадра — карточка обрезается до 4:3. Эта же картинка идёт в соцпосты и виджет «Луна сегодня», поэтому важное держите подальше от краёв.', 'sad-znaniy' ),
		);
	}

	if ( 'page' === $type ) {
		return array(
			'label'  => __( 'Изображение страницы', 'sad-znaniy' ),
			'size'   => '1600×900 px',
			'ratio'  => '16:9',
			'weight' => __( 'до 300 КБ (цель 150–250 КБ)', 'sad-znaniy' ),
			'minw'   => 1200,
			'note'   => __( 'Для страниц-лендингов и главной: смысловой центр — в средней части кадра, по краям обрезается.', 'sad-znaniy' ),
		);
	}

	return array(
		'label'  => __( 'Обложка статьи', 'sad-znaniy' ),
		'size'   => '1600×1000 px',
		'ratio'  => '16:10',
		'weight' => __( 'до 300 КБ (цель 150–250 КБ)', 'sad-znaniy' ),
		'minw'   => 1200,
		'note'   => __( 'Обложка показывается в 16:10 и уходит в Telegram / VK / MAX (кроп 1,91:1) — важное размещайте в центральной трети кадра, края могут срезаться.', 'sad-znaniy' ),
	);
}

/**
 * Подсказка alt: заголовок записи + понятное описание сцены.
 *
 * @param WP_Post|int $post Запись или её ID.
 * @return string
 */
function sad_znaniy_image_alt_suggestion( $post ) {
	$post  = get_post( $post );
	$title = $post ? trim( wp_strip_all_tags( get_the_title( $post ) ) ) : '';

	if ( '' === $title ) {
		return '';
	}

	$tails = array(
		'plant'          => __( 'растение в саду, фото', 'sad-znaniy' ),
		'page'           => __( 'страница сайта «Сад знаний»', 'sad-znaniy' ),
		'calendar_event' => __( 'сезонная работа в саду и огороде', 'sad-znaniy' ),
	);
	$tail = isset( $tails[ $post->post_type ] ) ? $tails[ $post->post_type ] : __( 'фото по теме статьи', 'sad-znaniy' );

	return $title . ': ' . $tail;
}

/**
 * Транслитерация русского текста в латиницу для имени файла.
 *
 * @param string $text Исходный текст.
 * @return string
 */
function sad_znaniy_translit( $text ) {
	$map = array(
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
		'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
		'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
		'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
		'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
	);
	$text = mb_strtolower( $text, 'UTF-8' );

	$out  = '';
	$len  = mb_strlen( $text, 'UTF-8' );
	for ( $i = 0; $i < $len; $i++ ) {
		$ch  = mb_substr( $text, $i, 1, 'UTF-8' );
		$out .= isset( $map[ $ch ] ) ? $map[ $ch ] : $ch;
	}
	$out = preg_replace( '/[^a-z0-9]+/', '-', $out );
	$out = trim( (string) $out, '-' );

	return substr( $out, 0, 50 );
}

/**
 * Подсказка имени файла: латиница, дефисы, ключевая фраза.
 *
 * @param WP_Post|int $post Запись или её ID.
 * @return string
 */
function sad_znaniy_image_file_suggestion( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 'foto-sada.jpg';
	}

	$base = $post->post_name ? $post->post_name : sad_znaniy_translit( (string) get_the_title( $post ) );
	$base = sad_znaniy_ascii_slug( $base );

	return ( $base ? $base : 'foto-sada' ) . '.jpg';
}

/**
 * Приводит слаг к латинице (латинские слаги вида «krokus» оставляет как есть).
 *
 * @param string $slug Слаг.
 * @return string
 */
function sad_znaniy_ascii_slug( $slug ) {
	$slug = (string) $slug;
	if ( preg_match( '/[а-яё]/u', $slug ) ) {
		$slug = sad_znaniy_translit( $slug );
	}

	return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $slug ) ), '-' );
}

/**
 * Выводит блок-подсказку по изображению.
 *
 * @param WP_Post|int $post    Запись (или её ID).
 * @param bool        $compact Короткая версия — только требования и подсказка alt.
 */
function sad_znaniy_image_hint_block( $post, $compact = false ) {
	$spec = sad_znaniy_image_spec( $post );
	$alt  = sad_znaniy_image_alt_suggestion( $post );
	$file = sad_znaniy_image_file_suggestion( $post );
	$obj  = get_post( $post );
	$uid  = 'sz-img-alt-' . ( $obj ? (int) $obj->ID : 0 );
	?>
	<div class="sz-img-hint" style="max-width:780px;background:#f6f7f7;border:1px solid #dcdcde;border-left:4px solid #2271b1;border-radius:4px;padding:10px 12px;margin:10px 0;">
		<p style="margin:0 0 6px;"><strong><?php esc_html_e( '📷 Требования к изображению —', 'sad-znaniy' ); ?> <?php echo esc_html( $spec['label'] ); ?></strong></p>
		<ul style="margin:0 0 8px 18px;list-style:disc;line-height:1.55;">
			<li><?php esc_html_e( 'Формат: JPEG или WebP. Для схем, графиков и скриншотов с текстом — PNG.', 'sad-znaniy' ); ?></li>
			<li>
				<?php esc_html_e( 'Размер:', 'sad-znaniy' ); ?>
				<code><?php echo esc_html( $spec['size'] ); ?></code>,
				<?php esc_html_e( 'кроп', 'sad-znaniy' ); ?> <?php echo esc_html( $spec['ratio'] ); ?>;
				<?php esc_html_e( 'ширина не меньше', 'sad-znaniy' ); ?> <?php echo (int) $spec['minw']; ?> px <?php esc_html_e( '(иначе будет мыло на retina).', 'sad-znaniy' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Вес:', 'sad-znaniy' ); ?> <?php echo esc_html( $spec['weight'] ); ?>;
				<?php esc_html_e( 'длинная сторона до 1600 px, профиль sRGB, сжать перед загрузкой (Squoosh, TinyPNG).', 'sad-znaniy' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Имя файла: латиницей через дефис, с ключевой фразой — например', 'sad-znaniy' ); ?>
				<code><?php echo esc_html( $file ); ?></code>.
			</li>
			<li><?php esc_html_e( 'Alt (обязательно): 5–12 слов о том, что на снимке, с ключевой фразой. Без alt фото не попадает в поиск по картинкам.', 'sad-znaniy' ); ?></li>
			<li><?php esc_html_e( 'Title (атрибут): заполнять не нужно — Google и Яндекс ориентируются на alt и подпись; title влияет только на всплывающую подсказку. Если хочется — повторите в нём alt.', 'sad-znaniy' ); ?></li>
		</ul>
		<p style="margin:0 0 8px;"><em><?php echo esc_html( $spec['note'] ); ?></em></p>
		<p style="margin:0 0 4px;"><strong><?php esc_html_e( 'Подсказка alt для этого материала (скопируйте и уточните, что видно на снимке):', 'sad-znaniy' ); ?></strong></p>
		<p style="margin:0 0 8px;">
			<?php if ( '' === $alt ) : ?>
				<em><?php esc_html_e( 'Сохраните запись — подсказка alt соберётся из заголовка автоматически.', 'sad-znaniy' ); ?></em>
			<?php else : ?>
				<input type="text" class="widefat" readonly id="<?php echo esc_attr( $uid ); ?>" value="<?php echo esc_attr( $alt ); ?>" onclick="this.select();">
				<button type="button" class="button button-small" style="margin-top:4px;" onclick="var f=document.getElementById('<?php echo esc_attr( $uid ); ?>');if(f){f.select();f.setSelectionRange(0,999);var ok=false;try{ok=document.execCommand('copy');}catch(e){ok=false;}if(!ok&&navigator.clipboard){navigator.clipboard.writeText(f.value);}this.textContent=ok?'<?php echo esc_js( __( 'Скопировано ✓', 'sad-znaniy' ) ); ?>':'<?php echo esc_js( __( 'Выделено — нажмите Ctrl+C', 'sad-znaniy' ) ); ?>';var b=this;setTimeout(function(){b.textContent='<?php echo esc_js( __( 'Скопировать alt', 'sad-znaniy' ) ); ?>';},2500);}"><?php esc_html_e( 'Скопировать alt', 'sad-znaniy' ); ?></button>
			<?php endif; ?>
		</p>
		<?php if ( ! $compact ) : ?>
			<p style="margin:0 0 6px;"><?php esc_html_e( 'Готовый alt тема подставляет файлу миниатюры автоматически, если он ещё пустой — останется проверить и уточнить в «Медиафайлы».', 'sad-znaniy' ); ?></p>
			<p style="margin:0;"><?php esc_html_e( 'Внутри текста у каждого блока «Изображение» заполните поля Alt и Подпись (в правой панели блока). Если в тексте осталась пометка «TODO: фото» — замените её реальным снимком.', 'sad-znaniy' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Регистрирует метабокс с требованиями к изображениям.
 */
function sad_znaniy_image_hints_meta_box() {
	foreach ( array( 'post', 'plant', 'page' ) as $type ) {
		add_meta_box(
			'sad-znaniy-image-hints',
			__( 'Изображения: требования и подсказка alt', 'sad-znaniy' ),
			'sad_znaniy_image_hints_meta_box_html',
			$type,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'sad_znaniy_image_hints_meta_box' );

/**
 * Разметка метабокса с требованиями к изображениям.
 *
 * @param WP_Post $post Текущая запись.
 */
function sad_znaniy_image_hints_meta_box_html( $post ) {
	sad_znaniy_image_hint_block( $post, false );
}

/**
 * Дополняет штатное поле «Изображение записи» подсказкой.
 *
 * @param string $content      HTML поля миниатюры.
 * @param int    $post_id      ID записи.
 * @param int    $thumbnail_id ID миниатюры (0 — не задана).
 * @return string
 */
function sad_znaniy_image_featured_hint( $content, $post_id, $thumbnail_id ) {
	unset( $thumbnail_id );
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'plant', 'page' ), true ) ) {
		return $content;
	}
	ob_start();
	sad_znaniy_image_hint_block( $post, true );
	return $content . ob_get_clean();
}
add_filter( 'admin_post_thumbnail_html', 'sad_znaniy_image_featured_hint', 10, 3 );

/**
 * Автоматически заполняет alt миниатюры, если у файла он ещё пустой.
 *
 * Срабатывает при назначении «Изображения записи». Готовую строку можно
 * потом уточнить в «Медиафайлы» — без alt фото не видно в поиске по картинкам.
 *
 * @param int $post_id      ID записи.
 * @param int $thumbnail_id ID вложения-миниатюры.
 */
function sad_znaniy_image_autofill_alt( $post_id, $thumbnail_id ) {
	$thumb_id = (int) $thumbnail_id;
	if ( ! $thumb_id ) {
		return;
	}

	$current = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
	if ( '' !== trim( $current ) ) {
		return;
	}

	$alt = sad_znaniy_image_alt_suggestion( (int) $post_id );
	if ( '' === $alt ) {
		return;
	}

	update_post_meta( $thumb_id, '_wp_attachment_image_alt', $alt );
}
add_action( 'added_post_meta', 'sad_znaniy_image_alt_on_thumbnail_meta', 10, 4 );
add_action( 'updated_post_meta', 'sad_znaniy_image_alt_on_thumbnail_meta', 10, 4 );

/**
 * Реагирует на смену «Изображения записи» (мета _thumbnail_id).
 *
 * В ядре нет отдельного хука на set_post_thumbnail(), поэтому слушаем мету:
 * так alt заполняется и при сохранении из редактора, и через REST, и из списка.
 *
 * @param int    $meta_id    ID мета-записи.
 * @param int    $object_id  ID записи.
 * @param string $meta_key   Ключ мета.
 * @param mixed  $meta_value Значение мета.
 */
function sad_znaniy_image_alt_on_thumbnail_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
	unset( $meta_id );
	if ( '_thumbnail_id' !== $meta_key ) {
		return;
	}
	sad_znaniy_image_autofill_alt( (int) $object_id, (int) $meta_value );
}

/**
 * Панель в правой колонке блочного редактора: подсказка всегда под рукой.
 *
 * Классический метабокс в блочном редакторе отрисовывается в самом низу экрана,
 * поэтому дублируем подсказку панелью настроек записи (рядом с «Изображение записи»).
 */
function sad_znaniy_image_hints_editor_assets() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post    = $post_id ? get_post( $post_id ) : null;
	$type    = $post ? $post->post_type : ( isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $type, array( 'post', 'plant', 'page' ), true ) ) {
		return;
	}

	ob_start();
	sad_znaniy_image_hint_block( $post ? $post : $type, false );
	$html = (string) ob_get_clean();

	$handle = 'sad-znaniy-image-hints-panel';
	wp_register_script( $handle, false, array( 'wp-plugins', 'wp-edit-post', 'wp-element' ), SAD_ZNANIY_VERSION, true );
	wp_add_inline_script( $handle, 'window.szImageHintHtml = ' . wp_json_encode( $html ) . ';', 'before' );
	wp_add_inline_script(
		$handle,
		'( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element ) { return; }
	var html = window.szImageHintHtml;
	if ( ! html ) { return; }
	wp.plugins.registerPlugin( "sad-znaniy-image-hints", {
		render: function () {
			return wp.element.createElement(
				wp.editPost.PluginDocumentSettingPanel,
				{ name: "sad-znaniy-image-hints-panel", title: "📷 Изображения: требования и alt", initialOpen: false },
				wp.element.createElement( "div", { dangerouslySetInnerHTML: { __html: html } } )
			);
		}
	} );
} )( window.wp );',
		'after'
	);
	wp_enqueue_script( $handle );
}
add_action( 'enqueue_block_editor_assets', 'sad_znaniy_image_hints_editor_assets' );

/**
 * Регистрирует страницу-справочник «Изображения: требования».
 */
function sad_znaniy_image_hints_admin_page() {
	add_submenu_page(
		'themes.php',
		__( 'Изображения: требования', 'sad-znaniy' ),
		__( 'Изображения: требования', 'sad-znaniy' ),
		'manage_options',
		'sad-znaniy-images',
		'sad_znaniy_image_hints_admin_page_html'
	);
}
add_action( 'admin_menu', 'sad_znaniy_image_hints_admin_page', 11 );

/**
 * Справочник по изображениям: размеры, вес, форматы, alt/title, логотип.
 */
function sad_znaniy_image_hints_admin_page_html() {
	$post  = sad_znaniy_image_spec( 'post' );
	$plant = sad_znaniy_image_spec( 'plant' );
	$page  = sad_znaniy_image_spec( 'page' );
	$demo  = get_posts(
		array(
			'post_type'      => 'plant',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$demo_id = $demo ? (int) $demo[0] : 0;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Изображения: требования и подсказки', 'sad-znaniy' ); ?></h1>
		<p style="max-width:900px;"><?php esc_html_e( 'Одно и то же фото работает и на странице, и в соцпостах — поэтому размеры, вес и alt лучше соблюдать сразу при загрузке. Подсказки с готовым alt автоматически показываются в редакторе каждой статьи, растения и страницы; ниже — единая памятка.', 'sad-znaniy' ); ?></p>

		<h2><?php esc_html_e( '1. Штатные места загрузки изображений', 'sad-znaniy' ); ?></h2>
		<table class="widefat striped" style="max-width:1100px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Где в админке', 'sad-znaniy' ); ?></th>
					<th><?php esc_html_e( 'Что это', 'sad-znaniy' ); ?></th>
					<th><?php esc_html_e( 'Формат', 'sad-znaniy' ); ?></th>
					<th><?php esc_html_e( 'Размер и кроп', 'sad-znaniy' ); ?></th>
					<th><?php esc_html_e( 'Вес', 'sad-znaniy' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Растения → Изображение записи', 'sad-znaniy' ); ?></td>
					<td><?php echo esc_html( $plant['label'] ); ?></td>
					<td>JPEG / WebP</td>
					<td><code><?php echo esc_html( $plant['size'] ); ?></code> (<?php echo esc_html( $plant['ratio'] ); ?>)</td>
					<td><?php echo esc_html( $plant['weight'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Записи (статьи) → Изображение записи', 'sad-znaniy' ); ?></td>
					<td><?php echo esc_html( $post['label'] ); ?></td>
					<td>JPEG / WebP</td>
					<td><code><?php echo esc_html( $post['size'] ); ?></code> (<?php echo esc_html( $post['ratio'] ); ?>)</td>
					<td><?php echo esc_html( $post['weight'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Страницы → Изображение записи', 'sad-znaniy' ); ?></td>
					<td><?php echo esc_html( $page['label'] ); ?></td>
					<td>JPEG / WebP</td>
					<td><code><?php echo esc_html( $page['size'] ); ?></code> (<?php echo esc_html( $page['ratio'] ); ?>)</td>
					<td><?php echo esc_html( $page['weight'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Внутри текста (блок «Изображение»)', 'sad-znaniy' ); ?></td>
					<td><?php esc_html_e( 'Иллюстрации по ходу статьи', 'sad-znaniy' ); ?></td>
					<td>JPEG / WebP, <?php esc_html_e( 'схемы — PNG', 'sad-znaniy' ); ?></td>
					<td><code>1200×800 px</code> (3:2)</td>
					<td><?php esc_html_e( 'до 200 КБ', 'sad-znaniy' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Внешний вид → Настроить → Свойства сайта', 'sad-znaniy' ); ?></td>
					<td><?php esc_html_e( 'Логотип', 'sad-znaniy' ); ?></td>
					<td>PNG (<?php esc_html_e( 'прозрачный фон', 'sad-znaniy' ); ?>)</td>
					<td><code>400×120 px</code></td>
					<td><?php esc_html_e( 'до 60 КБ', 'sad-znaniy' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Внешний вид → Настроить → Свойства сайта', 'sad-znaniy' ); ?></td>
					<td><?php esc_html_e( 'Иконка сайта (favicon)', 'sad-znaniy' ); ?></td>
					<td>PNG / ICO</td>
					<td><code>512×512 px</code> (<?php esc_html_e( 'строгий квадрат', 'sad-znaniy' ); ?>)</td>
					<td><?php esc_html_e( 'до 100 КБ', 'sad-znaniy' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Соцпосты (Telegram / VK / MAX)', 'sad-znaniy' ); ?></td>
					<td><?php esc_html_e( 'Картинка поста — берётся «Изображение записи»', 'sad-znaniy' ); ?></td>
					<td>JPEG</td>
					<td><code>1200×630 px</code> (1,91:1)</td>
					<td><?php esc_html_e( 'до 300 КБ', 'sad-znaniy' ); ?></td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( '2. Как заполнять alt и title', 'sad-znaniy' ); ?></h2>
		<ul style="max-width:900px;list-style:disc;margin-left:20px;line-height:1.6;">
			<li><?php esc_html_e( 'Alt — обязательно и у миниатюры, и у каждого фото в тексте. Формула: ключевая фраза + что видно на снимке. Пример: «Крокус: цветение ранней весной на клумбе».', 'sad-znaniy' ); ?></li>
			<li><?php esc_html_e( 'Title (атрибут) — не обязателен: Google и Яндекс берут смысл из alt, подписи и окружающего текста. Title показывается только как всплывающая подсказка при наведении; если хочется её заполнить — повторите alt, но ключи «для поисковиков» здесь не нужны.', 'sad-znaniy' ); ?></li>
			<li><?php esc_html_e( 'Имя файла — латиницей через дефис: krokus-posadka-i-uhod.jpg. Кириллические имена теряются при переносе на хостинг и хуже индексируются.', 'sad-znaniy' ); ?></li>
			<li><?php esc_html_e( 'Одно фото — одно место. Повторная вставка той же картинки на нескольких страницах считается дублем: берите другой кадр или другой кроп.', 'sad-znaniy' ); ?></li>
		</ul>

		<h2><?php esc_html_e( '3. Чек-лист перед публикацией', 'sad-znaniy' ); ?></h2>
		<ol style="max-width:900px;list-style:decimal;margin-left:20px;line-height:1.6;">
			<li><?php esc_html_e( 'Загружено «Изображение записи» нужного размера и веса.', 'sad-znaniy' ); ?></li>
			<li><?php esc_html_e( 'У миниатюры и у всех фото в тексте заполнен alt (5–12 слов).', 'sad-znaniy' ); ?></li>
			<li><?php esc_html_e( 'В тексте не осталось пометок «TODO: фото».', 'sad-znaniy' ); ?></li>
			<li><?php esc_html_e( 'Файлы переименованы латиницей с ключевой фразой.', 'sad-znaniy' ); ?></li>
			<li><?php esc_html_e( 'Смысловой центр картинки — в середине кадра (для соцпостов и кропа 4:3).', 'sad-znaniy' ); ?></li>
		</ol>

		<h2><?php esc_html_e( '4. Как выглядит подсказка в редакторе', 'sad-znaniy' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Откройте любую статью, растение или страницу — под полем «Изображение записи» появится такой же блок с автоматически собранным alt (готовую строку можно скопировать кнопкой и уточнить под снимок). Ниже — пример для карточки растения:', 'sad-znaniy' ); ?></p>
		<?php sad_znaniy_image_hint_block( $demo_id, false ); ?>
	</div>
	<?php
}



