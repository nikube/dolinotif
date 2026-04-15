# DoliNotif

In-app notification center for Dolibarr. Bell icon in the top bar, badge counter,
rich dropdown, toast on page load. Other modules push notifications via a single
public function. No email, no triggers — just a mailbox that modules feed into.

License: **GPL v3 + Commons Clause** (free, but you may not sell the Software).

Requires: Dolibarr 18.0+.

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

Accepted `type` values: `info`, `success`, `warning`, `error`. `category` is a
free-form tag (not translated) — e.g. `bgjob`, `system`, `import`.
