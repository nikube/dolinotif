-- Copyright (C) 2026 DoliNotif contributors
--
-- Licensed under the GNU General Public License v3 or later (GPL-3.0-or-later)
-- with the Commons Clause restriction.

ALTER TABLE llx_dolinotif ADD INDEX idx_dolinotif_fk_user (fk_user, is_read, entity);
ALTER TABLE llx_dolinotif ADD INDEX idx_dolinotif_date (date_creation);
