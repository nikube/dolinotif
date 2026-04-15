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
 *	\file       ajax/notifications.php
 *	\ingroup    dolinotif
 *	\brief      AJAX endpoints (count, list, markread, markallread).
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', 1);
}

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

dol_include_once('/dolinotif/class/dolinotification.class.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

// Output JSON
top_httphead('application/json');

/**
 *	Small helper to emit a JSON response and exit.
 *
 *	@param	mixed	$payload	Anything json_encode() can handle
 *	@param	int		$httpStatus	HTTP status code
 *	@return	void
 */
function dolinotif_json_out($payload, $httpStatus = 200)
{
	if ($httpStatus !== 200) {
		http_response_code($httpStatus);
	}
	echo json_encode($payload);
	exit;
}

// Auth
if (empty($user) || !is_object($user) || $user->id <= 0) {
	dolinotif_json_out(array('error' => 'not_authenticated'), 401);
}

$action = GETPOST('action', 'aZ09');
$method = !empty($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

$entity  = (int) $conf->entity;
$fk_user = (int) $user->id;

$notif = new DoliNotification($db);

switch ($action) {
	case 'count':
		$since = GETPOST('since', 'alphanohtml');
		$unread = $notif->countUnread($fk_user, $entity);
		if ($unread < 0) {
			dolinotif_json_out(array('error' => 'db_error'), 500);
		}

		$newItems = array();
		if (!empty($since)) {
			// Accept ISO 'YYYY-MM-DDTHH:MM:SS' and 'YYYY-MM-DD HH:MM:SS'
			$since = str_replace('T', ' ', $since);
			// Strip anything that isn't a safe datetime char
			if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $since, $m)) {
				$since = $m[1];
				$rows = $notif->listNewSince($fk_user, $entity, $since, 20);
				if (is_array($rows)) {
					foreach ($rows as $r) {
						$newItems[] = array(
							'rowid'         => (int) $r->rowid,
							'type'          => $r->type,
							'category'      => $r->category,
							'title'         => dol_escape_htmltag($r->title),
							'message'       => $r->message !== null ? dol_escape_htmltag($r->message) : null,
							'url'           => $r->url,
							'element_type'  => $r->element_type,
							'fk_element'    => $r->fk_element !== null ? (int) $r->fk_element : null,
							'is_read'       => (int) $r->is_read,
							'date_creation' => $r->date_creation,
						);
					}
				}
			}
		}

		dolinotif_json_out(array(
			'unread' => (int) $unread,
			'new'    => $newItems,
			'now'    => dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S'),
		));
		break;

	case 'list':
		// HOOK: dolinotif_before_list — for category filtering (user prefs, premium)
		// Premium version will filter $rows by user category preferences here.

		$limit = (int) GETPOST('limit', 'int');
		if ($limit <= 0) {
			$limit = (int) getDolGlobalInt('DOLINOTIF_MAX_DROPDOWN', 15);
		}
		$rows = $notif->listForUser($fk_user, $entity, $limit);
		if (!is_array($rows)) {
			dolinotif_json_out(array('error' => 'db_error'), 500);
		}
		$out = array();
		foreach ($rows as $r) {
			$out[] = array(
				'rowid'         => (int) $r->rowid,
				'type'          => $r->type,
				'category'      => $r->category,
				'title'         => dol_escape_htmltag($r->title),
				'message'       => $r->message !== null ? dol_escape_htmltag($r->message) : null,
				'url'           => $r->url,
				'element_type'  => $r->element_type,
				'fk_element'    => $r->fk_element !== null ? (int) $r->fk_element : null,
				'is_read'       => (int) $r->is_read,
				'date_creation' => $r->date_creation,
			);
		}
		dolinotif_json_out($out);
		break;

	case 'markread':
		if ($method !== 'POST') {
			dolinotif_json_out(array('error' => 'method_not_allowed'), 405);
		}
		// CSRF
		$token = GETPOST('token', 'alpha');
		if (empty($token) || $token !== newToken()) {
			// Fallback: verify against current session token
			if (empty($_SESSION['token']) || $token !== $_SESSION['token']) {
				dolinotif_json_out(array('error' => 'bad_token'), 403);
			}
		}
		$id = (int) GETPOST('id', 'int');
		if ($id <= 0) {
			dolinotif_json_out(array('error' => 'bad_id'), 400);
		}
		$r = $notif->markRead($id, $fk_user, $entity);
		if ($r < 0) {
			dolinotif_json_out(array('error' => 'db_error'), 500);
		}
		dolinotif_json_out(array('ok' => 1));
		break;

	case 'markallread':
		if ($method !== 'POST') {
			dolinotif_json_out(array('error' => 'method_not_allowed'), 405);
		}
		$token = GETPOST('token', 'alpha');
		if (empty($token) || $token !== newToken()) {
			if (empty($_SESSION['token']) || $token !== $_SESSION['token']) {
				dolinotif_json_out(array('error' => 'bad_token'), 403);
			}
		}
		$count = $notif->markAllRead($fk_user, $entity);
		if ($count < 0) {
			dolinotif_json_out(array('error' => 'db_error'), 500);
		}
		dolinotif_json_out(array('ok' => 1, 'count' => (int) $count));
		break;

	default:
		dolinotif_json_out(array('error' => 'unknown_action'), 400);
}
