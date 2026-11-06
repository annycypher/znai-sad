/* Регистрация фирменного шрифта Golos Text для страниц рендера.
   Файл берётся из темы (assets/fonts), страницы открываются с локального
   статик-сервера build.py — поэтому same-origin и CORS не мешает.

   Почему не @font-face в CSS: в headless-Chromium такой face навсегда
   остаётся в статусе «loading» (файл скачивается, шрифт не применяется),
   а FontFace, собранный из ArrayBuffer, грузится штатно.

   Признак готовности: <html data-fonts="golos">. */
(function () {
    var FILES = ['../../../../assets/fonts/golos-text-cyrillic.woff2'];

    var jobs = FILES.map(function (url) {
        return fetch(url)
            .then(function (res) {
                if (!res.ok) { throw new Error(url + ': HTTP ' + res.status); }
                return res.arrayBuffer();
            })
            .then(function (buf) {
                var face = new FontFace('Golos Text', buf, { weight: '700', style: 'normal' });
                return face.load();
            })
            .then(function (loaded) {
                document.fonts.add(loaded);
                return loaded;
            });
    });

    Promise.all(jobs).then(function () {
        if (document.fonts.check('700 50px "Golos Text"', 'САД')) {
            document.documentElement.setAttribute('data-fonts', 'golos');
        } else {
            document.documentElement.setAttribute('data-fonts', 'fallback');
        }
    }).catch(function () {
        document.documentElement.setAttribute('data-fonts', 'fallback');
    });
})();
