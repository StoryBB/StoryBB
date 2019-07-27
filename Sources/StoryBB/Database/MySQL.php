<?php

/**
 * Any database connector should implement this.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Database;

use StoryBB\Database\DatabaseAdapter;
use StoryBB\Database\Exception\ConnectionFailedException;
use StoryBB\Database\Exception\CouldNotSelectDatabaseException;

/**
 * Database adapter for MySQL.
 */
class MySQL implements DatabaseAdapter
{
	/** @var object $connection The connection object */
	protected $connection = null;

	/** @var string $db_prefix The table prefix used in this installation */
	protected $db_prefix = null;

	/** @var string $db_server The database server for this connection */
	protected $db_server = null;

	/** @var string $db_name The name of the database for this connection */
	protected $db_name = null;

	/** @var string $db_user The user for this connection */
	protected $db_user = null;

	/** @var string $db_password The password for this connection */
	protected $db_passwd = null;

	/** @var int $db_port The port for the server for this connection */
	protected $db_port = 0;

	/**
	 * Sets the database table prefix this instance should use.
	 *
	 * @param string $db_prefix The prefix to use.
	 */
	public function set_prefix(string $db_prefix)
	{
		$this->db_prefix = $db_prefix;
	}

	/**
	 * Sets the connection details that this connector should use for the database.
	 *
	 * @param string $db_server The database server to connect to.
	 * @param string $db_name The name of the database to connect to.
	 * @param string $db_user The database user.
	 * @param string $db_passwd The database user's password.
	 * @param int $db_port The databsae port to use, 0 for the default.
	 */
	public function set_server(string $db_server, string $db_name, string $db_user, string $db_passwd, int $db_port = 0)
	{
		$this->db_server = $db_server;
		$this->db_name = $db_name;
		$this->db_user = $db_user;
		$this->db_passwd = $db_passwd;
		$this->db_port = $db_port;
	}

	/**
	 * Connect to the database.
	 *
	 * @param array $options Options for connecting the database.
	 * @return The database connection object.
	 */
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

	/**
	 * Select database on the current connection.
	 *
	 * @param string $database The database
	 * @return bool Whether the database was selected
	 */
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

	/**
	 * Checks whether the database connection is still active.
	 *
	 * @return bool True if the connection is still active
	 */
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

	/**
	 * Create a database with the current connection and the specified name.
	 *
	 * @param string $db_name The database name to be created.
	 * @return bool True if could be created.
	 */
	public function create_database(string $db_name): bool
	{
		global $smcFunc;

		$smcFunc['db_query']('', "
			CREATE DATABASE IF NOT EXISTS `$db_name`",
			array(
				'security_override' => true,
				'db_error_skip' => true,
			)
		);
		try
		{
			$this->select_db($db_name);
			return true;
		}
		catch (CouldNotSelectDatabaseException $e)
		{
			return false;
		}
	}
}
