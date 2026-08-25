# DoliNotif

In-app notification center for Dolibarr. Bell icon in the top bar, badge counter,
rich dropdown, toast on page load. Other modules push notifications via a single
public function. No email, no triggers — just a mailbox that modules feed into.

License: **GPL v3 + Commons Clause** (free, but you may not sell the Software).

Requires: Dolibarr 23.0+ and PHP 8.1+.

## Install

1. Copy this folder to `htdocs/custom/dolinotif/`.
2. In Dolibarr: Setup → Modules/Applications → enable **DoliNotif**.
3. (Optional) Adjust polling interval, retention, bell position at Setup →
   DoliNotif.

The module creates `llx_dolinotif` and a daily cron job that purges read
notifications older than the retention setting.

## Public API

One function. Other modules call it — no hard dependency, just check
`isModEnabled('dolinotif')` first.

```php
if (isModEnabled('dolinotif')) {
    dol_include_once('/dolinotif/lib/dolinotif.lib.php');
    dolinotifSend($db, $job->fk_user_author, [
        'type'         => 'success',
        'category'     => 'bgjob',
        'title'        => 'PDF invoice FA2402-0012',
        'message'      => 'Generated in 2.3s',
        'url'          => '/compta/facture/card.php?id=42',
        'element_type' => 'facture',
        'fk_element'   => 42,
    ]);
}
```

Return value: `>0` = new rowid, `<0` = error.

Use `dolinotifErrorMessage($result)` to turn a negative return value into a
stable diagnostic suitable for logs. Database details are also written to the
Dolibarr log by DoliNotif.

Accepted `type` values: `info`, `success`, `warning`, `error`. `category` is a
free-form tag (not translated) — e.g. `bgjob`, `system`, `import`.

### Display-time translation

`title_i18n` is optional. It lets DoliNotif translate the title in the
recipient's language when the notification is displayed. `title` remains the
required fallback.

```php
dolinotifSend($db, $userId, [
    'title' => $langs->trans('DocumentGenerated'),
    'title_i18n' => [
        'key' => 'DocumentGenerated',
        'file' => 'mymodule@mymodule',
        'params' => [],
    ],
    'category' => 'mymodule',
]);
```

The complete payload accepts:

| Field | Required | Description |
| --- | --- | --- |
| `title` | yes | Pre-rendered fallback title |
| `title_i18n` | no | Translation payload: `key`, optional `file`, optional `params` |
| `message` | no | Additional text |
| `type` | no | `info`, `success`, `warning`, or `error` |
| `category` | no | Free-form source/category tag |
| `url` | no | Relative Dolibarr path or absolute HTTP(S) URL |
| `element_type` | no | Related Dolibarr object type |
| `fk_element` | no | Related object row ID |
| `entity` | no | Target entity; current entity by default |
