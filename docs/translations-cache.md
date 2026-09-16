# Кэши переводов модуля (для разработки)

Заметка для разработчиков: почему правки в `Messages/*` иногда «не подхватываются»
в веб-интерфейсе и как это корректно инвалидировать.

## Как устроены кэши переводов (3 слоя, все завязаны на `versionsHash`)

Модульные переводы (`<Module>/Messages/<lang>.php` или новая структура
`<Module>/Messages/<lang>/*.php`) мёржатся в `MessagesProvider` с ядровыми
(`MessagesProvider.php:76-87`). Кэшируются в:

1. **Redis managedCache (DB 4)** — ключ
   `_PH_MANAGED_CACHE:LocalisationArray:<versionsHash>:<lang>`, TTL 3600 с
   (`MessagesProvider.php:53,58,93`).
2. **JS-файл** `/var/tmp/www_cache/js/localization-<lang>-<versionsHash>.min.js`
   — регенерируется только если отсутствует (`AssetProvider.php:325-326`,
   `if (!file_exists)`).
3. **Сам `versionsHash`** — тоже в Redis DB 4, ключ
   `_PH_MANAGED_CACHE:versionHash`, TTL 3600 с
   (`PBXConfModulesProvider.php:131,139`).

## Ловушка, из-за которой «не подхватывается»

`versionsHash = md5(PBX_VERSION + конкатенация id+version всех модулей)`
(`PBXConfModulesProvider.php:133-138`) — зависит от **версий**, а не от
содержимого. Отсюда:

- Если править `Messages/*` модуля **без бампа версии** в `module.json` (или
  переустанавливать ту же версию) — хеш не меняется → и Redis-ключ, и имя
  JS-файла те же → старые переводы отдаются до истечения TTL (Redis) и пока
  не удалён JS-файл.
- Даже при бампе версии: `getVersionsHash()` кэширует результат на 1 час
  (ключ `versionHash`). Пока он «тёплый» — вернётся **старый** хеш. Поэтому
  одного `getVersionsHash(true)` мало, если ключ уже прогрет; надёжнее —
  сбросить.

**Вывод:** полагаться на смену хеша нельзя — надо принудительно
инвалидировать все три слоя.

## Правильный рецепт (на хосте, напр. `serber@boffart.miko.ru`)

```bash
# 1. Сбросить Redis managedCache (DB 4) — убивает и versionHash, и все LocalisationArray:*
redis-cli -n 4 FLUSHDB

# 2. Удалить скомпилированные JS-локализации (регенерируются только если их нет)
rm -f /var/tmp/www_cache/js/localization-*.min.js

# 3. Сбросить OPcache для веб-слоя: файлы Messages/*.php это `return [...]`,
#    их кэширует OPcache в php-fpm
monit restart php-fpm
```

Если правите точечно, без `FLUSHDB` всей DB 4:

```bash
redis-cli -n 4 --scan --pattern '_PH_MANAGED_CACHE:LocalisationArray:*' | xargs -r redis-cli -n 4 DEL
redis-cli -n 4 DEL _PH_MANAGED_CACHE:versionHash
```

**Порядок важен:** сначала Redis + JS, затем php-fpm (чтобы новый воркер сразу
перечитал файлы и пересобрал кэш при первом заходе в GUI).

## Нюансы

- **Браузер.** Если версия модуля не менялась, имя JS-файла то же → браузер
  может отдать закешированный. После чистки — жёсткий рефреш
  (Ctrl/Cmd+Shift+R).
- **CLI/воркеры — фреши.** В CLI кэша нет (`MessagesProvider.php:51`,
  `php_sapi_name() !== 'cli'`) — переводы читаются с диска. Но долгоживущий
  воркер держит message-файлы в памяти/OPcache; если модуль показывает
  переводы из воркера — рестартните его
  (`pkill -TERM -f <WorkerClass>`, супервизор поднимет).
- **`getVersionsHash(true)`** уже вызывается в конце `installModule()`
  (`PbxExtensionSetupBase.php:228`) и `installDB()` (`:395`) — но это помогает
  только при смене версии модуля. При той же версии хеш прежний; спасает
  именно `FLUSHDB` + удаление JS.
- **Language-pack (новая структура `Messages/<lang>/*.php`)** — всё то же:
  `loadModuleTranslations()` (`MessagesProvider.php:173`) грузит обе структуры,
  кэш общий.
