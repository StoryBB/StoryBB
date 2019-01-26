<?php

/**
 * Any database connector should implement this.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Database;

use StoryBB\Database\DatabaseAdapter;
use StoryBB\Database\Exception\ConnectionFailedException;
use StoryBB\Database\Exception\CouldNotSelectDatabaseException;

class MySQL implements DatabaseAdapter
{
	protected $connection = null;
	protected $db_prefix = null;
	protected $db_server = null;
	protected $db_name = null;
	protected $db_user = null;
	protected $db_passwd = null;
	protected $db_port = 0;

	public function set_prefix(string $db_prefix)
	{
		$this->db_prefix = $db_prefix;
	}

	public function set_server(string $db_server, string $db_name, string $db_user, string $db_passwd, int $db_port = 0)
	{
		$this->db_server = $db_server;
		$this->db_name = $db_name;
		$this->db_user = $db_user;
		$this->db_passwd = $db_passwd;
		$this->db_port = $db_port;
	}

	public function connect(array $options = [])
	{
		$db_server = $this->db_server;
		if (!empty($options['persist']))
		{
			$db_server = 'p:' . $this->db_server;
		}

		$this->connection = mysqli_init();

		$flags = MYSQLI_CLIENT_FOUND_ROWS;

		$success = false;

		if ($this->connection) {
			$success = mysqli_real_connect($this->connection, $db_server, $this->db_user, $this->db_passwd, '', $this->db_port, null, $flags);
		}

		if ($success === false)
		{
			throw new ConnectionFailedException;
		}

		// Select the database, unless told not to
		if (empty($options['dont_select_db']))
		{
			$this->select_db($this->db_name);
		}

		mysqli_query($this->connection, "SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");

		mysqli_set_charset($this->connection, 'utf8mb4');

		// Dirty compatibility hack.
		global $db_connection;
		$db_connection = $this->connection;
	}

	public function select_db(string $database = null)
	{
		if ($database === null)
		{
			$database = $this->db_name;
		}
		if (!mysqli_select_db($this->connection, $database))
		{
			throw new CouldNotSelectDatabaseException;
		}
	}

	public function connection_active(): bool
	{
		return is_object($this->connection);
	}

	/**
	 * Return the number of rows affected by the last data-changing query on this DB connection.
	 *
	 * @return int The number of rows affected by the last query
	 */
	public function affected_rows(): int
	{
		return mysqli_affected_rows($this->connection);
	}

	/**
	 * Gets the ID of the most recently inserted row on this DB connection.
	 *
	 * @return int The ID of the most recently inserted row
	 */
	public function inserted_id()
	{
		return mysqli_insert_id($this->connection);
	}

	/**
	 * Do a transaction action on this DB connection.
	 *
	 * @param string $type The step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @return bool True if successful, false otherwise
	 */
	public function transaction(string $type): bool
	{
		$ops = [
			'begin' => 'BEGIN',
			'rollback' => 'ROLLBACK',
			'commit' => 'COMMIT',
		];
		if (!isset($ops[$type]))
		{
			return false;
		}

		return @mysqli_query($this->connection, $ops[$type]);
	}
}
