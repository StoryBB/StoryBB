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

	public function get_title()
	{
		return 'MySQL';
	}

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

	public function num_rows($result)
	{
		return mysqli_num_rows($result);
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
			[
				'security_override' => true,
				'db_error_skip' => true,
			]
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

	public function truncate_table(string $tablename)
	{
		global $smcFunc;

		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}' . $tablename,
			[]
		);
	}

	public function is_case_sensitive()
	{
		return false;
	}

	public function free_result($result)
	{
		mysqli_free_result($result);
	}

	/**
	 * This function lists all tables in the database.
	 * The listing could be filtered according to $filter.
	 *
	 * @param string $filter String to filter by or null to list all tables
	 * @param string $db string The database name or null to use the current DB
	 * @return array An array of table names
	 */
	public function list_tables($filter = null, $db = null)
	{
		global $smcFunc;

		$db = $db === null ? $this->db_name : $db;
		$db = trim($db);
		$filter = $filter === null ? '' : ' LIKE \'' . $filter . '\'';

		$request = $smcFunc['db_query']('', '
			SHOW TABLES
			FROM `{raw:db}`
			{raw:filter}',
			[
				'db' => $db[0] == '`' ? strtr($db, ['`' => '']) : $db,
				'filter' => $filter,
			]
		);
		$tables = [];
		while ($row = $smcFunc['db_fetch_row']($request))
			$tables[] = $row[0];
		$smcFunc['db']->free_result($request);

		return $tables;
	}


	/**
	 * Database error!
	 * Backtrace, log, try to fix.
	 *
	 * @param string $db_string The DB string
	 */
	public function error($db_string)
	{
		global $txt, $context, $modSettings, $db_show_debug, $smcFunc;

		// Get the file and line numbers.
		list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

		// This is the error message...
		$query_error = mysqli_error($this->connection);
		$query_errno = mysqli_errno($this->connection);

		// Error numbers:
		//    1016: Can't open file '....MYI'
		//    1030: Got error ??? from table handler.
		//    1034: Incorrect key file for table.
		//    1035: Old key file for table.
		//    1205: Lock wait timeout exceeded.
		//    1213: Deadlock found.
		//    2006: Server has gone away.
		//    2013: Lost connection to server during query.

		// Log the error.
		if ($query_errno != 1213 && $query_errno != 1205 && function_exists('log_error'))
		{
			log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n$db_string" : ''), 'database', $file, $line);
		}

		// Check for the "lost connection" or "deadlock found" errors - and try it just one more time.
		if (in_array($query_errno, [1205, 1213, 2006, 2013]))
		{
			if (in_array($query_errno, [2006, 2013]))
			{
				try
				{
					// The DB object still has all the details, but try to reconnect.
					$this->connect();
					$this->select_db();
				}
				catch (\Exception $e)
				{
					$this->connection = false;
				}
			}

			if ($this->connection)
			{
				// Try a deadlock more than once more.
				for ($n = 0; $n < 4; $n++)
				{
					$ret = $smcFunc['db_query']('', $db_string, false, false);

					$new_errno = mysqli_errno($this->connection);
					if ($ret !== false || in_array($new_errno, [1205, 1213]))
						break;
				}

				// If it failed again, shucks to be you... we're not trying it over and over.
				if ($ret !== false)
				{
					return $ret;
				}
			}
		}
		// Are they out of space, perhaps?
		elseif ($query_errno == 1030 && (strpos($query_error, ' -1 ') !== false || strpos($query_error, ' 28 ') !== false || strpos($query_error, ' 12 ') !== false))
		{
			if (!isset($txt))
			{
				$query_error .= ' - check database storage space.';
			}
			else
			{
				if (!isset($txt['mysql_error_space']))
					loadLanguage('Errors');

				$query_error .= !isset($txt['mysql_error_space']) ? ' - check database storage space.' : $txt['mysql_error_space'];
			}
		}

		// Nothing's defined yet... just die with it.
		if (empty($context) || empty($txt))
		{
			die($query_error);
		}

		// Show an error message, if possible.
		$context['error_title'] = $txt['database_error'];
		$context['error_message'] = $txt['try_again'];
		if (allowedTo('admin_forum'))
		{
			$context['error_message'] = nl2br($query_error) . '<br>' . $txt['file'] . ': ' . $file . '<br>' . $txt['line'] . ': ' . $line;
		}

		if (allowedTo('admin_forum') && isset($db_show_debug) && $db_show_debug === true)
		{
			$context['error_message'] .= '<br><br>' . nl2br($db_string);
		}

		// It's already been logged... don't log it again.
		fatal_error($context['error_message'], false);
	}

	/**
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param string $error_message The error message
	 * @param string $log_message The message to log
	 * @param string|bool $error_type What type of error this is
	 * @param string $file The file the error occurred in
	 * @param int $line What line of $file the code which generated the error is on
	 * @return void|array Returns an array with the file and line if $error_type is 'return'
	 */
	pulic function error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
	{
		if (empty($log_message))
		{
			$log_message = $error_message;
		}

		foreach (debug_backtrace() as $step)
		{
			// Did it come from inside this class? If so, don't care.
			if (isset($step['class']) && $step['class'] == __CLASS__)
			{
				continue;
			}
			// Is it from something that looks normal? If so, add the place in question 
			if (strpos($step['function'], 'query') === false && !in_array(substr($step['function'], 0, 7), ['sbb_db_', 'preg_re', 'db_erro', 'call_us']) && strpos($step['function'], '__') !== 0)
			{
				$log_message .= '<br>Function: ' . (!empty($step['class']) ? $step['class'] . '::' : '') . $step['function'];
				break;
			}

			if (isset($step['line']))
			{
				$file = $step['file'];
				$line = $step['line'];
			}
		}

		// A special case - we want the file and line numbers for debugging.
		if ($error_type == 'return')
			return [$file, $line];

		// Is always a critical error.
		if (function_exists('log_error'))
		{
			log_error($log_message, 'critical', $file, $line);
		}

		if (function_exists('fatal_error'))
		{
			fatal_error($error_message, false);

			// Cannot continue...
			exit;
		}
		elseif ($error_type)
		{
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), $error_type);
		}
		else
		{
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));
		}
	}
}
