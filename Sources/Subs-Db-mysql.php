<?php

/**
 * This file has all the main functions in it that relate to the database.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\IP;
use StoryBB\StringLibrary;

/**
 *  Maps the implementations in this file (sbb_db_function_name)
 *  to the $smcFunc['db_function_name'] variable.
 *
 * @param string $db_server The database server
 * @param string $db_name The name of the database
 * @param string $db_user The database username
 * @param string $db_passwd The database password
 * @param string $db_prefix The table prefix
 * @param array $db_options An array of database options
 * @return null|resource Returns null on failure if $db_options['non_fatal'] is true or a MySQL connection resource handle if the connection was successful.
 */
function sbb_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = [])
{
	global $smcFunc;

	// Map some database specific functions, only do this once.
	if (!isset($smcFunc['db_fetch_assoc']))
	{
		$smcFunc += [
			'db_fetch_assoc'            => 'mysqli_fetch_assoc',
			'db_fetch_row'              => 'mysqli_fetch_row',
			'db_insert'                 => 'sbb_db_insert',
			'db_data_seek'              => 'mysqli_data_seek',
			'db_escape_string'          => 'addslashes',
			'db_unescape_string'        => 'stripslashes',
			'db_server_info'            => 'sbb_db_get_server_info',
			'db_error'                  => 'mysqli_error',
			'db_errno'                  => 'mysqli_errno',
			'db_escape_wildcard_string' => 'sbb_db_escape_wildcard_string',
			'db_is_resource'            => 'sbb_is_resource',
			'db_fetch_all'              => 'sbb_db_fetch_all',
			'db_error_insert'			=> 'sbb_db_error_insert',
			'db_custom_order'			=> 'sbb_db_custom_order',
		];
	}
}

/**
 * Extend the database functionality. It calls the respective file's init
 * to add the implementations in that file to $smcFunc array.
 *
 * @param string $type Indicates which additional file to load. ('extra', 'packages')
 */
function db_extend($type = 'extra')
{
	global $sourcedir;

	// we force the MySQL files as nothing syntactically changes with MySQLi
	require_once($sourcedir . '/Db' . strtoupper($type[0]) . substr($type, 1) . '-mysql.php');
	$initFunc = 'db_' . $type . '_init';
	$initFunc();
}

/**
 * Wrap mysqli_get_server_info so the connection does not need to be specified
 *
 * @param object $connection The connection to use (if null, $db_connection is used)
 * @return string The server info
 */
function sbb_db_get_server_info($connection = null)
{
	global $db_connection;
	return mysqli_get_server_info($connection === null ? $db_connection : $connection);
}

/**
 * Inserts data into a table
 *
 * @param string $method The insert method - can be 'replace', 'ignore' or 'insert'
 * @param string $table The table we're inserting the data into
 * @param array $columns An array of the columns we're inserting the data into. Should contain 'column' => 'datatype' pairs
 * @param array $data The data to insert
 * @param array $keys The keys for the table
 * @param int returnmode 0 = nothing(default), 1 = last row id, 2 = all rows id as array
 * @return mixed value of the first key, behavior based on returnmode. null if no data.
 */
