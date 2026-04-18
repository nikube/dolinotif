<?php
/* Copyright (C) 2026 DoliNotif contributors
 * License: GPL v3 + Commons Clause
 * SPDX-License-Identifier: GPL-3.0-or-later WITH Commons-Clause
 */

/**
 *	\file       css/dolinotif.css.php
 *	\ingroup    dolinotif
 *	\brief      DoliNotif stylesheet.
 *
 *  Served without a Dolibarr bootstrap on purpose: every rule below is static,
 *  pulling main.inc.php on a CSS request cost a DB connection per asset and
 *  could 500 the stylesheet under NOREQUIRE* flags on some Dolibarr versions.
 */

header('Content-Type: text/css');
header('Cache-Control: max-age=3600');
?>

/* ---------- Bell in the top menu ----------
 * The bell itself uses Dolibarr's stock `atoplogin valignmiddle` classes
 * on a <span class="fa fa-bell"> so it aligns naturally with the other
 * top-right icons. Only the badge is styled here.
 */
.dolinotif-wrap {
	position: relative;
	display: inline-block;
	vertical-align: middle;
}
.dolinotif-bell {
	position: relative;
	display: inline-block;
}
.dolinotif-badge {
	position: absolute;
	top: -4px;
	right: -4px;
	min-width: 14px;
	padding: 0 4px;
	border-radius: 8px;
	background: #c0392b;
	color: #fff;
	font-size: 10px;
	font-weight: 600;
	line-height: 13px;
	text-align: center;
}

/* ---------- Dropdown ---------- */
.dolinotif-dropdown {
	position: absolute;
	top: 100%;
	right: 0;
	width: 360px;
	max-width: 92vw;
	background: #fff;
	color: #333;
	border: 1px solid #ccc;
	box-shadow: 0 2px 6px rgba(0,0,0,0.15);
	z-index: 10000;
	margin-top: 4px;
	text-align: left;
}

.dolinotif-dropdown-header {
	padding: 6px 10px;
	border-bottom: 1px solid #e0e0e0;
	background: #f5f5f5;
	font-size: 12px;
	text-align: left;
	display: block;
	position: relative;
}
.dolinotif-dropdown-header .dolinotif-title {
	font-weight: 600;
}
.dolinotif-markall {
	float: right;
	font-size: 11px;
	color: #2a7ab8;
	text-decoration: none;
}
.dolinotif-markall:hover { text-decoration: underline; }

.dolinotif-list {
	max-height: 420px;
	overflow-y: auto;
	text-align: left;
}
.dolinotif-empty {
	padding: 16px 10px;
	text-align: center;
	color: #999;
	font-size: 12px;
}

.dolinotif-item {
	display: block;
	padding: 6px 10px;
	border-bottom: 1px solid #eee;
	cursor: pointer;
	font-size: 12px;
	text-align: left;
	color: #333;
}
.dolinotif-item:last-child { border-bottom: none; }
.dolinotif-item:hover      { background: #f5f5f5; }
.dolinotif-item.unread     { background: #fafcff; }
.dolinotif-item.unread .dolinotif-item-title { font-weight: 600; }

.dolinotif-item-title {
	margin: 0 0 2px 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.dolinotif-item-msg {
	color: #666;
	margin: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.dolinotif-item-meta {
	margin-top: 2px;
	font-size: 11px;
	color: #999;
}
.dolinotif-tag {
	display: inline-block;
	padding: 0 4px;
	margin-right: 4px;
	background: #e8e8e8;
	color: #555;
	font-size: 10px;
	border-radius: 2px;
	text-transform: lowercase;
}

/* Hide the dropdown's link column entirely — the whole row is clickable */
.dolinotif-link { display: none; }
.dolinotif-dot  { display: none; }

/* ---------- Toasts ---------- */
.dolinotif-toast-container {
	position: fixed;
	right: 12px;
	bottom: 12px;
	z-index: 11000;
	display: flex;
	flex-direction: column;
	gap: 6px;
	pointer-events: none;
}
.dolinotif-toast {
	pointer-events: auto;
	min-width: 240px;
	max-width: 360px;
	background: #fff;
	color: #333;
	border: 1px solid #ccc;
	border-left: 3px solid #3a87ad;
	padding: 8px 10px;
	font-size: 12px;
	text-align: left;
	box-shadow: 0 2px 6px rgba(0,0,0,0.12);
	cursor: pointer;
}
.dolinotif-toast.info    { border-left-color: #3a87ad; }
.dolinotif-toast.success { border-left-color: #3b9f5a; }
.dolinotif-toast.warning { border-left-color: #e08e0b; }
.dolinotif-toast.error   { border-left-color: #c9302c; }
.dolinotif-toast .dolinotif-toast-title {
	font-weight: 600;
	margin-bottom: 2px;
}
.dolinotif-toast .dolinotif-toast-msg {
	color: #666;
}
