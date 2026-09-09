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


/** Public API error codes returned by dolinotifSend(). */
const DOLINOTIF_ERROR_INVALID_DATABASE = -1;
const DOLINOTIF_ERROR_INVALID_USER = -2;
const DOLINOTIF_ERROR_INVALID_PAYLOAD = -3;
const DOLINOTIF_ERROR_MISSING_TITLE = -4;
const DOLINOTIF_ERROR_DATABASE = -5;


/**
 * Return a stable, untranslated diagnostic for a dolinotifSend() result.
 *
 * This lets calling modules log a useful reason without depending on the
 * internals of DoliNotification. An empty string means that the result is not
 * a known error code.
 *
 * @param int $code Return value from dolinotifSend()
 * @return string Diagnostic suitable for logs
 */
function dolinotifErrorMessage($code)
{
	$messages = array(
		DOLINOTIF_ERROR_INVALID_DATABASE => 'Invalid database handler',
		DOLINOTIF_ERROR_INVALID_USER => 'Invalid target user',
		DOLINOTIF_ERROR_INVALID_PAYLOAD => 'Invalid notification payload',
		DOLINOTIF_ERROR_MISSING_TITLE => 'Notification title is required',
		DOLINOTIF_ERROR_DATABASE => 'Unable to create notification',
	);

	return isset($messages[(int) $code]) ? $messages[(int) $code] : '';
}


/**
 *	Sanitize a notification URL. The frontend puts this value in an href AND
 *	assigns it to window.location.href, so a stored javascript: (or other
 *	active-scheme) URL would execute when the notification is clicked.
 *
 *	Allowed: relative Dolibarr paths ('/custom/...', 'card.php?id=...') and
 *	absolute http/https URLs. Rejected (returns null): every other scheme
 *	(javascript:, data:, vbscript:...), scheme-relative '//host', and
 *	whitespace/control-character smuggling.
 *
 *	@param	mixed	$url	Raw URL value
 *	@return	string|null		Safe URL, or null when rejected/empty
 */
function dolinotifSanitizeUrl($url)
{
	if (!is_string($url) && !is_numeric($url)) {
		return null;
	}
	// Control chars (incl. \t\r\n) can hide a scheme from naive checks —
	// strip them before validating, like browsers collapse them.
	$url = preg_replace('/[\x00-\x20\x7F]+/', '', (string) $url);
	if ($url === '' || $url === null) {
		return null;
	}
	// Scheme-relative //host escapes to another origin: reject.
	if (strpos($url, '//') === 0) {
		return null;
	}
	// Absolute URLs: http(s) only.
	if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
		return preg_match('~^https?://~i', $url) ? $url : null;
	}
	// No scheme: relative path — safe for both href and location.href.
	return $url;
}


/**
 *	URL as the browser must receive it. Paths starting with '/' are relative
 *	to the Dolibarr root (the API contract), so they get DOL_URL_ROOT
 *	prepended when the instance is served from a sub-directory — unless the
 *	sender already built them with dol_buildpath(). Absolute http(s) URLs
 *	and page-relative paths ('card.php?id=1') are left alone.
 *
 *	@param	mixed	$url	Stored URL value
 *	@param	string	$root	Web root (DOL_URL_ROOT); parameter for tests
 *	@return	string|null		Safe URL for href/location, or null
 */
function dolinotifDisplayUrl($url, $root = DOL_URL_ROOT)
{
	$url = dolinotifSanitizeUrl($url);
	if ($url === null || $root === '' || strpos($url, '/') !== 0) {
		return $url;
	}
	if ($url === $root || strpos($url, $root.'/') === 0 || strpos($url, $root.'?') === 0) {
		return $url;
	}
	return $root.$url;
}


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
 *		- title_i18n   (array, optional: key, file and params; translated for the viewer)
 *		- message      (string, optional)
 *		- type         (string: info|success|warning|error, default: info)
 *		- category     (string, optional, e.g. 'bgjob')
 *		- url          (string, optional, path relative to the Dolibarr root
 *		                ('/compta/facture/card.php?id=1', DOL_URL_ROOT added at
 *		                display) or http(s) URL; other schemes are dropped)
 *		- element_type (string, optional)
 *		- fk_element   (int, optional)
 *		- entity       (int, optional, defaults to current entity)
 *	@return	int					>0 = new rowid, <0 = error
 */
function dolinotifSend($db, $fk_user, $params)
{
	global $conf;

	if (!is_object($db)) {
		return DOLINOTIF_ERROR_INVALID_DATABASE;
	}
	$fk_user = (int) $fk_user;
	if ($fk_user <= 0) {
		return DOLINOTIF_ERROR_INVALID_USER;
	}
	if (!is_array($params)) {
		return DOLINOTIF_ERROR_INVALID_PAYLOAD;
	}
	if (empty($params['title'])) {
		return DOLINOTIF_ERROR_MISSING_TITLE;
	}

	$notif = new DoliNotification($db);
	$notif->fk_user     = $fk_user;
	$notif->entity      = isset($params['entity']) ? (int) $params['entity'] : (int) $conf->entity;
	$notif->type        = !empty($params['type']) ? (string) $params['type'] : 'info';
	$notif->category    = isset($params['category']) ? (string) $params['category'] : null;
	$notif->title       = (string) $params['title'];
	// Optional display-time i18n: array('key' => 'LangKey', 'file' => 'file@module', 'params' => array(...)).
	// The reader's ajax translates it in THEIR language; 'title' stays the pre-rendered fallback.
	if (!empty($params['title_i18n']) && is_array($params['title_i18n']) && !empty($params['title_i18n']['key'])) {
		$notif->title_i18n = json_encode($params['title_i18n']);
	}
	$notif->message     = isset($params['message']) ? (string) $params['message'] : null;
	$notif->url         = isset($params['url']) ? dolinotifSanitizeUrl($params['url']) : null;
	$notif->element_type = isset($params['element_type']) ? (string) $params['element_type'] : null;
	$notif->fk_element  = isset($params['fk_element']) ? (int) $params['fk_element'] : null;

	$res = $notif->create();
	if ($res < 0) {
		dol_syslog('dolinotifSend: '.$notif->error, LOG_ERR);
		return DOLINOTIF_ERROR_DATABASE;
	}

	// HOOK: dolinotif_after_send — for email fallback, broadcast expansion (premium)
	// Premium version will dispatch here to queue an email if unread after X minutes,
	// or to expand a broadcast payload into per-user rows.

	return $res;
}
