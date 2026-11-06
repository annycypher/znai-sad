# -*- coding: utf-8 -*-
"""Этап 2: боевые фавиконы из утверждённого варианта В5.

В5 = белый знак «Сад знаний» на акцентном зелёном #68B96E (master 512×512
лежит в _design/favicons/variants/v5.png + vector v5.svg).

Что пишет в assets/img/favicons/:
  favicon.svg           — векторный мастер иконки (все современные браузеры);
  favicon-32.png        — PNG 32×32 (Lanczos из мастера);
  favicon-16.png        — PNG 16×16;
  favicon.ico           — 16/32/48 в одном файле (старые браузеры, Windows);
  apple-touch-icon.png  — 180×180 без прозрачности (iOS рисует свою маску);

Запуск:  py build_prod.py   (из каталога _design/favicons/_tools)
"""

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]          # _design/favicons
THEME = ROOT.parent.parent                          # корень темы
OUT = THEME / "assets" / "img" / "favicons"
MASTER = ROOT / "variants" / "v5.png"
VECTOR = ROOT / "variants" / "v5.svg"
GREEN = (104, 185, 110, 255)                        # --accent #68B96E
ICO_SIZES = ((16, 16), (32, 32), (48, 48))


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)

    master = Image.open(MASTER).convert("RGBA")
    if master.size != (512, 512):
        raise SystemExit(f"Мастер не 512×512: {master.size}")

    (OUT / "favicon.svg").write_text(
        VECTOR.read_text(encoding="utf-8"), encoding="utf-8")

    for size, name in ((32, "favicon-32.png"), (16, "favicon-16.png")):
        master.resize((size, size), Image.LANCZOS).save(OUT / name, optimize=True)

    # apple-touch-icon: плашка на весь квадрат (скругление делает сама iOS),
    # поэтому прозрачные углы мастера заливаем тем же зелёным и убираем альфу.
    apple = Image.new("RGBA", master.size, GREEN)
    apple.alpha_composite(master)
    rgb = apple.convert("RGB").resize((180, 180), Image.LANCZOS)
    rgb.save(OUT / "apple-touch-icon.png", optimize=True)

    master.save(OUT / "favicon.ico", format="ICO", sizes=list(ICO_SIZES))

    print("Записано в", OUT)
    for f in sorted(OUT.iterdir()):
        print(f"  {f.name}: {f.stat().st_size} Б")

    ico = Image.open(OUT / "favicon.ico")
    print("проверка favicon.ico: кадры", ico.info.get("sizes"), "| режим", ico.mode)

    a = Image.open(OUT / "apple-touch-icon.png")
    print("проверка apple-touch-icon.png:", a.size, a.mode,
          "| альфа отсутствует:", a.mode == "RGB")

    for name in ("favicon-32.png", "favicon-16.png"):
        im = Image.open(OUT / name).convert("RGBA")
        alt = im.getchannel("A")
        print(f"проверка {name}: {im.size}, непрозрачных "
              f"{sum(1 for v in alt.get_flattened_data() if v > 190)} px")


if __name__ == "__main__":
    main()
