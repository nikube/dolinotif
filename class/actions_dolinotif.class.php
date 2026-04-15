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
 *	\file       class/actions_dolinotif.class.php
 *	\ingroup    dolinotif
 *	\brief      Framework glue: Dolibarr's HookManager auto-loads
 *	            /{module}/class/actions_{module}.class.php and instantiates
 *	            Actions{ucfirst(module)}. The actual implementation lives in
 *	            core/hooks/hookDoliNotif.class.php (per spec file structure).
 */

require_once __DIR__.'/../core/hooks/hookDoliNotif.class.php';
