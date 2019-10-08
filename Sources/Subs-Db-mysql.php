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
			'db_error'                  => 'mysqli_error',
			'db_errno'                  => 'mysqli_errno',
			'db_fetch_all'              => 'sbb_db_fetch_all',
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
