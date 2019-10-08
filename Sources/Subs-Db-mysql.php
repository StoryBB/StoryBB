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
	if (!isset($smcFunc['db_fetch_all']))
	{
		$smcFunc += [
			'db_data_seek'              => 'mysqli_data_seek',
			'db_escape_string'          => 'addslashes',
			'db_unescape_string'        => 'stripslashes',
			'db_error'                  => 'mysqli_error',
			'db_errno'                  => 'mysqli_errno',
			'db_escape_wildcard_string' => 'sbb_db_escape_wildcard_string',
			'db_is_resource'            => 'sbb_is_resource',
			'db_fetch_all'              => 'sbb_db_fetch_all',
			'db_error_insert'			=> 'sbb_db_error_insert',
		];
	}
}

/**
 * Extend the database functionality. It calls the respective file's init
 * to add the implementations in that file to $smcFunc array.
 *
 * @param string $type Indicates which additional file to load. ('search', 'packages')
 */
function db_extend($type = 'packages')
{
	global $sourcedir;

	// we force the MySQL files as nothing syntactically changes with MySQLi
	require_once($sourcedir . '/Db' . strtoupper($type[0]) . substr($type, 1) . '-mysql.php');
	$initFunc = 'db_' . $type . '_init';
	$initFunc();
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
