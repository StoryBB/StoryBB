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
use StoryBB\Helper\IP;
use StoryBB\StringLibrary;

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
		$this->query('', "
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
		$this->query('truncate_table', '
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

		$request = $this->query('', '
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
		$this->free_result($request);

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
		global $txt, $context, $modSettings, $db_show_debug;

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
					$ret = $this->query('', $db_string, false, false);

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
	public function error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
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

	/**
	 * Do a query.  Takes care of errors too.
	 *
	 * @param string $identifier An identifier.
	 * @param string $db_string The database string
	 * @param array $db_values = [] The values to be inserted into the string
	 * @return resource|bool Returns a MySQL result resource (for SELECT queries), true (for UPDATE queries) or false if the query failed
	 */
	public function query($identifier, $db_string, $db_values = [])
	{
		global $db_cache, $db_count, $db_show_debug, $time_start;
		global $db_unbuffered, $db_callback, $modSettings, $smcFunc;

		// Comments that are allowed in a query are preg_removed.
		static $allowed_comments_from = [
			'~\s+~s',
			'~/\*!40001 SQL_NO_CACHE \*/~',
			'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
			'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
		];
		static $allowed_comments_to = [
			' ',
			'',
			'',
			'',
		];


		// Get a connection if we are shutting down, sometimes the link is closed before sessions are written
		if (!$this->connection_active())
		{
			try
			{
				$this->connect();
				$this->select_db();
			}
			catch (\Exception $e)
			{
				// We're not connected, guess we're going nowhere.
				$this->error_backtrace('No longer connected to database.', $smcFunc['db_error'](), true, __FILE__, __LINE__);
			}
		}

		// One more query....
		$db_count = !isset($db_count) ? 1 : $db_count + 1;

		if (empty($modSettings['disableQueryCheck']) && strpos($db_string, '\'') !== false && empty($db_values['security_override']))
		{
			$this->error_backtrace('Hacking attempt...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);
		}

		// Use "ORDER BY null" to prevent MySQL doing filesorts for Group By clauses without an Order By.
		if (strpos($db_string, 'GROUP BY') !== false && strpos($db_string, 'ORDER BY') === false && preg_match('~^\s+SELECT~i', $db_string))
		{
			// Add before LIMIT
			if ($pos = strpos($db_string, 'LIMIT '))
			{
				$db_string = substr($db_string, 0, $pos) . "\t\t\tORDER BY null\n" . substr($db_string, $pos, strlen($db_string));
			}
			else
			{
				// Append it.
				$db_string .= "\n\t\t\tORDER BY null";
			}
		}

		if (empty($db_values['security_override']) && (!empty($db_values) || strpos($db_string, '{db_prefix}') !== false))
		{
			$db_string = $this->quote($db_string, $db_values);
		}

		// Debugging.
		if (isset($db_show_debug) && $db_show_debug === true)
		{
			// Get the file and line number this function was called.
			list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

			// Initialize $db_cache if not already initialized.
			if (!isset($db_cache))
			{
				$db_cache = [];
			}

			if (!empty($_SESSION['debug_redirect']))
			{
				$db_cache = array_merge($_SESSION['debug_redirect'], $db_cache);
				$db_count = count($db_cache) + 1;
				$_SESSION['debug_redirect'] = [];
			}

			// Don't overload it.
			$st = microtime(true);
			$db_cache[$db_count]['q'] = $db_count < 50 ? $db_string : '...';
			$db_cache[$db_count]['f'] = $file;
			$db_cache[$db_count]['l'] = $line;
			$db_cache[$db_count]['s'] = $st - $time_start;
		}

		// First, we clean strings out of the query, reduce whitespace, lowercase, and trim - so we can check it over.
		if (empty($modSettings['disableQueryCheck']))
		{
			$clean = '';
			$old_pos = 0;
			$pos = -1;
			while (true)
			{
				$pos = strpos($db_string, '\'', $pos + 1);
				if ($pos === false)
				{
					break;
				}
				$clean .= substr($db_string, $old_pos, $pos - $old_pos);

				while (true)
				{
					$pos1 = strpos($db_string, '\'', $pos + 1);
					$pos2 = strpos($db_string, '\\', $pos + 1);
					if ($pos1 === false)
					{
						break;
					}
					elseif ($pos2 === false || $pos2 > $pos1)
					{
						$pos = $pos1;
						break;
					}

					$pos = $pos2 + 1;
				}
				$clean .= ' %s ';

				$old_pos = $pos + 1;
			}
			$clean .= substr($db_string, $old_pos);
			$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $clean)));

			// Comments?  We don't use comments in our queries, we leave 'em outside!
			if (strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, ';') !== false)
			{
				$fail = true;
			}
			// Trying to change passwords, slow us down, or something?
			elseif (strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0)
			{
				$fail = true;
			}
			elseif (strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
			{
				$fail = true;
			}

			if (!empty($fail) && function_exists('log_error'))
			{
				$this->error_backtrace('Hacking attempt...', 'Hacking attempt...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
			}
		}

		if (empty($db_unbuffered))
		{
			$ret = @mysqli_query($this->connection, $db_string);
		}
		else
		{
			$ret = @mysqli_query($this->connection, $db_string, MYSQLI_USE_RESULT);
		}

		if ($ret === false && empty($db_values['db_error_skip']))
		{
			$ret = $this->error($db_string, $this->connection);
		}

		// Debugging.
		if (isset($db_show_debug) && $db_show_debug === true)
		{
			$db_cache[$db_count]['t'] = microtime(true) - $st;
		}

		return $ret;
	}

	/**
	 * Eescape and quote a string, e.g. in preparation for execution.
	 *
	 * @param string $db_string The database string
	 * @param array $db_values An array of values to be injected into the string
	 * @return string The string with the values inserted
	 */
	public function quote($db_string, $db_values)
	{
		// Do the quoting and escaping
		return preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', function($matches) use ($db_values)
		{
			global $user_info, $db_prefix;

			if (!is_object($this->connection))
			{
				display_db_error();
			}

			if ($matches[1] === 'db_prefix')
			{
				return $db_prefix;
			}

			if (strpos($matches[1], 'query_') !== false && !isset($matches[2]))
			{
				return isset($user_info[$matches[1]]) ? $user_info[$matches[1]] : '0=1';
			}

			if ($matches[1] === 'empty')
			{
				return '\'\'';
			}

			if (!isset($matches[2]))
			{
				$this->error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);
			}

			if ($matches[1] === 'literal')
			{
				return '\'' . mysqli_real_escape_string($this->connection, $matches[2]) . '\'';
			}

			if (!isset($db_values[$matches[2]]))
			{
				$this->error_backtrace('The database value you\'re trying to insert does not exist: ' . StringLibrary::escape($matches[2]), '', E_USER_ERROR, __FILE__, __LINE__);
			}

			$replacement = $db_values[$matches[2]];

			switch ($matches[1])
			{
				case 'int':
					if (!is_numeric($replacement) || (string) $replacement !== (string) (int) $replacement)
					{
						$this->error_backtrace('Wrong value type sent to the database. Integer expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
					}
					return (string) (int) $replacement;
				break;

				case 'string':
				case 'text':
					return sprintf('\'%1$s\'', mysqli_real_escape_string($this->connection, $replacement));
				break;

				case 'array_int':
					if (is_array($replacement))
					{
						if (empty($replacement))
						{
							$this->error_backtrace('Database error, given array of integer values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
						}

						foreach ($replacement as $key => $value)
						{
							if (!is_numeric($value) || (string) $value !== (string) (int) $value)
							{
								$this->error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
							}

							$replacement[$key] = (string) (int) $value;
						}

						return implode(', ', $replacement);
					}
				break;

				case 'array_string':
					if (is_array($replacement))
					{
						if (empty($replacement))
						{
							$this->error_backtrace('Database error, given array of string values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
						}

						foreach ($replacement as $key => $value)
						{
							$replacement[$key] = sprintf('\'%1$s\'', mysqli_real_escape_string($this->connection, $value));
						}

						return implode(', ', $replacement);
					}
				break;

				case 'date':
					if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d)$~', $replacement, $date_matches) === 1)
					{
						return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]);
					}
				break;

				case 'time':
					if (preg_match('~^([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $time_matches) === 1)
					{
						return sprintf('\'%02d:%02d:%02d\'', $time_matches[1], $time_matches[2], $time_matches[3]);
					}
				break;

				case 'datetime':
					if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d) ([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $datetime_matches) === 1)
					{
						return 'str_to_date('.
							sprintf('\'%04d-%02d-%02d %02d:%02d:%02d\'', $datetime_matches[1], $datetime_matches[2], $datetime_matches[3], $datetime_matches[4], $datetime_matches[5], $datetime_matches[6]).
							',\'%Y-%m-%d %h:%i:%s\')';
					}
				break;

				case 'float':
					if (is_numeric($replacement))
					{
						return (string) (float) $replacement;
					}
				break;

				case 'identifier':
					// Backticks inside identifiers are supported as of MySQL 4.1. We don't need them for StoryBB.
					return '`' . strtr($replacement, ['`' => '', '.' => '`.`']) . '`';
				break;

				case 'raw':
					return $replacement;
				break;

				case 'inet':
					if ($replacement == 'null' || $replacement == '')
					{
						return 'null';
					}
					elseif (IP::is_valid($replacement))
					{
						// We don't use the native support of mysql > 5.6.2
						return sprintf('unhex(\'%1$s\')', str_pad(bin2hex(inet_pton($replacement)), 32, "0", STR_PAD_LEFT));
					}
					break;

				case 'array_inet':
					if (is_array($replacement))
					{
						if (empty($replacement))
						{
							$this->error_backtrace('Database error, given array of IPv4 or IPv6 values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
						}

						foreach ($replacement as $key => $value)
						{
							if ($replacement == 'null' || $replacement == '')
								$replacement[$key] = 'null';
							if (!IP::is_valid($value))
							{
								$this->error_backtrace('Wrong value type sent to the database. IPv4 or IPv6 expected.(' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
							}
							$replacement[$key] = sprintf('unhex(\'%1$s\')', str_pad(bin2hex(inet_pton($value)), 32, "0", STR_PAD_LEFT));
						}

						return implode(', ', $replacement);
					}
				break;
			}

			$types = [
				'array_int' => 'Array of integers expected.',
				'array_string' => 'Array of strings expected.',
				'date' => 'Date expected.',
				'time' => 'Time expected.',
				'datetime' => 'Datetime expected.',
				'float' => 'Floating point number expected.',
				'inet' => 'IPv4 or IPv6 expected.',
				'array_inet' => 'Array of IPv4 or IPv6 expected.',
			];
			if (isset($types[$matches[1]]))
			{
				$this->error_backtrace('Wrong value type sent to the database. ' . $types[$matches[1]] . ' (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			}
			else
			{
				$this->error_backtrace('Undefined type used in the database query. (' . $matches[1] . ':' . $matches[2] . ')', '', false, __FILE__, __LINE__);
			}
		}, $db_string);
	}
}
