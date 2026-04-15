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
 *	\file       lib/dolinotif.lib.php
 *	\ingroup    dolinotif
 *	\brief      DoliNotif public API.
 */

dol_include_once('/dolinotif/class/dolinotification.class.php');


/**
 *	Send an in-app notification.
 *
 *	This is the ONLY public entry point for other modules to push notifications
 *	to a user. The contract is stable — don't break it.
 *
 *	@param	DoliDB	$db			Database handler
 *	@param	int		$fk_user	Target user rowid
 *	@param	array<string,mixed>	$params		Notification payload:
 *		- title        (string, required)
 *		- message      (string, optional)
 *		- type         (string: info|success|warning|error, default: info)
 *		- category     (string, optional, e.g. 'bgjob')
 *		- url          (string, optional, relative URL)
 *		- element_type (string, optional)
 *		- fk_element   (int, optional)
 *		- entity       (int, optional, defaults to current entity)
 *	@return	int					>0 = new rowid, <0 = error
 */
function dolinotifSend($db, $fk_user, $params)
{
	global $conf;

	if (!is_object($db)) {
		return -1;
	}
	$fk_user = (int) $fk_user;
	if ($fk_user <= 0) {
		return -2;
	}
	if (!is_array($params)) {
		return -3;
	}
	if (empty($params['title'])) {
		return -4;
	}

	$notif = new DoliNotification($db);
	$notif->fk_user     = $fk_user;
	$notif->entity      = isset($params['entity']) ? (int) $params['entity'] : (int) $conf->entity;
	$notif->type        = !empty($params['type']) ? (string) $params['type'] : 'info';
	$notif->category    = isset($params['category']) ? (string) $params['category'] : null;
	$notif->title       = (string) $params['title'];
	$notif->message     = isset($params['message']) ? (string) $params['message'] : null;
	$notif->url         = isset($params['url']) ? (string) $params['url'] : null;
	$notif->element_type = isset($params['element_type']) ? (string) $params['element_type'] : null;
	$notif->fk_element  = isset($params['fk_element']) ? (int) $params['fk_element'] : null;

	$res = $notif->create();

	// HOOK: dolinotif_after_send — for email fallback, broadcast expansion (premium)
	// Premium version will dispatch here to queue an email if unread after X minutes,
	// or to expand a broadcast payload into per-user rows.

	return $res;
}
