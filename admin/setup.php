<?php
/* Copyright (C) 2026 DoliNotif contributors
 *
 * Licensed under the GNU General Public License v3 or later (GPL-3.0-or-later)
 * with the Commons Clause restriction.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * Commons Clause: you may not Sell the Software.
 */

/**
 *	\file       admin/setup.php
 *	\ingroup    dolinotif
 *	\brief      DoliNotif admin setup page.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("admin", "dolinotif@dolinotif"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

$constants = array(
	'DOLINOTIF_POLLING_INTERVAL' => array('type' => 'int',    'default' => 30,             'min' => 5),
	'DOLINOTIF_RETENTION_DAYS'   => array('type' => 'int',    'default' => 90,             'min' => 1),
	'DOLINOTIF_MAX_DROPDOWN'     => array('type' => 'int',    'default' => 15,             'min' => 1),
);

if ($action === 'update' && !empty($_POST)) {
	if (empty($_SESSION['newtoken']) || GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
		setEventMessages($langs->trans('ErrorBadToken'), null, 'errors');
	} else {
		$error = 0;
		foreach ($constants as $name => $def) {
			$val = GETPOST($name, 'alphanohtml');
			if ($def['type'] === 'int') {
				$val = (int) $val;
				if (isset($def['min']) && $val < $def['min']) {
					$val = $def['min'];
				}
			}
			$r = dolibarr_set_const($db, $name, $val, 'chaine', 0, '', $conf->entity);
			if ($r <= 0) {
				$error++;
			}
		}
		if ($error) {
			setEventMessages($langs->trans('ErrorOnSave'), null, 'errors');
		} else {
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$title = $langs->trans('DoliNotifSetupTitle');
llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '</tr>';

// DOLINOTIF_POLLING_INTERVAL
$v = getDolGlobalInt('DOLINOTIF_POLLING_INTERVAL', 30);
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DoliNotifPollingInterval').'</td>';
print '<td><input type="number" min="5" name="DOLINOTIF_POLLING_INTERVAL" value="'.(int) $v.'" class="width75"></td>';
print '<td class="opacitymedium">'.$langs->trans('DoliNotifPollingIntervalHelp').'</td>';
print '</tr>';

// DOLINOTIF_RETENTION_DAYS
$v = getDolGlobalInt('DOLINOTIF_RETENTION_DAYS', 90);
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DoliNotifRetentionDays').'</td>';
print '<td><input type="number" min="1" name="DOLINOTIF_RETENTION_DAYS" value="'.(int) $v.'" class="width75"></td>';
print '<td class="opacitymedium">'.$langs->trans('DoliNotifRetentionDaysHelp').'</td>';
print '</tr>';

// DOLINOTIF_MAX_DROPDOWN
$v = getDolGlobalInt('DOLINOTIF_MAX_DROPDOWN', 15);
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DoliNotifMaxDropdown').'</td>';
print '<td><input type="number" min="1" name="DOLINOTIF_MAX_DROPDOWN" value="'.(int) $v.'" class="width75"></td>';
print '<td class="opacitymedium">'.$langs->trans('DoliNotifMaxDropdownHelp').'</td>';
print '</tr>';

print '</table>';

print '<div class="center" style="margin-top:12px;">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';
print '</form>';

llxFooter();
$db->close();
