# -*- coding: utf-8 -*-
"""Сборка мастеров фавиконов «Сад знаний» (Этап 1, локально).

Что делает:
  1) пересобирает зелёную копию символа (source/logo-sad-green.svg);
  2) рендерит 4 страницы _tools/render/vN.html в PNG 512×512 локальным
     Chromium-браузером в headless-режиме: прозрачный фон, device-scale-factor=1,
     окно 512×512; страницы отдаются локальным сервером на 127.0.0.1
     (по file:// браузер не применяет шрифты);
  3) проверяет результат (размер, режим, альфа в углах, поля вокруг содержимого);
  4) пишет векторные мастера variants/v2.svg и variants/v4.svg (чистый путь);
  5) уменьшает мастера до 16/32/64/180 px Pillow'ом (Lanczos) в preview/.

Почему браузер, а не ImageMagick/PHP GD:
  magick в системе нет, у Local PHP отключено расширение gd, Pillow не умеет SVG
  и не грузит woff2. Headless-Chromium рисует тот же вектор и фирменный Golos Text
  без установки пакетов и без обращений в сеть.

Запуск:  py build.py             (перерисовать всё)
         py build.py --no-render (только проверка + уменьшения)
"""

from __future__ import annotations

import functools
import re
import shutil
import subprocess
import sys
import tempfile
import threading
import time
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]          # _design/favicons
THEME = ROOT.parent.parent                          # корень темы sad-znaniy
SRC = ROOT / "source"
RENDER = ROOT / "_tools" / "render"
VARIANTS = ROOT / "variants"
PREVIEW = ROOT / "preview"
PORT = 8098                                         # локальный сервер только для рендера

NAMES = ("v1", "v2", "v3", "v4", "v5")
SIZES = (16, 32, 64, 180)
SIZE_MASTER = 512
# Chromium-браузеры для рендера: сначала Chrome (завершается сам), затем Edge.
BROWSER_CANDIDATES = (
    Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe"),
    Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"),
    Path(r"C:\Program Files\Microsoft\Edge\Application\msedge.exe"),
)


def browser_binary() -> Path:
    for p in BROWSER_CANDIDATES:
        if p.exists():
            return p
    raise SystemExit("Не найден chrome.exe/msedge.exe — проверьте Program Files.")


class QuietHandler(SimpleHTTPRequestHandler):
    """Статика без логов в консоль."""

    def log_message(self, *args):  # noqa: D401 - намеренно молча
        pass


def start_server(port: int) -> tuple[ThreadingHTTPServer, str]:
    """Локальный статик-сервер на корень темы.

    Нужен, чтобы Chrome грузил @font-face: по file:// шрифты заблокированы
    и вордмарк В1 молча рисовался системным шрифтом. Слушаем только 127.0.0.1.
    """
    handler = functools.partial(QuietHandler, directory=str(THEME))
    for candidate in range(port, port + 20):
        try:
            srv = ThreadingHTTPServer(("127.0.0.1", candidate), handler)
        except OSError:
            continue
        threading.Thread(target=srv.serve_forever, daemon=True).start()
        base = f"http://127.0.0.1:{candidate}/_design/favicons/_tools/render/"
        return srv, base
    raise SystemExit("Не удалось занять локальный порт для рендера.")


def path_data(svg_text: str) -> str:
    m = re.search(r'<path d="([^"]+)"', svg_text)
    if not m:
        raise SystemExit("В исходнике не найден <path d=...>")
    return m.group(1)


def ensure_color_sources() -> None:
    """Производные копии символа: зелёная (#68B96E) и белая (#FFFFFF).

    В тексте комментария нельзя писать два дефиса подряд (--accent):
    XML-комментарии такое запрещают, и браузер молча не рисует такой SVG.
    """
    ink = (SRC / "logo-sad.svg").read_text(encoding="utf-8")
    origin = "Для зелёного исполнения есть logo-sad-green.svg (цвет заливки отличается)."
    copies = (
        ("green", "#68B96E", "Зелёное исполнение символа: акцентный зелёный #68B96E."),
        ("white", "#FFFFFF", "Белое исполнение символа: #FFFFFF."),
    )
    for suffix, color, note in copies:
        text = ink.replace('fill="#20241F"', f'fill="{color}"').replace(origin, note)
        if text == ink:
            raise SystemExit(f"Не удалось подменить цвет заливки: {suffix}")
        (SRC / f"logo-sad-{suffix}.svg").write_text(text, encoding="utf-8")


def render(name: str, browser: Path, base: str) -> Path:
    """Скриншот страницы 512×512 с прозрачным фоном.

    Edge не всегда завершается после записи файла (остаются дочерние процессы),
    поэтому ждём появления PNG и добиваем процесс сами.
    """
    out = VARIANTS / f"{name}.png"
    if out.exists():
        out.unlink()
    profile = Path(tempfile.mkdtemp(prefix="fav-render-"))
    cmd = [
        str(browser),
        "--headless=new",
        "--disable-gpu",
        "--hide-scrollbars",
        "--force-device-scale-factor=1",
        f"--window-size={SIZE_MASTER},{SIZE_MASTER}",
        "--default-background-color=00000000",
        "--no-first-run",
        "--no-default-browser-check",
        "--disable-extensions",
        "--virtual-time-budget=5000",
        f"--user-data-dir={profile}",
        f"--screenshot={out}",
        f"{base}{name}.html",
    ]
    proc = subprocess.Popen(cmd, stdout=subprocess.DEVNULL,
                            stderr=subprocess.DEVNULL)
    deadline = time.time() + 90
    written = False
    while time.time() < deadline:
        if out.exists() and out.stat().st_size > 0:
            written = True
            break
        if proc.poll() is not None:
            written = out.exists() and out.stat().st_size > 0
            break
        time.sleep(0.3)
    if proc.poll() is None:
        proc.kill()
        proc.wait(timeout=15)
    shutil.rmtree(profile, ignore_errors=True)
    if not written:
        raise SystemExit(f"{name}: браузер не записал {out}")
    return out


