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
 *	\file       core/hooks/hookDoliNotif.class.php
 *	\ingroup    dolinotif
 *	\brief      Hook overload: inject bell icon into top-right menu.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 *	Class ActionsDolinotif — injects the notification bell into the top menu.
 *
 *	Note: the HookManager auto-loads `/{module}/class/actions_{module}.class.php`
 *	and instantiates `Actions{ucfirst(module)}`. For `dolinotif`, that is
 *	`ActionsDolinotif`. We define the real class here (as required by the spec
 *	file structure) and expose it via a thin wrapper at the expected path.
 */
class ActionsDolinotif extends CommonHookActions
{
	/**
	 * @var DoliDB
	 */
	public $db;

	/**
	 * @var string
	 */
	public $error = '';

	/**
	 * @var string[]
	 */
	public $errors = array();

	/**
	 * @var mixed[]
	 */
	public $results = array();

	/**
	 * @var ?string
	 */
	public $resprints;

	/**
	 * @var int
	 */
	public $priority;

	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 *	Hook: printTopRightMenu — add the bell icon + dropdown container.
	 *
	 *	@param	array<string,mixed>	$parameters		Hook parameters
	 *	@param	?CommonObject		$object			(unused)
	 *	@param	?string				$action			(unused)
	 *	@param	?HookManager		$hookmanager	Hook manager
	 *	@return	int									0 to append, 1 to replace
	 */
	public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		$this->resprints = '';

		if (!isModEnabled('dolinotif')) {
			return 0;
		}
		if (empty($user) || $user->id <= 0) {
			return 0;
		}

		$langs->load('dolinotif@dolinotif');

		// Unread count — single cheap indexed query.
		$unread = 0;
		$sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."dolinotif";
		$sql .= " WHERE fk_user = ".(int) $user->id;
		$sql .= " AND entity = ".(int) $conf->entity;
		$sql .= " AND is_read = 0";
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($obj = $this->db->fetch_object($resql)) {
				$unread = (int) $obj->nb;
			}
		}

		$badgeDisplay = $unread > 0 ? '' : ' style="display:none;"';
		$badgeLabel   = $unread > 99 ? '99+' : (string) $unread;

		$ajaxUrl = DOL_URL_ROOT.'/custom/dolinotif/ajax/notifications.php';
		$token = newToken();

		// Translated JS labels (transnoentities: real UTF-8, the JS escapes).
		$jsLabels = json_encode(array(
			'noNotifications'   => $langs->transnoentities('DoliNotifNoNotifications'),
			'loadError'         => $langs->transnoentities('DoliNotifLoadError'),
			'now'               => $langs->transnoentities('DoliNotifJustNow'),
			'minutesAgo'        => $langs->transnoentities('DoliNotifMinutesAgo'),
			'hoursAgo'          => $langs->transnoentities('DoliNotifHoursAgo'),
			'daysAgo'           => $langs->transnoentities('DoliNotifDaysAgo'),
			'moreNotifications' => $langs->transnoentities('DoliNotifMore'),
		));

		$html = '';
		$html .= '<div class="login_block_elem dolinotif-wrap" id="dolinotif-wrap"'
			.' data-ajax="'.dol_escape_htmltag($ajaxUrl).'"'
			.' data-token="'.dol_escape_htmltag($token).'"'
			.' data-polling="'.(int) getDolGlobalInt('DOLINOTIF_POLLING_INTERVAL', 30).'"'
			.' data-max="'.(int) getDolGlobalInt('DOLINOTIF_MAX_DROPDOWN', 15).'"'
			.' data-labels="'.dol_escape_htmltag($jsLabels).'">';

		// Bell button — use Dolibarr-standard classes (atoplogin valignmiddle)
		// so it aligns with the other top-right icons (bookmarks, help, logout).
		$html .= '<a href="#" class="login dolinotif-bell" id="dolinotif-bell" title="'.dol_escape_htmltag($langs->trans('DoliNotifNotifications')).'">';
		$html .= '<span class="fa fa-bell atoplogin valignmiddle" aria-hidden="true"></span>';
		$html .= '<span class="dolinotif-badge" id="dolinotif-badge"'.$badgeDisplay.'>'.dol_escape_htmltag($badgeLabel).'</span>';
		$html .= '</a>';

		// Dropdown (populated by JS)
		$html .= '<div class="dolinotif-dropdown" id="dolinotif-dropdown" style="display:none;">';
		$html .= '  <div class="dolinotif-dropdown-header">';
		$html .= '    <span class="dolinotif-title">'.dol_escape_htmltag($langs->trans('DoliNotifNotifications')).'</span>';
		$html .= '    <a href="#" class="dolinotif-markall" id="dolinotif-markall">'.dol_escape_htmltag($langs->trans('DoliNotifMarkAllAsRead')).'</a>';
		$html .= '  </div>';
		$html .= '  <div class="dolinotif-list" id="dolinotif-list">';
		$html .= '    <div class="dolinotif-empty">'.dol_escape_htmltag($langs->trans('DoliNotifLoading')).'</div>';
		$html .= '  </div>';
		$html .= '</div>';

		// Toast container
		$html .= '<div class="dolinotif-toast-container" id="dolinotif-toast-container"></div>';

		$html .= '</div>';

		$this->resprints = $html;
		return 0;
	}
}
