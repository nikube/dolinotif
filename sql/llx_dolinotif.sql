-- Copyright (C) 2026 DoliNotif contributors
--
-- Licensed under the GNU General Public License v3 or later (GPL-3.0-or-later)
-- with the Commons Clause restriction.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

CREATE TABLE llx_dolinotif (
	rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER DEFAULT 1 NOT NULL,
	fk_user         INTEGER NOT NULL,
	type            VARCHAR(20) NOT NULL DEFAULT 'info',
	category        VARCHAR(50) DEFAULT NULL,
	title           VARCHAR(255) NOT NULL,
	message         TEXT,
	url             VARCHAR(500),
	element_type    VARCHAR(50),
	fk_element      INTEGER,
	is_read         TINYINT DEFAULT 0 NOT NULL,
	date_creation   DATETIME NOT NULL,
	date_read       DATETIME,
	tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