function sbb_db_insert($method = 'replace', $table, $columns, $data, $keys, $returnmode = 0, bool $safe_mode = false)
{
	global $smcFunc, $db_connection, $db_prefix;

	$connection = $db_connection;
	
	$return_var = null;

	// With nothing to insert, simply return.
	if (empty($data))
		return;

	// Replace the prefix holder with the actual prefix.
	$table = str_replace('{db_prefix}', $db_prefix, $table);
	
	$with_returning = false;
	
	if (!empty($keys) && (count($keys) > 0) && $returnmode > 0)
	{
		$with_returning = true;
		if ($returnmode == 2)
			$return_var = [];
	}

	// Inserting data as a single row can be done as a single array.
	if (!is_array($data[array_rand($data)]))
		$data = [$data];

	// Create the mold for a single row insert.
	$insertData = '(';
	foreach ($columns as $columnName => $type)
	{
		// Are we restricting the length?
		if (strpos($type, 'string-') !== false)
			$insertData .= sprintf('SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $columnName);
		else
			$insertData .= sprintf('{%1$s:%2$s}, ', $type, $columnName);
	}
	$insertData = substr($insertData, 0, -2) . ')';

	// Create an array consisting of only the columns.
	$indexed_columns = array_keys($columns);

	// Here's where the variables are injected to the query.
	$insertRows = [];
	foreach ($data as $dataRow)
		$insertRows[] = $smcFunc['db']->quote($insertData, array_combine($indexed_columns, $dataRow), $connection);

	// Determine the method of insertion.
	$queryTitle = $method == 'replace' ? 'REPLACE' : ($method == 'ignore' ? 'INSERT IGNORE' : 'INSERT');

	if (!$with_returning || $method != 'ingore')
	{
		// Do the insert.
		$result = $smcFunc['db']->query('', '
			' . $queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
			VALUES
				' . implode(',
				', $insertRows),
			[
				'security_override' => true,
				'db_error_skip' => $table === $db_prefix . 'log_errors',
				'safe_mode' => $safe_mode
			],
			$connection
		);
		if ($safe_mode)
		{
			return $result;
		}
	}
	else //special way for ignore method with returning
	{
		$count = count($insertRows);
		$ai = 0;
		for($i = 0; $i < $count; $i++)
		{
			$old_id = $smcFunc['db']->inserted_id();
			
			$result = $smcFunc['db']->query('', '
				' . $queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
				VALUES
					' . $insertRows[$i],
				[
					'security_override' => true,
					'db_error_skip' => $table === $db_prefix . 'log_errors',
					'safe_mode' => $safe_mode,
				],
				$connection
			);
			if ($safe_mode)
			{
				return $result;
			}
			$new_id = $smcFunc['db']->inserted_id();
			
			if ($last_id != $new_id) //the inserted value was new
			{
				$ai = $new_id;
			}
			else	// the inserted value already exists we need to find the pk
			{
				$where_string = '';
				$count2 = count($indexed_columns);
				for ($x = 0; $x < $count2; $x++)
				{
					$where_string += key($indexed_columns[$x]) . ' = '. $insertRows[$i][$x];
					if (($x + 1) < $count2)
						$where_string += ' AND ';
				}

				$request = $smcFunc['db']->query('', '
					SELECT `'. $keys[0] . '` FROM ' . $table .'
					WHERE ' . $where_string . ' LIMIT 1',
					[]
				);
				
				if ($request !== false && $smcFunc['db']->num_rows($request) == 1)
				{
					$row = $smcFunc['db_fetch_assoc']($request);
					$ai = $row[$keys[0]];
				}
			}
			
			if ($returnmode == 1)
				$return_var = $ai;
			elseif ($returnmode == 2)
				$return_var[] = $ai;
		}
	}
	

	if ($with_returning)
	{
		if ($returnmode == 1 && empty($return_var))
			$return_var = $smcFunc['db']->inserted_id() + count($insertRows) - 1;
		elseif ($returnmode == 2 && empty($return_var))
		{
			$return_var = [];
			$count = count($insertRows);
			$start = $smcFunc['db']->inserted_id();
			for ($i = 0; $i < $count; $i++)
				$return_var[] = $start + $i;
		}
		return $return_var;
	}
}

/**
 * Escape the LIKE wildcards so that they match the character and not the wildcard.
 *
 * @param string $string The string to escape
 * @param bool $translate_human_wildcards If true, turns human readable wildcards into SQL wildcards.
 * @return string The escaped string
 */
function sbb_db_escape_wildcard_string($string, $translate_human_wildcards = false)
{
	$replacements = [
		'%' => '\%',
		'_' => '\_',
		'\\' => '\\\\',
	];

	if ($translate_human_wildcards)
		$replacements += [
			'*' => '%',
		];

	return strtr($string, $replacements);
}

/**
 * Validates whether the resource is a valid mysqli instance.
 * Mysqli uses objects rather than resource. https://bugs.php.net/bug.php?id=42797
 *
 * @param mixed $result The string to test
 * @return bool True if it is, false otherwise
 */
function sbb_is_resource($result)
{
	if ($result instanceof mysqli_result)
		return true;

	return false;
}

/**
 * Fetches all rows from a result as an array 
 *
 * @param resource $request A MySQL result resource
 * @return array An array that contains all rows (records) in the result resource
 */
function sbb_db_fetch_all($request)
{
	// Return the right row.
	return mysqli_fetch_all($request);
}

/**
 * Function to save errors in database in a safe way
 *
 * @param array with keys in this order id_member, log_time, ip, url, message, session, error_type, file, line
 * @return void
 */
function sbb_db_error_insert($error_array)
{
	global  $db_prefix, $db_connection;
	static $mysql_error_data_prep;

	if (empty($mysql_error_data_prep))
			$mysql_error_data_prep = mysqli_prepare($db_connection,
				'INSERT INTO ' . $db_prefix . 'log_errors(id_member, log_time, ip, url, message, session, error_type, file, line)
				VALUES(?, ?, unhex(?), ?, ?, ?, ?, ?, ?)'
			);

	if (filter_var($error_array[2], FILTER_VALIDATE_IP) !== false)
		$error_array[2] = bin2hex(inet_pton($error_array[2]));
	else
		$error_array[2] = null;
	mysqli_stmt_bind_param($mysql_error_data_prep, 'iissssssi', 
		$error_array[0], $error_array[1], $error_array[2], $error_array[3], $error_array[4], $error_array[5], $error_array[6],
		$error_array[7], $error_array[8]);
	mysqli_stmt_execute ($mysql_error_data_prep);
}

/**
 * Function which constructs an optimize custom order string
 * as an improved alternative to find_in_set()
 *
 * @param string $field name
 * @param array $array_values Field values sequenced in array via order priority. Must cast to int.
 * @param boolean $desc default false
 * @return string case field when ... then ... end
 */
function sbb_db_custom_order($field, $array_values, $desc = false)
{
	$return = 'CASE '. $field . ' ';
	$count = count($array_values);
	$then = ($desc ? ' THEN -' : ' THEN ');

	for ($i = 0; $i < $count; $i++)
		$return .= 'WHEN ' . (int) $array_values[$i] . $then . $i . ' ';

	$return .= 'END';
	return $return;
}
