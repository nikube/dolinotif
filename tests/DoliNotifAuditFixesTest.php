<?php
/**
 * DoliNotif — 2026-07 codex audit fixes.
 *
 * Covers:
 *  - purgeOld() entity scoping (a cron tick must not apply one entity's
 *    retention period to every entity's notifications);
 *  - dolinotifSanitizeUrl() (stored javascript:/data: URLs would execute
 *    on click — the frontend assigns notification URLs to location.href);
 *  - listNewSinceId()/latestId() row-id cursor (a burst larger than one
 *    page must be delivered across polls, not skipped forever).
 *
 * Run via the dolitest harness:
 *   cd ~/work/dev/dolitest && ./bin/test --testsuite=dolinotif
 */

if (!class_exists('DoliTestCase')) {
	$bootstrap = '/var/www/html/custom/dolitest/tests/Support/bootstrap.php';
	if (is_file($bootstrap)) {
		require_once $bootstrap;
	}
}

final class DoliNotifAuditFixesTest extends DoliTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->requireModule('dolinotif');
		require_once DOL_DOCUMENT_ROOT.'/custom/dolinotif/class/dolinotification.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/dolinotif/lib/dolinotif.lib.php';
	}

	protected function tearDown(): void
	{
		$this->deleteTaggedRows('dolinotif', 'title');
		parent::tearDown();
	}

	/** Insert a notification row directly; returns rowid. */
	private function makeNotif(int $entity, int $isRead, string $ageExpr = 'NOW()'): int
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolinotif";
		$sql .= " (entity, fk_user, type, title, is_read, date_creation)";
		$sql .= " VALUES (".$entity.", ".((int) $this->user->id).", 'info',";
		$sql .= " '".$this->db->escape($this->tag.'-n')."', ".$isRead.", ".$ageExpr.")";
		$this->assertNotFalse($this->db->query($sql), (string) $this->db->lasterror());
		return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'dolinotif');
	}

	// ---- Fix 1: entity-scoped purge -------------------------------------

	public function testPurgeOldIsEntityScoped(): void
	{
		global $conf;
		$old = "DATE_SUB(NOW(), INTERVAL 400 DAY)"; // older than any retention
		$mineId = $this->makeNotif((int) $conf->entity, 1, $old);
		$foreignId = $this->makeNotif(99999, 1, $old);

		$notif = new DoliNotification($this->db);
		$this->assertGreaterThanOrEqual(0, $notif->purgeOld());

		$this->assertTableLacks('dolinotif', "rowid = ".$mineId, 'Current-entity expired notification must be purged');
		$this->assertTableHas('dolinotif', "rowid = ".$foreignId, "Another entity's notification must survive this entity's purge");
	}

	// ---- Fix 3: URL scheme restriction -----------------------------------

	public function testSanitizeUrlAllowsRelativeAndHttp(): void
	{
		$this->assertSame('/custom/doliflow/run.php?id=3', dolinotifSanitizeUrl('/custom/doliflow/run.php?id=3'));
		$this->assertSame('card.php?id=5', dolinotifSanitizeUrl('card.php?id=5'));
		$this->assertSame('https://example.com/x', dolinotifSanitizeUrl('https://example.com/x'));
		$this->assertSame('http://example.com/x', dolinotifSanitizeUrl('http://example.com/x'));
	}

	public function testSanitizeUrlRejectsActiveSchemes(): void
	{
		$this->assertNull(dolinotifSanitizeUrl('javascript:alert(1)'));
		$this->assertNull(dolinotifSanitizeUrl('JaVaScRiPt:alert(1)'));
		$this->assertNull(dolinotifSanitizeUrl("java\tscript:alert(1)"), 'Control chars must not hide the scheme');
		$this->assertNull(dolinotifSanitizeUrl(' javascript:alert(1)'));
		$this->assertNull(dolinotifSanitizeUrl('data:text/html,<script>alert(1)</script>'));
		$this->assertNull(dolinotifSanitizeUrl('vbscript:msgbox(1)'));
		$this->assertNull(dolinotifSanitizeUrl('//evil.example.com/x'), 'Scheme-relative URLs escape to another origin');
		$this->assertNull(dolinotifSanitizeUrl(''));
		$this->assertNull(dolinotifSanitizeUrl(null));
	}

	public function testSendDropsUnsafeUrl(): void
	{
		global $conf;
		$id = dolinotifSend($this->db, (int) $this->user->id, array(
			'title' => $this->tag.'-n',
			'url'   => 'javascript:alert(1)',
		));
		$this->assertGreaterThan(0, $id);
		$this->assertTableHas('dolinotif', "rowid = ".$id." AND url IS NULL", 'Unsafe URL must be stored as NULL');
	}

	// ---- Fix 4: row-id cursor ---------------------------------------------

	public function testListNewSinceIdPagesBurstsWithoutSkipping(): void
	{
		global $conf;
		$entity = (int) $conf->entity;
		$notif = new DoliNotification($this->db);

		$cursor = $notif->latestId((int) $this->user->id, $entity);
		$this->assertGreaterThanOrEqual(0, $cursor);

		$ids = array();
		for ($i = 0; $i < 25; $i++) {
			$ids[] = $this->makeNotif($entity, 0);
		}

		// First poll: the 20 OLDEST new rows, in rowid order.
		$page1 = $notif->listNewSinceId((int) $this->user->id, $entity, $cursor, 20);
		$this->assertIsArray($page1);
		$this->assertCount(20, $page1);
		$this->assertSame($ids[0], (int) $page1[0]->rowid, 'Pages must start at the oldest unseen row');

		// Advance the cursor exactly like ajax/notifications.php does.
		foreach ($page1 as $r) {
			$cursor = max($cursor, (int) $r->rowid);
		}

		// Second poll: the 5 remaining rows — nothing skipped.
		$page2 = $notif->listNewSinceId((int) $this->user->id, $entity, $cursor, 20);
		$this->assertIsArray($page2);
		$this->assertCount(5, $page2);
		$this->assertSame($ids[20], (int) $page2[0]->rowid);
		$this->assertSame($ids[24], (int) $page2[4]->rowid);
	}

	public function testLatestIdInitialisesCursor(): void
	{
		global $conf;
		$entity = (int) $conf->entity;
		$notif = new DoliNotification($this->db);

		$id = $this->makeNotif($entity, 0);
		$this->assertGreaterThanOrEqual($id, $notif->latestId((int) $this->user->id, $entity));
	}
}