def verify(name: str) -> dict:
    im = Image.open(VARIANTS / f"{name}.png")
    rgba = im.convert("RGBA")
    alpha = rgba.getchannel("A")
    bbox = alpha.point(lambda a: 255 if a > 8 else 0).getbbox() or (0, 0, 0, 0)
    corners = [rgba.getpixel(p)[3] for p in
               ((0, 0), (511, 0), (0, 511), (511, 511))]
    content = (bbox[2] - bbox[0], bbox[3] - bbox[1])
    margins = (bbox[0], bbox[1], 511 - bbox[2], 511 - bbox[3])
    return {
        "name": name, "size": im.size, "mode": im.mode,
        "corners": corners, "bbox": bbox,
        "content": content, "margins": margins,
    }


def thumbs(name: str) -> None:
    src = Image.open(VARIANTS / f"{name}.png").convert("RGBA")
    for s in SIZES:
        src.resize((s, s), Image.LANCZOS).save(
            PREVIEW / f"{name}-{s}.png", optimize=True)


def svg_master(name: str, d: str) -> None:
    """Векторные мастера для вариантов без текста (В2, В4, В5).

    В1 и В3 содержат текст: в SVG он остался бы зависимым от шрифта
    (внешние ресурсы в standalone SVG не грузятся), поэтому для них
    мастер — только PNG 512×512. Перевод текста в кривые — Этап 2.
    """
    if name == "v2":
        text = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<!-- В2 «Только символ»: символ цвета ink #20241F, поля ~10 %.\n'
            '     Вектор без текста, годится как favicon.svg без зависимостей. -->\n'
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"'
            ' width="512" height="512">\n'
            '  <g fill="#20241F" transform="translate(79.82 51) scale(7.978)">\n'
            f'    <path d="{d}"/>\n'
            '  </g>\n'
            '</svg>\n'
        )
    elif name == "v4":
        text = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<!-- В4 «Тёмная плашка»: фон — градиент шапки #3A423B → #22271F,\n'
            '     символ — акцентный зелёный #68B96E. -->\n'
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"'
            ' width="512" height="512">\n'
            '  <defs>\n'
            '    <linearGradient id="plate" x1="0" y1="0" x2="0.342" y2="0.94">\n'
            '      <stop offset="0" stop-color="#3A423B"/>\n'
            '      <stop offset="1" stop-color="#22271F"/>\n'
            '    </linearGradient>\n'
            '  </defs>\n'
            '  <rect width="512" height="512" rx="112" fill="url(#plate)"/>\n'
            '  <g fill="#68B96E" transform="translate(127.06 106) scale(5.838)">\n'
            f'    <path d="{d}"/>\n'
            '  </g>\n'
            '</svg>\n'
        )
    else:  # v5
        text = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<!-- В5 «Белый символ на зелёной плашке»: фон — акцентный зелёный #68B96E,\n'
            '     символ — белый #FFFFFF. Текста нет, мастер чистый вектор. -->\n'
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"'
            ' width="512" height="512">\n'
            '  <rect width="512" height="512" rx="112" fill="#68B96E"/>\n'
            '  <g fill="#FFFFFF" transform="translate(127.06 106) scale(5.838)">\n'
            f'    <path d="{d}"/>\n'
            '  </g>\n'
            '</svg>\n'
        )
    (VARIANTS / f"{name}.svg").write_text(text, encoding="utf-8")


def main() -> None:
    do_render = "--no-render" not in sys.argv
    VARIANTS.mkdir(parents=True, exist_ok=True)
    PREVIEW.mkdir(parents=True, exist_ok=True)

    ensure_color_sources()
    d = path_data((SRC / "logo-sad.svg").read_text(encoding="utf-8"))

    if do_render:
        browser = browser_binary()
        print(f"Браузер для рендера: {browser}")
        srv, base = start_server(PORT)
        print(f"Локальный сервер: {base}")
        time.sleep(0.5)
        try:
            for name in NAMES:
                render(name, browser, base)
                print(f"  {name}.png — отрендерен")
        finally:
            srv.shutdown()

    for name in NAMES:
        thumbs(name)

    svg_master("v2", d)
    svg_master("v4", d)
    svg_master("v5", d)

    print("\nПроверка мастеров (512x512):")
    for name in NAMES:
        r = verify(name)
        ok_size = r["size"] == (SIZE_MASTER, SIZE_MASTER)
        ok_alpha = all(a == 0 for a in r["corners"])
        print(f"  {name}: {r['size']} {r['mode']} "
              f"углы={r['corners']} bbox={r['bbox']} "
              f"содержимое={r['content']} поля={r['margins']} "
              f"{'OK' if ok_size and ok_alpha else 'ПРОВЕРИТЬ'}")

    print("\nУменьшения:", ", ".join(str(s) for s in SIZES), "px ->", PREVIEW)
    print("Готово.")


if __name__ == "__main__":
    main()
