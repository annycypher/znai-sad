<?php
/**
 * Блочные паттерны «Сад знаний».
 *
 * Владелец собирает статью из готовых заготовок в блочном редакторе
 * (смотри ADMINGUIDE.md → «Как собрать статью из блоков»).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Категория паттернов «Сад знаний».
 */
function sad_znaniy_pattern_category() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'sad-znaniy',
		array( 'label' => __( 'Сад знаний', 'sad-znaniy' ) )
	);
}
add_action( 'init', 'sad_znaniy_pattern_category' );

/**
 * Регистрирует паттерны темы.
 */
function sad_znaniy_register_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	$plant_guide = '<!-- wp:paragraph -->
<p>Коротко: о чём этот материал и для кого он полезен.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"align":"wide"} -->
<figure class="wp-block-image alignwide"><img alt="Фото растения"/></figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2>Описание и особенности</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Как выглядит, где растёт, чем интересно.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Посадка</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li>Сроки и место</li><li>Подготовка почвы</li><li>Схема посадки</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Уход</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li>Полив</li><li>Подкормки</li><li>Обрезка</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Чек-лист</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li>Пункт 1</li><li>Пункт 2</li><li>Пункт 3</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Частые вопросы (FAQ)</h2>
<!-- /wp:heading -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Вопрос 1</summary><!-- wp:paragraph --><p>Ответ 1.</p><!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Вопрос 2</summary><!-- wp:paragraph --><p>Ответ 2.</p><!-- /wp:paragraph --></details>
<!-- /wp:details -->';

	register_block_pattern(
		'sad-znaniy/plant-guide',
		array(
			'title'       => __( 'Гайд по растению', 'sad-znaniy' ),
			'description' => __( 'Структура гайда: вступление, фото, секции H2, чек-лист и FAQ.', 'sad-znaniy' ),
			'categories'  => array( 'sad-znaniy' ),
			'keywords'    => array( 'растение', 'гайд', 'садоводство' ),
			'content'     => $plant_guide,
		)
	);

	$recipe = '<!-- wp:paragraph -->
<p>Короткое описание блюда и на сколько порций.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Состав</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li>Ингредиент 1 — количество</li><li>Ингредиент 2 — количество</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Приготовление</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol><li>Шаг 1</li><li>Шаг 2</li><li>Шаг 3</li></ol>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Совет</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Совет по подаче или хранению.</p>
<!-- /wp:paragraph -->';

	register_block_pattern(
		'sad-znaniy/recipe',
		array(
			'title'       => __( 'Рецепт', 'sad-znaniy' ),
			'description' => __( 'Структура рецепта: состав, шаги приготовления и совет.', 'sad-znaniy' ),
			'categories'  => array( 'sad-znaniy' ),
			'keywords'    => array( 'рецепт', 'кухня', 'мангал', 'казан' ),
			'content'     => $recipe,
		)
	);
}
add_action( 'init', 'sad_znaniy_register_patterns' );
