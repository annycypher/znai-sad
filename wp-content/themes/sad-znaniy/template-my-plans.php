<?php
/**
 * Template Name: Мои планы участка
 *
 * Кабинет планировщика (Этап 6, Партия 4): список сохранённых планов и
 * действия «открыть / посмотреть / копировать / удалить». Формы идут через
 * admin-post.php с nonce — работает и без JavaScript. Шаблон подставляется
 * странице со слагом moi-plany фильтром sad_znaniy_my_plans_template().
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$pln_flash = sad_znaniy_planner_flash();
$pln_plans = is_user_logged_in() ? sad_znaniy_planner_my_plans( get_current_user_id() ) : array();
$pln_edit  = sad_znaniy_planner_page_url();
?>
<section class="section">
		<div class="section-head">
				<h1 class="h-cap"><?php esc_html_e( 'Мои планы участка', 'sad-znaniy' ); ?></h1>
				<p class="pln-plans-lead">Сохранённые планы привязаны к аккаунту: открывайте их с любого устройства. Ссылкой на план можно поделиться — она открывается в режиме просмотра.</p>
		</div>

		<?php if ( '' !== $pln_flash['text'] ) : ?>
				<p class="pln-plans-flash"><?php echo esc_html( $pln_flash['text'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! is_user_logged_in() ) : ?>

				<div class="pln-gate">
						<b>Войдите — планы будут сохраняться навсегда</b>
						<p>Сейчас планировщик сохраняет план только в этом браузере. С аккаунтом план хранится в личном кабинете, доступен с телефона, а ссылкой можно поделиться.</p>
						<?php wp_login_form( array( 'redirect' => esc_url( sad_znaniy_planner_my_plans_url() ) ) ); ?>
				</div>

		<?php elseif ( empty( $pln_plans ) ) : ?>

				<div class="pln-plans-invite">
<b>Планов пока нет</b>
<p>Начертите участок, поставьте грядки и постройки — план сохранится в кабинете и будет доступен с телефона.</p>
<a class="pln-btn" href="<?php echo esc_url( $pln_edit ); ?>">Создайте первый план →</a>
</div>

		<?php else : ?>				<div class="pln-plans">
						<?php foreach ( $pln_plans as $pln_p ) : ?>
								<article class="pln-plan">
										<div class="pln-plan-head">
												<h2><?php echo esc_html( $pln_p['title'] ); ?></h2>
												<span class="pln-plan-date">создан <?php echo esc_html( $pln_p['created'] ? mysql2date( 'd.m.Y', $pln_p['created'] ) : '—' ); ?> · обновлён <?php echo esc_html( $pln_p['updated'] ? mysql2date( 'd.m.Y H:i', $pln_p['updated'] ) : '' ); ?></span>
										</div>
										<p class="pln-plan-info">Участок <b><?php echo esc_html( (float) $pln_p['plot']['w'] ); ?> × <?php echo esc_html( (float) $pln_p['plot']['h'] ); ?> м</b> · элементов <b><?php echo (int) $pln_p['elements']; ?></b><?php if ( '' !== $pln_p['region'] ) : ?> · <?php echo esc_html( sad_znaniy_planner_region_label( $pln_p['region'] ) ); ?><?php endif; ?></p>
										<div class="pln-plan-actions">
												<a class="pln-btn" href="<?php echo esc_url( $pln_p['edit'] ); ?>">Открыть</a>
												<a class="pln-btn pln-btn-light" href="<?php echo esc_url( $pln_p['share'] ); ?>">Посмотреть</a>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pln-plan-form">
														<?php wp_nonce_field( 'sz_plan_copy' ); ?>
														<input type="hidden" name="action" value="sz_plan_copy">
														<input type="hidden" name="plan_id" value="<?php echo esc_attr( $pln_p['id'] ); ?>">
														<button class="pln-btn pln-btn-light" type="submit">Копировать</button>
												</form>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pln-plan-form" onsubmit="return confirm( 'Удалить этот план? Действие необратимо.' );">
														<?php wp_nonce_field( 'sz_plan_delete' ); ?>
														<input type="hidden" name="action" value="sz_plan_delete">
														<input type="hidden" name="plan_id" value="<?php echo esc_attr( $pln_p['id'] ); ?>">
														<button class="pln-btn pln-btn-danger" type="submit">Удалить</button>
												</form>
										</div>
										<p class="pln-plan-share">Ссылка для отправки: <code><?php echo esc_html( $pln_p['share'] ); ?></code></p>
								</article>
						<?php endforeach; ?>
				</div>

				<p class="pln-plans-new"><a class="pln-btn" href="<?php echo esc_url( $pln_edit ); ?>">+ Создать новый план</a> <a class="pln-btn pln-btn-light" href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>">Календарь работ</a></p>

		<?php endif; ?>
</section>
<?php
get_footer();