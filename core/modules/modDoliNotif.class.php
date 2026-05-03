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
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * The Software is provided to you by the Licensor under the License,
 * as defined below, subject to the following condition.
 *
 * Without limiting other conditions in the License, the grant of rights
 * under the License will not include, and the License does not grant to
 * you, the right to Sell the Software (Commons Clause).
 */

/**
 *	\defgroup   dolinotif     Module DoliNotif
 *	\brief      DoliNotif module descriptor.
 *	\file       htdocs/custom/dolinotif/core/modules/modDoliNotif.class.php
 *	\ingroup    dolinotif
 *	\brief      Description and activation file for module DoliNotif
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 *	Description and activation class for module DoliNotif
 */
class modDoliNotif extends DolibarrModules
{
	/**
	 *	Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *	@param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Reserved range 185150–185169 (Anatole Conseil) — see https://wiki.dolibarr.org/index.php/List_of_modules_id
		$this->numero = 185151;
		$this->rights_class = 'dolinotif';
		$this->family = "interface";
		$this->module_position = '50';
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		$this->description = "In-app notification center (bell icon, badge, dropdown, toast)";
		$this->descriptionlong = "DoliNotif provides an in-app notification center for Dolibarr. Other modules push notifications through a single public API function dolinotifSend().";

		$this->editor_name = 'DoliNotif';
		$this->editor_url = 'https://github.com/';

		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'bell';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array('/custom/dolinotif/css/dolinotif.css.php'),
			'js'  => array('/custom/dolinotif/js/dolinotif.js'),
			'hooks' => array(
				'data' => array(
					'main',
					'toprightmenu',
				),
				'entity' => '0',
			),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();

		$this->config_page_url = array("setup.php@dolinotif");

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("dolinotif@dolinotif");

		$this->phpmin = array(7, 2);
		$this->need_dolibarr_version = array(18, 0);
		$this->need_javascript_ajax = 1;

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants created when module is enabled
		$this->const = array(
			0 => array('DOLINOTIF_POLLING_INTERVAL', 'chaine', '30', 'Polling interval in seconds', 0, 'current', 0),
			1 => array('DOLINOTIF_RETENTION_DAYS', 'chaine', '90', 'Auto-purge read notifications older than X days', 0, 'current', 0),
			2 => array('DOLINOTIF_MAX_DROPDOWN', 'chaine', '15', 'Max items shown in dropdown', 0, 'current', 0),
			3 => array('DOLINOTIF_BELL_POSITION', 'chaine', 'before_user', 'Bell icon position (before_user | after_bookmark)', 0, 'current', 0),
		);

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();

		// Cron jobs: daily auto-purge
		$this->cronjobs = array(
			0 => array(
				'label' => 'DoliNotifPurgeCronLabel',
				'jobtype' => 'method',
				'class' => '/custom/dolinotif/class/dolinotification.class.php',
				'objectname' => 'DoliNotification',
				'method' => 'purgeOld',
				'parameters' => '',
				'comment' => 'Purge read notifications older than DOLINOTIF_RETENTION_DAYS',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 1,
				'test' => 'isModEnabled("dolinotif")',
				'priority' => 50,
			),
		);

		// No specific rights — anyone logged in can see their own notifications
		$this->rights = array();

		// Menu entries — sits under Home → Admin tools next to the other
		// three custom modules. The bell icon injected via hook remains the
		// primary user-facing entry point; this menu is for admin setup.
		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu'   => 'fk_mainmenu=home,fk_leftmenu=admintools',
			'type'      => 'left',
			'titre'     => 'DoliNotif',
			'mainmenu'  => 'home',
			'leftmenu'  => 'dolinotif',
			'url'       => '/custom/dolinotif/admin/setup.php',
			'langs'     => 'dolinotif@dolinotif',
			'position'  => 120,
			'enabled'   => "isModEnabled('dolinotif')",
			'perms'     => '$user->admin',
			'target'    => '',
			'user'      => 2,
		);
		$this->menu[$r++] = array(
			'fk_menu'   => 'fk_mainmenu=home,fk_leftmenu=dolinotif',
			'type'      => 'left',
			'titre'     => 'Settings',
			'mainmenu'  => 'home',
			'leftmenu'  => 'dolinotif_setup',
			'url'       => '/custom/dolinotif/admin/setup.php',
			'langs'     => 'dolinotif@dolinotif',
			'position'  => 121,
			'enabled'   => "isModEnabled('dolinotif')",
			'perms'     => '$user->admin',
			'target'    => '',
			'user'      => 2,
		);
	}

	/**
	 *	Function called when module is enabled.
	 *	Creates tables, constants, permissions defined in constructor.
	 *
	 *	@param	string	$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>			1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/dolinotif/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *
	 *	@param	string	$options	Options when disabling module
	 *	@return	int<-1,1>			1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
