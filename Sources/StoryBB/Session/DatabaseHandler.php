<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace StoryBB\Session;

use StoryBB\Database\DatabaseAdapter;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\SiteSettings;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

/**
 * Session handler using a PDO connection to read and write data.
 *
 * It works with MySQL, PostgreSQL, Oracle, SQL Server and SQLite and implements
 * different locking strategies to handle concurrent access to the same session.
 * Locking is necessary to prevent loss of data due to race conditions and to keep
 * the session data consistent between read() and write(). With locking, requests
 * for the same session will wait until the other one finished writing. For this
 * reason it's best practice to close a session as early as possible to improve
 * concurrency. PHPs internal files session handler also implements locking.
 *
 * Attention: Since SQLite does not support row level locks but locks the whole database,
 * it means only one session can be accessed at a time. Even different sessions would wait
 * for another to finish. So saving session in SQLite should only be considered for
 * development or prototypes.
 *
 * Session data is a binary string that can contain non-printable characters like the null byte.
 * For this reason it must be saved in a binary column in the database like BLOB in MySQL.
 * Saving it in a character column could corrupt the data. You can use createTable()
 * to initialize a correctly defined table.
 *
 * @see https://php.net/sessionhandlerinterface
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michael Williams <michael.williams@funsational.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class DatabaseHandler extends AbstractSessionHandler
{
	use Database;
	use SiteSettings;

	private const MAX_LIFETIME = 315576000;

	/**
	 * @var bool True when the current session exists but expired according to session.gc_maxlifetime
	 */
	private $sessionExpired = false;

	/**
	 * @var bool Whether gc() has been called
	 */
	private $gcCalled = false;


	public function isSessionExpired()
	{
		return $this->sessionExpired;
	}

	public function open($save_path, $session_name)
	{
		parent::open($save_path, $session_name);
		$this->sessionExpired = false;

		return true;
	}

	public function gc($maxlifetime)
	{
		// We delay gc() to close() so that it is executed outside the transactional and blocking read-write process.
		// This way, pruning expired sessions does not block them from being started while the current session is used.
		$this->gcCalled = true;

		return true;
	}

	protected function doDestroy($session_id)
	{
		$this->db()->query('', '
			DELETE FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}',
			[
				'session_id' => $session_id,
			]
		);

		return true;
	}

	protected function doWrite($session_id, $data)
	{
		$maxlifetime = (int) ini_get('session.gc_maxlifetime'); // @todo databaseSession_lifetime ?

		$db = $this->db();

		$db->query('', '
			UPDATE {db_prefix}sessions
			SET data = {string:data},
				lifetime = {int:expiry},
				session_time = {int:time}
			WHERE session_id = {string:session_id}',
			[
				'session_id' => $session_id,
				'data' => $data,
				'expiry' => time() + $maxlifetime,
				'time' => time(),
			]
		);

		if ($db->affected_rows() == 0)
		{
			$db->insert(DatabaseAdapter::INSERT_INSERT,
				'{db_prefix}sessions',
				['session_id' => 'string', 'data' => 'string', 'lifetime' => 'int', 'session_time' => 'int'],
				[$session_id, $data, time() + $maxlifetime, time()],
				['session_id'],
				DatabaseAdapter::RETURN_NOTHING
			);
		}

		return true;
	}

	public function updateTimestamp($session_id, $data)
	{
		$expiry = time() + (int) ini_get('session.gc_maxlifetime');

		$this->db()->query('', '
			UPDATE {db_prefix}sessions
			SET lifetime = {int:expiry},
				session_time = {int:time}
			WHERE session_id = {string:session_id}',
			[
				'session_id' => $session_id,
				'expiry' => time() + $expiry,
				'time' => time(),
			]
		);

		return true;
	}

	public function close()
	{
		if ($this->gcCalled) {
			$this->gcCalled = false;

			$this->db()->query('', '
				DELETE FROM {db_prefix}sessions
				WHERE lifetime <= {int:time}
					AND lifetime > {int:min}',
				[
					'time' => time(),
					'min' => self::MAX_LIFETIME, // @ databaseSession_lifetime ?
				]
			);

		}

		return true;
	}

	protected function doRead($session_id)
	{
		$db = $this->db();
		$result = $db->query('', '
			SELECT data, lifetime
			FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}
			LIMIT 1',
			[
				'session_id' => $session_id,
			]
		);
		list ($sess_data, $expiry) = $db->fetch_row($result);
		$db->free_result($result);

		if ($expiry <= time())
		{
			$this->sessionExpired = true;
			return '';
		}

		return $sess_data ?? '';
	}
}
