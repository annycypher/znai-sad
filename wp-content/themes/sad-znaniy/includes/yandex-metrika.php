<?php
/**
 * Plugin Name: SZ — Yandex.Metrika counter
 * Description: Счётчик Яндекс.Метрики №114109309 (webvisor, clickmap, ecommerce dataLayer). Выводится только на проде (znai-sad.ru). Код из интерфейса Метрики от 2025-11-06, не редактировать вручную.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_head', function () {
    $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : '';
    $host = preg_replace( '/^www\./', '', $host );
    if ( 'znai-sad.ru' !== $host ) {
        return; // Локально (LocalWP) и на прочих доменах счётчик не выводится.
    }
    ?>
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=114109309', 'ym');

        ym(114109309, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/114109309" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
    <?php
}, 99 );
