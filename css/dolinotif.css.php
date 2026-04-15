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
 *	\file       css/dolinotif.css.php
 *	\ingroup    dolinotif
 *	\brief      DoliNotif stylesheet (served via PHP for dynamic tweaks if needed).
 */

if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

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

header('Content-Type: text/css');

?>

.dolinotif-wrap {
	position: relative;
	display: inline-block;
	vertical-align: middle;
}

.dolinotif-bell {
	position: relative;
	display: inline-flex;
	align-items: center;
	padding: 0 8px;
	text-decoration: none;
	color: inherit;
	cursor: pointer;
}
.dolinotif-bell-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	line-height: 1;
}
.dolinotif-bell:hover .dolinotif-bell-icon {
	opacity: 0.75;
}

.dolinotif-badge {
	position: absolute;
	top: -4px;
	right: -2px;
	min-width: 16px;
	height: 16px;
	padding: 0 4px;
	border-radius: 8px;
	background: #e55353;
	color: #fff;
	font-size: 10px;
	font-weight: 700;
	line-height: 16px;
	text-align: center;
	box-shadow: 0 0 0 2px #fff;
	box-sizing: border-box;
}

.dolinotif-dropdown {
	position: absolute;
	top: 100%;
	right: 0;
	width: 360px;
	max-width: 92vw;
	background: #fff;
	color: #333;
	border: 1px solid #ddd;
	border-radius: 6px;
	box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
	z-index: 10000;
	margin-top: 6px;
	overflow: hidden;
}

.dolinotif-dropdown-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 10px 12px;
	border-bottom: 1px solid #eee;
	background: #f7f7f7;
	font-size: 13px;
}
.dolinotif-dropdown-header .dolinotif-title {
	font-weight: 600;
}
.dolinotif-markall {
	font-size: 12px;
	color: #2a7ab8;
	text-decoration: none;
	cursor: pointer;
}
.dolinotif-markall:hover {
	text-decoration: underline;
}

.dolinotif-list {
	max-height: 420px;
	overflow-y: auto;
}
.dolinotif-empty {
	padding: 24px 12px;
	text-align: center;
	color: #888;
	font-size: 13px;
}

.dolinotif-item {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	padding: 10px 12px;
	border-bottom: 1px solid #f0f0f0;
	cursor: pointer;
	font-size: 13px;
	background: #fff;
}
.dolinotif-item:last-child {
	border-bottom: none;
}
.dolinotif-item:hover {
	background: #f5f9ff;
}
.dolinotif-item.unread {
	background: #f0f7ff;
}
.dolinotif-item.unread:hover {
	background: #e6f0ff;
}

.dolinotif-dot {
	flex: 0 0 auto;
	width: 10px;
	height: 10px;
	margin-top: 5px;
	border-radius: 50%;
	background: #3a87ad;
}
.dolinotif-dot.info    { background: #3a87ad; }
.dolinotif-dot.success { background: #3b9f5a; }
.dolinotif-dot.warning { background: #e08e0b; }
.dolinotif-dot.error   { background: #c9302c; }

.dolinotif-body {
	flex: 1 1 auto;
	min-width: 0;
}
.dolinotif-item-title {
	font-weight: 600;
	margin-bottom: 2px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.dolinotif-item-msg {
	color: #666;
	font-size: 12px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.dolinotif-item-meta {
	margin-top: 3px;
	font-size: 11px;
	color: #999;
	display: flex;
	gap: 8px;
	align-items: center;
}
.dolinotif-tag {
	display: inline-block;
	padding: 1px 6px;
	border-radius: 3px;
	background: #e8e8e8;
	color: #555;
	font-size: 10px;
	text-transform: uppercase;
}

.dolinotif-link {
	flex: 0 0 auto;
	align-self: center;
	color: #2a7ab8;
	text-decoration: none;
	font-size: 16px;
	padding: 0 4px;
}
.dolinotif-link:hover {
	color: #1a4a7a;
}

/* Toasts */
.dolinotif-toast-container {
	position: fixed;
	right: 16px;
	bottom: 16px;
	z-index: 11000;
	display: flex;
	flex-direction: column;
	gap: 8px;
	pointer-events: none;
}
.dolinotif-toast {
	pointer-events: auto;
	min-width: 260px;
	max-width: 360px;
	background: #fff;
	color: #333;
	border: 1px solid #ddd;
	border-left: 4px solid #3a87ad;
	border-radius: 4px;
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
	padding: 10px 12px;
	font-size: 13px;
	cursor: pointer;
	animation: dolinotif-toast-in 0.2s ease-out;
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
	font-size: 12px;
}
@keyframes dolinotif-toast-in {
	from { opacity: 0; transform: translateY(12px); }
	to   { opacity: 1; transform: translateY(0); }
}
