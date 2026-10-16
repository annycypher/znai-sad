# Запуск php-скриптов с доступом к WordPress на локалке (LocalWP)

Заметка для владельца и для будущих сессий: как запускать служебные скрипты
темы (`_tools/link-audit.php`, проверки целостности, экспорт) из PowerShell.
Понадобится и при деплое — на боевом сервере та же команда, но без правки путей.

## Симптом

`php _tools/link-audit.php` из папки темы на локалке отдаёт HTML-страницу
WordPress с ошибками, по очереди:

1. «Похоже, в вашей конфигурации PHP отсутствует расширение MySQL…» — CLI-сборка
   PHP у Local не подхватывает `extension_dir` (по умолчанию смотрит в несуществующий
   `C:\php\ext`), поэтому `mysqli` не загружается.
2. После включения `mysqli` — «Ошибка базы данных»: CLI обращается к MySQL на
   `localhost:3306`, а MySQL сайта в Local слушает **свой порт** (для этого сайта — **10005**).

## Рабочая команда (локалка, сайт sadznaniy)

```powershell
$php = 'C:\Users\krs3d\AppData\Roaming\Local\lightning-services\php-8.3.29+1\bin\win64\php.exe'
$ext = 'C:\Users\krs3d\AppData\Roaming\Local\lightning-services\php-8.3.29+1\bin\win64\ext'
& $php -d "extension_dir=$ext" -d extension=mysqli -d extension=pdo_mysql `
       -d mysqli.default_port=10005 `
       "C:\Users\krs3d\Local Sites\sadznaniy\app\public\wp-content\themes\sad-znaniy\_tools\link-audit.php"
```

Проверка, что порт и расширения в порядке:

```powershell
& $php -d "extension_dir=$ext" -d extension=mysqli -r 'echo extension_loaded("mysqli") ? "mysqli=yes" : "mysqli=no";'
```

## Почему именно так

| Что | Значение / где смотреть |
|---|---|
| Порт MySQL сайта | `%APPDATA%\Local\run\<hash>\conf\mysql\my.cnf`, строка `port = 10005`; либо `Get-Process mysqld` + `Get-NetTCPConnection -OwningProcess <pid>` |
| Каталог расширений | `…\Local\lightning-services\php-8.3.29+1\bin\win64\ext` (там лежат `php_mysqli.dll`, `php_pdo_mysql.dll`) |
| Креды БД | `wp-config.php`: `DB_NAME=local`, `DB_USER=root`, `DB_PASSWORD=root`, `DB_HOST=localhost`, префикс `wp_` |
| Веб-SAPI | ничего настраивать не нужно: страницы сайта работают через сервер Local, где расширения уже включены |

## Важно

- Скрипты в `_tools/` имеют CLI-гейт (`PHP_SAPI !== 'cli'` → 403), поэтому запускать
  их из браузера бессмысленно и небезопасно — только так.
- Порт может измениться после переустановки/обновления Local — сверить по `my.cnf`.
- Пароли БД не выносим в командную строку боевого сервера: там свои креды и SSH.
- В `wp-config.php` локально включены `WP_DEBUG` и лог вне webroot
  (`…/logs/php-error.log`) — ошибки CLI-скриптов тоже попадают туда.
