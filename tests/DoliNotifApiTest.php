<?php
/**
 * DoliNotif public API tests.
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

final class DoliNotifApiTest extends DoliTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->requireModule('dolinotif');
		require_once DOL_DOCUMENT_ROOT.'/custom/dolinotif/lib/dolinotif.lib.php';
	}

	protected function tearDown(): void
	{
		$this->deleteTaggedRows('dolinotif', 'title');
	}

	public function testSendReturnsRowid(): void
	{
		$rowid = dolinotifSend($this->db, (int) $this->user->id, array(
			'title'    => $this->tag.' hello',
			'message'  => 'greetings',
			'type'     => 'info',
			'category' => $this->tag,
		));
		$this->assertIsInt($rowid);
		$this->assertGreaterThan(0, $rowid);
	}

	public function testSendRequiresTitle(): void
	{
		$rowid = dolinotifSend($this->db, (int) $this->user->id, array(
			'message' => 'missing title',
		));
		$this->assertLessThan(0, $rowid, 'Missing title should yield a negative return');
	}

	public function testPublicErrorMessages(): void
	{
		$this->assertSame('Invalid target user', dolinotifErrorMessage(DOLINOTIF_ERROR_INVALID_USER));
		$this->assertSame('Unable to create notification', dolinotifErrorMessage(DOLINOTIF_ERROR_DATABASE));
		$this->assertSame('', dolinotifErrorMessage(123));
	}

	public function testUnreadCountIncreasesAfterSend(): void
	{
		$before = $this->countRows('dolinotif', "fk_user = ".(int) $this->user->id." AND is_read = 0");
		dolinotifSend($this->db, (int) $this->user->id, array(
			'title'    => $this->tag.' counter',
			'type'     => 'success',
			'category' => $this->tag,
		));
		$after = $this->countRows('dolinotif', "fk_user = ".(int) $this->user->id." AND is_read = 0");
		$this->assertSame($before + 1, $after);
	}
}
