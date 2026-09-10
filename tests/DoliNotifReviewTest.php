<?php
/**
 * DoliNotif — 2026-09 review fixes: display URLs relative to DOL_URL_ROOT,
 * column-width truncation instead of a lost row, unread-first dropdown,
 * and markRead scoped to the owner.
 */

if (!class_exists('DoliTestCase')) {
	$bootstrap = '/var/www/html/custom/dolitest/tests/Support/bootstrap.php';
	if (is_file($bootstrap)) {
		require_once $bootstrap;
	}
}

final class DoliNotifReviewTest extends DoliTestCase
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

	public function testDisplayUrlIsRelativeToTheDolibarrRoot(): void
	{
		$this->assertSame('/dolibarr/custom/doliflow/run.php?id=1', dolinotifDisplayUrl('/custom/doliflow/run.php?id=1', '/dolibarr'));
		$this->assertSame('/dolibarr/custom/doliflow/run.php?id=1', dolinotifDisplayUrl('/dolibarr/custom/doliflow/run.php?id=1', '/dolibarr'), 'dol_buildpath() output is not prefixed twice');
		$this->assertSame('/custom/doliflow/run.php?id=1', dolinotifDisplayUrl('/custom/doliflow/run.php?id=1', ''), 'root install: unchanged');
		$this->assertSame('card.php?id=1', dolinotifDisplayUrl('card.php?id=1', '/dolibarr'), 'page-relative path left alone');
		$this->assertSame('https://x.test/a', dolinotifDisplayUrl('https://x.test/a', '/dolibarr'));
		$this->assertNull(dolinotifDisplayUrl('javascript:alert(1)', '/dolibarr'));
	}

	public function testLongTitleAndUrlAreTruncatedNotLost(): void
	{
		$id = dolinotifSend($this->db, (int) $this->user->id, array(
			'title' => $this->tag.' '.str_repeat('x', 300),
			'url' => '/custom/doliflow/run.php?id=1&'.str_repeat('y', 600),
			'category' => str_repeat('c', 80),
		));
		$this->assertGreaterThan(0, $id);
		$res = $this->db->query("SELECT title, url, category FROM ".MAIN_DB_PREFIX."dolinotif WHERE rowid = ".$id);
		$row = $this->db->fetch_object($res);
		$this->assertSame(255, mb_strlen($row->title));
		$this->assertSame(500, mb_strlen($row->url));
		$this->assertSame(50, mb_strlen($row->category));
	}

	public function testDropdownListsUnreadFirst(): void
	{
		$uid = (int) $this->user->id;
		$entity = (int) $GLOBALS['conf']->entity;
		$n = new DoliNotification($this->db);
		$old = dolinotifSend($this->db, $uid, array('title' => $this->tag.' old unread'));
		$mid = dolinotifSend($this->db, $uid, array('title' => $this->tag.' read'));
		$new = dolinotifSend($this->db, $uid, array('title' => $this->tag.' new unread'));
		$n->markRead($mid, $uid, $entity);
		$ids = array_map(function ($r) { return (int) $r->rowid; }, $n->listForUser($uid, $entity, 200));
		$ids = array_values(array_intersect($ids, array($old, $mid, $new)));
		$this->assertSame(array($new, $old, $mid), $ids);
	}

	public function testUnreadRowsArePurgedAfterTwiceTheRetention(): void
	{
		global $conf;
		$retention = max(1, (int) getDolGlobalInt('DOLINOTIF_RETENTION_DAYS', 90));
		$insert = function (int $isRead, int $ageDays) use ($conf) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."dolinotif (entity, fk_user, type, title, is_read, date_creation)";
			$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->user->id).", 'info', '".$this->db->escape($this->tag.'-p')."', ".$isRead.", DATE_SUB(NOW(), INTERVAL ".$ageDays." DAY))";
			$this->assertNotFalse($this->db->query($sql), (string) $this->db->lasterror());
			return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'dolinotif');
		};
		$readExpired = $insert(1, $retention + 1);
		$unreadRecentish = $insert(0, $retention + 1);
		$unreadExpired = $insert(0, 2 * $retention + 1);

		$this->assertGreaterThanOrEqual(0, (new DoliNotification($this->db))->purgeOld());

		$this->assertTableLacks('dolinotif', "rowid = ".$readExpired);
		$this->assertTableHas('dolinotif', "rowid = ".$unreadRecentish, 'Unread rows get twice the retention');
		$this->assertTableLacks('dolinotif', "rowid = ".$unreadExpired, 'Unread rows are not kept forever');
	}

	public function testLinksAreStoredSanitizedAndResolvedForTheViewer(): void
	{
		global $langs;
		$id = dolinotifSend($this->db, (int) $this->user->id, array(
			'title' => $this->tag.' links',
			'links' => array(
				array('label' => 'PDF', 'url' => '/document.php?modulepart=facture&file=x.pdf'),
				array('label' => 'Bad', 'url' => 'javascript:alert(1)'),
				array('key' => 'DoliNotifNotifications', 'file' => 'dolinotif@dolinotif', 'url' => '/custom/doliflow/run.php?id=1'),
			),
		));
		$this->assertGreaterThan(0, $id);
		$rows = (new DoliNotification($this->db))->listForUser((int) $this->user->id, (int) $GLOBALS['conf']->entity, 200);
		$row = null;
		foreach ($rows as $r) { if ((int) $r->rowid === $id) { $row = $r; } }
		$this->assertNotNull($row);
		$links = dolinotif_resolve_links($langs, $row);
		$this->assertCount(2, $links, 'javascript: link dropped');
		$this->assertSame('PDF', $links[0]['label']);
		$this->assertSame($langs->transnoentities('DoliNotifNotifications'), $links[1]['label'], 'key translated for the viewer');
		$this->assertNotSame('DoliNotifNotifications', $links[1]['label']);
		$this->assertSame('/custom/doliflow/run.php?id=1', $links[1]['url']);
	}

	public function testMarkReadIsScopedToTheOwner(): void
	{
		$uid = (int) $this->user->id;
		$entity = (int) $GLOBALS['conf']->entity;
		$id = dolinotifSend($this->db, $uid, array('title' => $this->tag.' mine'));
		$n = new DoliNotification($this->db);
		$isRead = function () use ($id) {
			$res = $this->db->query("SELECT is_read FROM ".MAIN_DB_PREFIX."dolinotif WHERE rowid = ".$id);
			return (int) $this->db->fetch_object($res)->is_read;
		};
		$this->assertSame(1, $n->markRead($id, $uid + 1, $entity));
		$this->assertSame(0, $isRead(), 'another user cannot mark it read');
		$this->assertSame(1, $n->markRead($id, $uid, $entity + 1));
		$this->assertSame(0, $isRead(), 'another entity cannot mark it read');
		$n->markRead($id, $uid, $entity);
		$this->assertSame(1, $isRead());
	}
}
