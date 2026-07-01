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
 * Commons Clause: you may not Sell the Software.
 */

/**
 *	\file       class/dolinotification.class.php
 *	\ingroup    dolinotif
 *	\brief      Thin CRUD wrapper for llx_dolinotif
 */

/**
 *	Class DoliNotification — thin CRUD wrapper for llx_dolinotif
 */
class DoliNotification
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var string[] Errors stack
	 */
	public $errors = array();

	/**
	 * @var string Table name (without MAIN_DB_PREFIX)
	 */
	public $table_element = 'dolinotif';

	public $id;
	public $entity;
	public $fk_user;
	public $type;
	public $category;
	public $title;
	public $message;
	public $url;
	public $element_type;
	public $fk_element;
	public $is_read;
	public $date_creation;
	public $date_read;

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
	 *	Create a notification row.
	 *	Assumes $this->fk_user, $this->title and other properties are set.
	 *
	 *	@return	int		>0 = rowid, <0 = error
	 */
	public function create()
	{
		global $conf;

		if (empty($this->fk_user) || (int) $this->fk_user <= 0) {
			$this->error = 'fk_user is required';
			return -1;
		}
		if (empty($this->title)) {
			$this->error = 'title is required';
			return -2;
		}

		$allowedTypes = array('info', 'success', 'warning', 'error');
		$type = in_array((string) $this->type, $allowedTypes, true) ? (string) $this->type : 'info';

		$entity = ($this->entity !== null && $this->entity !== '') ? (int) $this->entity : (int) $conf->entity;
		$now = $this->db->idate(dol_now());

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolinotif (";
		$sql .= "entity, fk_user, type, category, title, message, url, element_type, fk_element, is_read, date_creation";
		$sql .= ") VALUES (";
		$sql .= (int) $entity;
		$sql .= ", ".(int) $this->fk_user;
		$sql .= ", '".$this->db->escape($type)."'";
		$sql .= ", ".($this->category !== null && $this->category !== '' ? "'".$this->db->escape($this->category)."'" : "NULL");
		$sql .= ", '".$this->db->escape($this->title)."'";
		$sql .= ", ".($this->message !== null && $this->message !== '' ? "'".$this->db->escape($this->message)."'" : "NULL");
		$sql .= ", ".($this->url !== null && $this->url !== '' ? "'".$this->db->escape($this->url)."'" : "NULL");
		$sql .= ", ".($this->element_type !== null && $this->element_type !== '' ? "'".$this->db->escape($this->element_type)."'" : "NULL");
		$sql .= ", ".(!empty($this->fk_element) ? (int) $this->fk_element : "NULL");
		$sql .= ", 0";
		$sql .= ", '".$this->db->escape($now)."'";
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -3;
		}

		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX."dolinotif");
		return $this->id;
	}

	/**
	 *	Mark one notification as read (security-scoped).
	 *
	 *	@param	int		$id			Notification rowid
	 *	@param	int		$fk_user	Owner user id
	 *	@param	int		$entity		Entity
	 *	@return	int					>0 OK, <0 KO
	 */
	public function markRead($id, $fk_user, $entity)
	{
		$now = $this->db->idate(dol_now());
		$sql = "UPDATE ".MAIN_DB_PREFIX."dolinotif";
		$sql .= " SET is_read = 1, date_read = '".$this->db->escape($now)."'";
		$sql .= " WHERE rowid = ".(int) $id;
		$sql .= " AND fk_user = ".(int) $fk_user;
		$sql .= " AND entity = ".(int) $entity;
		$sql .= " AND is_read = 0";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 *	Mark all unread notifications as read for a user.
	 *
	 *	@param	int		$fk_user	Owner user id
	 *	@param	int		$entity		Entity
	 *	@return	int					>=0 count of updated rows, <0 KO
	 */
	public function markAllRead($fk_user, $entity)
	{
		$now = $this->db->idate(dol_now());
		$sql = "UPDATE ".MAIN_DB_PREFIX."dolinotif";
		$sql .= " SET is_read = 1, date_read = '".$this->db->escape($now)."'";
		$sql .= " WHERE fk_user = ".(int) $fk_user;
		$sql .= " AND entity = ".(int) $entity;
		$sql .= " AND is_read = 0";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return (int) $this->db->affected_rows($sql);
	}

	/**
	 *	Count unread notifications for a user.
	 *
	 *	@param	int		$fk_user	Owner user id
	 *	@param	int		$entity		Entity
	 *	@return	int					Unread count (>=0), <0 on error
	 */
	public function countUnread($fk_user, $entity)
	{
		$sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."dolinotif";
		$sql .= " WHERE fk_user = ".(int) $fk_user;
		$sql .= " AND entity = ".(int) $entity;
		$sql .= " AND is_read = 0";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		return $obj ? (int) $obj->nb : 0;
	}

	/**
	 *	List notifications for a user (most recent first).
	 *
	 *	@param	int		$fk_user	Owner user id
	 *	@param	int		$entity		Entity
	 *	@param	int		$limit		Max rows
	 *	@return	array<int,object>|int	Array of rows or <0 on error
	 */
	public function listForUser($fk_user, $entity, $limit = 15)
	{
		$limit = (int) $limit;
		if ($limit <= 0) {
			$limit = 15;
		}
		if ($limit > 200) {
			$limit = 200;
		}

		$sql = "SELECT rowid, type, category, title, message, url, element_type, fk_element, is_read, date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX."dolinotif";
		$sql .= " WHERE fk_user = ".(int) $fk_user;
		$sql .= " AND entity = ".(int) $entity;
		$sql .= " ORDER BY date_creation DESC, rowid DESC";
		$sql .= " LIMIT ".$limit;

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$out = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$out[] = $obj;
		}
		return $out;
	}

	/**
	 *	List notifications newer than a given datetime for a user.
	 *
	 *	@param	int		$fk_user	Owner user id
	 *	@param	int		$entity		Entity
	 *	@param	string	$since		ISO-like datetime 'YYYY-MM-DD HH:MM:SS'
	 *	@param	int		$limit		Max rows (sanity cap)
	 *	@return	array<int,object>|int	Array of rows or <0 on error
	 */
	public function listNewSince($fk_user, $entity, $since, $limit = 20)
	{
		$limit = (int) $limit;
		if ($limit <= 0 || $limit > 100) {
			$limit = 20;
		}
		$sql = "SELECT rowid, type, category, title, message, url, element_type, fk_element, is_read, date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX."dolinotif";
		$sql .= " WHERE fk_user = ".(int) $fk_user;
		$sql .= " AND entity = ".(int) $entity;
		$sql .= " AND date_creation > '".$this->db->escape($since)."'";
		$sql .= " ORDER BY date_creation DESC, rowid DESC";
		$sql .= " LIMIT ".$limit;

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$out = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$out[] = $obj;
		}
		return $out;
	}

	/**
	 *	Auto-purge read notifications older than DOLINOTIF_RETENTION_DAYS.
	 *	Called by the daily cron job.
	 *
	 *	@return	int		>=0 deleted rows, <0 on error
	 */
	public function purgeOld()
	{
		$retention = (int) getDolGlobalInt('DOLINOTIF_RETENTION_DAYS', 90);
		if ($retention <= 0) {
			$retention = 90;
		}

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."dolinotif";
		$sql .= " WHERE date_creation < DATE_SUB(NOW(), INTERVAL ".$retention." DAY)";
		$sql .= " AND is_read = 1";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		$this->output = 'DoliNotif purge complete (retention='.$retention.' days)';
		return 0;
	}
}
