# Установка модуля ModuleSelectorMeeting

## Канонический установщик MikoPBX (боевой путь)

Модуль устанавливается штатным воркером `WorkerModuleInstaller`, который
распаковывает zip-пакет в каталог модуля и запускает
`PbxExtensionSetup::installModule()` (проверка совместимости, лицензия,
`installFiles`, `installDB`, права, пересчёт хэшей версий/переводов).

```bash
# 1. Описание установки
cat > settings.json <<'JSON'
{
    "currentModuleDir": "/storage/usbdisk1/mikopbx/custom_modules/ModuleSelectorMeeting",
    "filePath": "/home/serber/ModuleSelectorMeeting.zip",
    "uniqid": "ModuleSelectorMeeting"
}
JSON

# 2. Запуск установки
php -f /usr/www/src/PBXCoreREST/Workers/WorkerModuleInstaller.php start settings.json
```

- `filePath` — путь к собранному zip-пакету модуля (артефакт CI, см. `.github/workflows/build.yml`).
- `currentModuleDir` — куда распаковать; установщик перезапишет каталог модуля.
- Прогресс/ошибки пишутся в служебные файлы воркера; сообщения — в syslog
  (`WorkerModuleInstaller ... installed successfully`).

## Важно: диалплан

Модуль генерирует собственный контекст `[internal-originate-selector-conf]`
(см. `Lib/SelectorMeetingConf::extensionGenContexts()`), через который идёт
обзвон участников без строки `ORIGINATE_TRY_DIAL`.

После установки диалплан должен быть перегенерирован и перечитан Asterisk,
иначе обзвон будет падать с `No such extension/context
203@internal-originate-selector-conf`. Штатная установка/включение модуля
должны поднимать диалплан автоматически; если контекст не появился —
регенерировать вручную:

```bash
php -r 'require "Globals.php"; \MikoPBX\Core\System\Configs\PbxConf::dialplanReload();'
# и проверить:
asterisk -rx 'dialplan show internal-originate-selector-conf'
```
