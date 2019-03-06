<?php

/**
 * This file contains database functionality specifically designed for packages (mods) to utilize.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

use StoryBB\Schema\Schema;
use StoryBB\Schema\Database;
use StoryBB\Schema\Table;
use StoryBB\Schema\Column;
use StoryBB\Schema\Index;
use StoryBB\Schema\InvalidColumnTypeException;

/**
 * Add the file functions to the $smcFunc array.
 */
function db_packages_init()
{
	global $smcFunc, $reservedTables, $db_package_log, $db_prefix;

	if (!isset($smcFunc['db_create_table']) || $smcFunc['db_create_table'] != 'sbb_db_create_table')
	{
		$smcFunc += array(
			'db_add_column' => 'sbb_db_add_column',
			'db_add_index' => 'sbb_db_add_index',
			'db_calculate_type' => 'sbb_db_calculate_type',
			'db_change_column' => 'sbb_db_change_column',
			'db_create_table' => 'sbb_db_create_table',
			'db_drop_table' => 'sbb_db_drop_table',
			'db_table_structure' => 'sbb_db_table_structure',
			'db_compare_column' => 'sbb_db_compare_column',
			'db_compare_indexes' => 'sbb_db_compare_indexes',
			'db_list_columns' => 'sbb_db_list_columns',
			'db_list_indexes' => 'sbb_db_list_indexes',
			'db_remove_column' => 'sbb_db_remove_column',
			'db_remove_index' => 'sbb_db_remove_index',
		);
		$db_package_log = array();
	}

	// We setup an array of StoryBB tables we can't do auto-remove on - in case a mod writer cocks it up!
	foreach (Schema::get_tables() as $table)
	{
		$table_name = $table->get_table_name();
		$reservedTables[] = strtolower($db_prefix . $table_name);
	}

	// We in turn may need the extra stuff.
	db_extend('extra');
}

/**
 * This function can be used to create a table without worrying about schema
 *  compatibilities across supported database systems.
 *  - If the table exists will, by default, do nothing.
 *  - Builds table with columns as passed to it - at least one column must be sent.
 *  The columns array should have one sub-array for each column - these sub arrays contain:
 *  	'name' = Column name
 *  	'type' = Type of column - values from (smallint, mediumint, int, text, varchar, char, tinytext, mediumtext, largetext)
 *  	'size' => Size of column (If applicable) - for example 255 for a large varchar, 10 for an int etc.
 *  		If not set StoryBB will pick a size.
 *  	- 'default' = Default value - do not set if no default required.
 *  	- 'null' => Can it be null (true or false) - if not set default will be false.
 *  	- 'auto' => Set to true to make it an auto incrementing column. Set to a numerical value to set from what
 *  		 it should begin counting.
 *  - Adds indexes as specified within indexes parameter. Each index should be a member of $indexes. Values are:
 *  	- 'name' => Index name (If left empty StoryBB will generate).
 *  	- 'type' => Type of index. Choose from 'primary', 'unique' or 'index'. If not set will default to 'index'.
 *  	- 'columns' => Array containing columns that form part of key - in the order the index is to be created.
 *  - parameters: (None yet)
 *  - if_exists values:
 *  	- 'ignore' will do nothing if the table exists. (And will return true)
 *  	- 'overwrite' will drop any existing table of the same name.
 *  	- 'error' will return false if the table already exists.
 *  	- 'update' will update the table if the table already exists (no change of ai field and only colums with the same name keep the data)
 *
 * @param string $table_name The name of the table to create
 * @param array $columns An array of column info in the specified format
 * @param array $indexes An array of index info in the specified format
 * @param array $parameters Extra parameters. Currently only 'engine', the desired MySQL storage engine, is used.
 * @param string $if_exists What to do if the table exists.
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
function sbb_db_create_table($table_name, $columns, $indexes = array(), $parameters = array(), $if_exists = 'ignore', $error = 'fatal')
{
	global $reservedTables, $smcFunc, $db_package_log, $db_prefix, $db_name;

	static $engines = array();

	$old_table_exists = false;

	// Strip out the table name, we might not need it in some cases
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// With or without the database name, the fullname looks like this.
	$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Log that we'll want to remove this on uninstall.
	$db_package_log[] = array('remove_table', $table_name);

	// Slightly easier on MySQL than the others...
	$tables = $smcFunc['db_list_tables']();
	if (in_array($full_table_name, $tables))
	{
		// This is a sad day... drop the table? If not, return false (error) by default.
		if ($if_exists == 'overwrite')
			$smcFunc['db_drop_table']($table_name);
		elseif ($if_exists == 'update')
		{
			$smcFunc['db_transaction']('begin');
			$db_trans = true;
			$smcFunc['db_drop_table']($table_name.'_old');
			$smcFunc['db_query']('','
				RENAME TABLE '. $table_name .' TO ' . $table_name . '_old',
				array(
					'security_override' => true,
				)
			);
			$old_table_exists = true;
		}
		else
			return $if_exists == 'ignore';
	}

	// Righty - let's do the damn thing!
	$table_query = 'CREATE TABLE ' . $table_name . "\n" . '(';
	foreach ($columns as $column)
		$table_query .= "\n\t" . sbb_db_create_query_column($column) . ',';

	// Loop through the indexes next...
	foreach ($indexes as $index)
	{
		$columns = implode(',', $index['columns']);
		$columns = str_replace(['(', ')'], '', $columns);

		// Is it the primary?
		if (isset($index['type']) && $index['type'] == 'primary')
			$table_query .= "\n\t" . 'PRIMARY KEY (' . implode(', ', $index['columns']) . '),';
		else
		{
			if (empty($index['name']))
			{
				$column_names = $index['columns'];
				foreach ($column_names as $k => $v) {
					$column_names[$k] = str_replace(['(', ')'], '', $v);
				}
				$index['name'] = implode('_', $column_names);
			}
			$table_query .= "\n\t" . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : 'KEY') . ' ' . $index['name'] . ' (' . implode(', ', $index['columns']) . '),';
		}
	}

	// No trailing commas!
	if (substr($table_query, -1) == ',')
		$table_query = substr($table_query, 0, -1);

	if (!isset($parameters['engine']))
	{
		return false;
	}

	$table_query .= ') ENGINE=' . $parameters['engine'];
	$table_query .= ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

	// Create the table!
	$smcFunc['db_query']('', $table_query,
		array(
			'security_override' => true,
		)
	);

	// Fill the old data
	if ($old_table_exists)
	{	
		$same_col = array();

		$request = $smcFunc['db_query']('','
			SELECT count(*), column_name
			FROM information_schema.columns
			WHERE table_name in ({string:table1},{string:table2}) AND table_schema = {string:schema}
			GROUP BY column_name
			HAVING count(*) > 1',
			array (
				'table1' => $table_name,
				'table2' => $table_name.'_old',
				'schema' => $db_name,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$same_col[] = $row['column_name'];
		}

		$smcFunc['db_query']('','
			INSERT INTO ' . $table_name .'('
			. implode($same_col, ',') .
			')
			SELECT '. implode($same_col, ',') . '
			FROM ' . $table_name . '_old',
			array()
		);

		$smcFunc['db_drop_table']($table_name . '_old');
	}

	return true;
}

/**
 * Drop a table.
 *
 * @param string $table_name The name of the table to drop
 * @param array $parameters Not used at the moment
 * @param string $error
 * @param bool $bypass_checks
 * @return boolean Whether or not the operation was successful
 */
function sbb_db_drop_table($table_name, $parameters = array(), $error = 'fatal', $bypass_checks = false)
{
	global $reservedTables, $smcFunc, $db_prefix;

	// After stripping away the database name, this is what's left.
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get some aliases.
	$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// God no - dropping one of these = bad.
	if (in_array(strtolower($table_name), $reservedTables))
		return false;

	// Does it exist?
	if ($bypass_checks || in_array($full_table_name, $smcFunc['db_list_tables']()))
	{
		$query = 'DROP TABLE ' . $table_name;
		$smcFunc['db_query']('',
			$query,
			array(
				'security_override' => true,
			)
		);

		return true;
	}

	// Otherwise do 'nout.
	return false;
}

/**
 * This function adds a column.
 *
 * @param string $table_name The name of the table to add the column to
 * @param array $column_info An array of column info ({@see sbb_db_create_table})
 * @param array $parameters Not used?
 * @param string $if_exists What to do if the column exists. If 'update', column is updated.
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
function sbb_db_add_column($table_name, $column_info, $parameters = array(), $if_exists = 'update', $error = 'fatal')
{
	global $smcFunc, $db_package_log, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Log that we will want to uninstall this!
	$db_package_log[] = array('remove_column', $table_name, $column_info['name']);

	// Does it exist - if so don't add it again!
	$columns = $smcFunc['db_list_columns']($table_name, false);
	foreach ($columns as $column)
		if ($column == $column_info['name'])
		{
			// If we're going to overwrite then use change column.
			if ($if_exists == 'update')
				return $smcFunc['db_change_column']($table_name, $column_info['name'], $column_info);
			else
				return false;
		}

	// Get the specifics...
	$column_info['size'] = isset($column_info['size']) && is_numeric($column_info['size']) ? $column_info['size'] : null;

	// Now add the thing!
	$query = '
		ALTER TABLE ' . $table_name . '
		ADD ' . sbb_db_create_query_column($column_info) . (empty($column_info['auto']) ? '' : ' primary key');
	$smcFunc['db_query']('', $query,
		array(
			'security_override' => true,
		)
	);

	return true;
}

/**
 * Removes a column.
 *
 * @param string $table_name The name of the table to drop the column from
 * @param string $column_name The name of the column to drop
 * @param array $parameters Not used?
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
function sbb_db_remove_column($table_name, $column_name, $parameters = array(), $error = 'fatal')
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Does it exist?
	$columns = $smcFunc['db_list_columns']($table_name, true);
	foreach ($columns as $column)
		if ($column['name'] == $column_name)
		{
			$smcFunc['db_query']('', '
				ALTER TABLE ' . $table_name . '
				DROP COLUMN ' . $column_name,
				array(
					'security_override' => true,
				)
			);

			return true;
		}

	// If here we didn't have to work - joy!
	return false;
}

/**
 * Change a column.
 *
 * @param string $table_name The name of the table this column is in
 * @param string $old_column The name of the column we want to change
 * @param array $column_info An array of info about the "new" column definition (see {@link sbb_db_create_table()})
 * @return bool
 */
function sbb_db_change_column($table_name, $old_column, $column_info)
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Check it does exist!
	$columns = $smcFunc['db_list_columns']($table_name, true);
	$old_info = null;
	foreach ($columns as $column)
		if ($column['name'] == $old_column)
			$old_info = $column;

	// Nothing?
	if ($old_info == null)
		return false;

	// Get the right bits.
	if (!isset($column_info['name']))
		$column_info['name'] = $old_column;
	if (!isset($column_info['default']))
		$column_info['default'] = $old_info['default'];
	if (!isset($column_info['null']))
		$column_info['null'] = $old_info['null'];
	if (!isset($column_info['auto']))
		$column_info['auto'] = $old_info['auto'];
	if (!isset($column_info['type']))
		$column_info['type'] = $old_info['type'];
	if (!isset($column_info['size']) || !is_numeric($column_info['size']))
		$column_info['size'] = $old_info['size'];
	if (!isset($column_info['unsigned']) || !in_array($column_info['type'], array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')))
		$column_info['unsigned'] = '';

	list ($type, $size) = $smcFunc['db_calculate_type']($column_info['type'], $column_info['size']);

	// Allow for unsigned integers (mysql only)
	$unsigned = in_array($type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')) && !empty($column_info['unsigned']) ? 'unsigned ' : '';

	if ($size !== null)
		$type = $type . '(' . $size . ')';

	$smcFunc['db_query']('', '
		ALTER TABLE ' . $table_name . '
		CHANGE COLUMN `' . $old_column . '` `' . $column_info['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column_info['null']) ? 'NOT NULL' : '') . ' ' .
			(!isset($column_info['default']) ? '' : 'default \'' . $smcFunc['db_escape_string']($column_info['default']) . '\'') . ' ' .
			(empty($column_info['auto']) ? '' : 'auto_increment') . ' ',
		array(
			'security_override' => true,
		)
	);
}

/**
 * Add an index.
 *
 * @param string $table_name The name of the table to add the index to
 * @param array $index_info An array of index info (see {@link sbb_db_create_table()})
 * @param array $parameters Not used?
 * @param string $if_exists What to do if the index exists. If 'update', the definition will be updated.
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
function sbb_db_add_index($table_name, $index_info, $parameters = array(), $if_exists = 'update', $error = 'fatal')
{
	global $smcFunc, $db_package_log, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// No columns = no index.
	if (empty($index_info['columns']))
		return false;
	$columns = implode(',', $index_info['columns']);

	// No name - make it up!
	if (empty($index_info['name']))
	{
		// No need for primary.
		if (isset($index_info['type']) && $index_info['type'] == 'primary')
			$index_info['name'] = '';
		else
			$index_info['name'] = implode('_', $index_info['columns']);
	}

	// Log that we are going to want to remove this!
	$db_package_log[] = array('remove_index', $table_name, $index_info['name']);

	// Let's get all our indexes.
	$indexes = $smcFunc['db_list_indexes']($table_name, true);
	// Do we already have it?
	foreach ($indexes as $index)
	{
		if ($index['name'] == $index_info['name'] || ($index['type'] == 'primary' && isset($index_info['type']) && $index_info['type'] == 'primary'))
		{
			// If we want to overwrite simply remove the current one then continue.
			if ($if_exists != 'update' || $index['type'] == 'primary')
				return false;
			else
				$smcFunc['db_remove_index']($table_name, $index_info['name']);
		}
	}

	// If we're here we know we don't have the index - so just add it.
	if (!empty($index_info['type']) && $index_info['type'] == 'primary')
	{
		$smcFunc['db_query']('', '
			ALTER TABLE ' . $table_name . '
			ADD PRIMARY KEY (' . $columns . ')',
			array(
				'security_override' => true,
			)
		);
	}
	else
	{
		$smcFunc['db_query']('', '
			ALTER TABLE ' . $table_name . '
			ADD ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : 'INDEX') . ' ' . $index_info['name'] . ' (' . $columns . ')',
			array(
				'security_override' => true,
			)
		);
	}
}

/**
 * Remove an index.
 *
 * @param string $table_name The name of the table to remove the index from
 * @param string $index_name The name of the index to remove
 * @param array $parameters Not used?
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
function sbb_db_remove_index($table_name, $index_name, $parameters = array(), $error = 'fatal')
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Better exist!
	$indexes = $smcFunc['db_list_indexes']($table_name, true);

	foreach ($indexes as $index)
	{
		// If the name is primary we want the primary key!
		if ($index['type'] == 'primary' && $index_name == 'primary')
		{
			// Dropping primary key?
			$smcFunc['db_query']('', '
				ALTER TABLE ' . $table_name . '
				DROP PRIMARY KEY',
				array(
					'security_override' => true,
				)
			);

			return true;
		}
		if ($index['name'] == $index_name)
		{
			// Drop the bugger...
			$smcFunc['db_query']('', '
				ALTER TABLE ' . $table_name . '
				DROP INDEX ' . $index_name,
				array(
					'security_override' => true,
				)
			);

			return true;
		}
	}

	// Not to be found ;(
	return false;
}

/**
 * Get the schema formatted name for a type.
 *
 * @param string $type_name The data type (int, varchar, smallint, etc.)
 * @param int $type_size The size (8, 255, etc.)
 * @param boolean $reverse
 * @return array An array containing the appropriate type and size for this DB type
 */
function sbb_db_calculate_type($type_name, $type_size = null, $reverse = false)
{
	// MySQL is actually the generic baseline.

	$type_name = strtolower($type_name);
	// Generic => Specific.
	if (!$reverse)
	{
		$types = array(
			'inet' => 'varbinary',
		);
	}
	else
	{
		$types = array(
			'varbinary' => 'inet',
		);
	}

	// Got it? Change it!
	if (isset($types[$type_name]))
	{
		if ($type_name == 'inet' && !$reverse)
		{
			$type_size = 16;
			$type_name = 'varbinary';
		}
		elseif ($type_name == 'varbinary' && $reverse && $type_size == 16)
		{
			$type_name = 'inet';
			$type_size = null;
		}
		elseif ($type_name == 'varbinary')
			$type_name = 'varbinary';
		else
			$type_name = $types[$type_name];
	}

	return array($type_name, $type_size);
}

/**
 * Get table structure.
 *
 * @param string $table_name The name of the table
 * @return Table A table object representing the table as in the database
 */
function sbb_db_table_structure(string $table_name): Table
{
	global $smcFunc, $db_prefix;
	/*
			Table::make('admin_info_files',
				[
					'id_file' => Column::tinyint()->auto_increment(),
					'filename' => Column::varchar(255),
					'path' => Column::varchar(255),
					'parameters' => Column::varchar(255),
					'data' => Column::text(),
					'filetype' => Column::varchar(255),
				],
				[
					Index::primary(['id_file']),
					Index::key(['filename' => 30])
				]
			),
	*/

	$unprefixed_name = str_replace('{db_prefix}', '', $table_name);
	$real_table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	$columns = [];
	$indexes = [];

	// First, get the columns.
	$result = $smcFunc['db_query']('', '
		SHOW FIELDS
		FROM {raw:table_name}',
		[
			'table_name' => substr($real_table_name, 0, 1) == '`' ? $real_table_name : '`' . $real_table_name . '`',
		]
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (preg_match('~(.+?)\s*\((\d+)\)(?:(?:\s*)?(unsigned))?~i', $row['Type'], $matches) === 1)
		{
			$type = $matches[1];
			$size = (int) $matches[2];

			switch ($type)
			{
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'bigint':
					$columns[$row['Field']] = Column::$type()->size($size);

					$signed = true;
					if (!empty($matches[3]) && $matches[3] == 'unsigned')
					{
						$signed = false;
					}
					if ($signed)
					{
						$columns[$row['Field']]->signed();
					}

					if (strpos($row['Extra'], 'auto_increment') !== false)
					{
						$columns[$row['Field']]->auto_increment();
					}

					if ($row['Null'] == 'YES')
					{
						$columns[$row['Field']]->nullable();
					}
					if (isset($row['Default']))
					{
						$columns[$row['Field']]->default((int) $row['Default']);
					}
					break;

				case 'char':
				case 'varchar':
				case 'varbinary':
					$columns[$row['Field']] = Column::$type($size);

					if ($row['Null'] == 'YES')
					{
						$columns[$row['Field']]->nullable();
					}
					if (isset($row['Default']))
					{
						$columns[$row['Field']]->default($row['Default']);
					}
					break;

				default:
					throw new InvalidColumnTypeException('Unknown column type ' . $type);
			}
		}
		else
		{
			switch ($row['Type'])
			{
				case 'float':
					$columns[$row['Field']] = Column::float();
					if ($row['Null'] == 'YES')
					{
						$columns[$row['Field']]->nullable();
					}
					if (isset($row['Default']))
					{
						$columns[$row['Field']]->default($row['Default']);
					}
					break;

				case 'text':
				case 'mediumtext':
					$type = $row['Type'];
					$columns[$row['Field']] = Column::$type();

					if ($row['Null'] == 'YES')
					{
						$columns[$row['Field']]->nullable();
					}
					break;

				case 'date':
					$columns[$row['Field']] = Column::date();
					if ($row['Null'] == 'YES')
					{
						$columns[$row['Field']]->nullable();
					}
					if (isset($row['Default']))
					{
						$columns[$row['Field']]->default($row['Default']);
					}
					break;

				default:
					throw new InvalidColumnTypeException('Unknown column type ' . $row['Type']);
			}
		}
	}

	$smcFunc['db_free_result']($result);

	// Now get the indexes.
	$result = $smcFunc['db_query']('', '
		SHOW INDEXES
		FROM {raw:table_name}',
		[
			'table_name' => substr($real_table_name, 0, 1) == '`' ? $real_table_name : '`' . $real_table_name . '`',
		]
	);
	$indexlist = [];
	$indexfunc = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (empty($row['Sub_part']))
		{
			$indexlist[$row['Key_name']][] = $row['Column_name'];
		}
		else
		{
			$indexlist[$row['Key_name']][$row['Column_name']] = $row['Sub_part'];
		}

		if (!isset($indextype[$row['Key_name']]))
		{
			if ($row['Non_unique'])
			{
				$indexfunc[$row['Key_name']] = 'key';
			}
			else
			{
				$indexfunc[$row['Key_name']] = $row['Key_name'] == 'PRIMARY' ? 'primary' : 'unique';
			}
		}
	}
	$smcFunc['db_free_result']($result);

	foreach ($indexfunc as $index => $func)
	{
		$indexes[] = Index::$func($indexlist[$index]);
	}

	return Table::make($unprefixed_name, $columns, $indexes);
}

function sbb_db_compatible_types()
{
	$compatible_types = [
		'tinyint' => ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float'],
		'smallint' => ['smallint', 'mediumint', 'int', 'bigint', 'float'],
		'mediumint' => ['mediumint', 'int', 'bigint', 'float'],
		'int' => ['int', 'bigint', 'float'],
		'bigint' => ['bigint', 'float'],
		'float' => ['float'],
		'char' => ['char', 'varchar', 'text', 'mediumtext'],
		'varchar' => ['varchar', 'text', 'mediumtext'],
		'text' => ['text', 'mediumtext'],
		'mediumtext' => ['mediumtext'],
		'varbinary' => ['varbinary'],
		'date' => ['date'],
	];
	$superset_types = [];
	foreach ($compatible_types as $type => $upgradeable)
	{
	    foreach ($upgradeable as $upgrade)
	    {
		    $superset_types[$upgrade][$type] = true;
	    }
	}

	return [$compatible_types, $superset_types];
}

/**
 * Compares two column objects.
 *
 * @return mixed false if no changes required, Column otherwise reflecting new column
 */
function sbb_db_compare_column(Column $source, Column $dest)
{
	static $compatible_types, $superset_types;

	// These types are legal upgrades.
	if ($compatible_types === null)
	{
		list($compatibilities, $superset_types) = sbb_db_compatible_types();
	}

	$source_data = $source->create_data();
	$dest_data = $dest->create_data();

	// Is the new column bigger than the old one? What about if the old column is a supertype of the new one?
	$legal_upgrade = in_array($dest_data['type'], $compatible_types[$source_data['type']]);
	$is_superset = isset($superset_types[$source_data['type']][$dest_data['type']]);
	$type_change = $source_data['type'] != $dest_data['type'];

	// If it's not equal, a legal upgrade or a valid superset, it's not a viable change.
	if ($type_change && !$legal_upgrade && !$is_superset)
	{
		throw new InvalidColumnTypeException('Cannot convert ' . $source_data['type'] . ' to ' . $dest_data['type']);
	}

	// Is there is a size differential?
	$size_differential = 0;
	if (isset($source_data['size'], $dest_data['size']))
	{
		$size_differential = $source_data['size'] <=> $dest_data['size'];
	}

	// Is there a default value?
	$default = null;
	$default_change = false;
	if (isset($source_data['default'], $dest_data['default']))
	{

	}

	// Nullability change? Nullability is pre-known.
	$nullable = null;
	if ($source_data['null'] != $dest_data['null'])
	{
		$nullable = $dest_data['null'];
	}

	// Sign change?
	$signed = null;
	if (empty($source_data['unsigned']) != empty($dest_data['unsigned']))
	{
		$signed = empty($dest_data['unsigned']);
	}
	$signed_change = $signed !== null;

	// Are the column types the same?
	switch ($source_data['type'])
	{
		case 'tinyint':
		case 'smallint':
		case 'mediumint':
		case 'int':
		case 'bigint':
			if ($signed_change || $size_differential || isset($nullable) || isset($default))
			{
				
			}
			break;

		case 'float':
			break;

		case 'char':
			break;

		case 'varchar':
			break;

		case 'text':
			if ($type_change || isset($nullable))
			{
				// So this is currently a text column, it can either change its type and/or its nullability.
				$column = $type_change ? Column::mediumtext() : Column::text();
				if ($nullable)
				{
					$column->nullable();
				}
				return $column;
			}
			break;

		case 'mediumtext':
			// If the current column is mediumtext, the only possible change we can have is to nullability.
			if (isset($nullable))
			{
				// Build a new column. Doesn't matter what the destination.
				$column = Column::mediumtext();
				if ($nullable)
				{
					$column->nullable();
				}
				return $column;
			}
			break;

		case 'varbinary':
			if (isset($default) || isset($nullable))
			{
				// A change of default value or a change in nullability will apply a change.
				return $dest;
			}
			break;

		case 'date':
			if (isset($default) || isset($nullable))
			{
				// A change of default value or a change in nullability will apply a change.
				return $dest;
			}
			break;
	}

	return false;
}

function sbb_db_compare_indexes(array $source_indexes, array $dest_indexes): array
{

}

/**
 * Return column information for a table.
 *
 * @param string $table_name The name of the table to get column info for
 * @param bool $detail Whether or not to return detailed info. If true, returns the column info. If false, just returns the column names.
 * @param array $parameters Not used?
 * @return array An array of column names or detailed column info, depending on $detail
 */
function sbb_db_list_columns($table_name, $detail = false, $parameters = array())
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	$result = $smcFunc['db_query']('', '
		SHOW FIELDS
		FROM {raw:table_name}',
		array(
			'table_name' => substr($table_name, 0, 1) == '`' ? $table_name : '`' . $table_name . '`',
		)
	);
	$columns = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!$detail)
		{
			$columns[] = $row['Field'];
		}
		else
		{
			// Is there an auto_increment?
			$auto = strpos($row['Extra'], 'auto_increment') !== false ? true : false;

			// Can we split out the size?
			if (preg_match('~(.+?)\s*\((\d+)\)(?:(?:\s*)?(unsigned))?~i', $row['Type'], $matches) === 1)
			{
				$type = $matches[1];
				$size = $matches[2];
				if (!empty($matches[3]) && $matches[3] == 'unsigned')
					$unsigned = true;
			}
			else
			{
				$type = $row['Type'];
				$size = null;
			}

			$columns[$row['Field']] = array(
				'name' => $row['Field'],
				'null' => $row['Null'] != 'YES' ? false : true,
				'default' => isset($row['Default']) ? $row['Default'] : null,
				'type' => $type,
				'size' => $size,
				'auto' => $auto,
			);

			if (isset($unsigned))
			{
				$columns[$row['Field']]['unsigned'] = $unsigned;
				unset($unsigned);
			}
		}
	}
	$smcFunc['db_free_result']($result);

	return $columns;
}

/**
 * Get index information.
 *
 * @param string $table_name The name of the table to get indexes for
 * @param bool $detail Whether or not to return detailed info.
 * @param array $parameters Not used?
 * @return array An array of index names or a detailed array of index info, depending on $detail
 */
function sbb_db_list_indexes($table_name, $detail = false, $parameters = array())
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	$result = $smcFunc['db_query']('', '
		SHOW KEYS
		FROM {raw:table_name}',
		array(
			'table_name' => substr($table_name, 0, 1) == '`' ? $table_name : '`' . $table_name . '`',
		)
	);
	$indexes = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!$detail)
			$indexes[] = $row['Key_name'];
		else
		{
			// What is the type?
			if ($row['Key_name'] == 'PRIMARY')
				$type = 'primary';
			elseif (empty($row['Non_unique']))
				$type = 'unique';
			elseif (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT')
				$type = 'fulltext';
			else
				$type = 'index';

			// This is the first column we've seen?
			if (empty($indexes[$row['Key_name']]))
			{
				$indexes[$row['Key_name']] = array(
					'name' => $row['Key_name'],
					'type' => $type,
					'columns' => array(),
				);
			}

			// Is it a partial index?
			if (!empty($row['Sub_part']))
				$indexes[$row['Key_name']]['columns'][] = $row['Column_name'] . '(' . $row['Sub_part'] . ')';
			else
				$indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
		}
	}
	$smcFunc['db_free_result']($result);

	return $indexes;
}

/**
 * Creates a query for a column
 *
 * @param array $column An array of column info
 * @return string The column definition
 */
function sbb_db_create_query_column($column)
{
	global $smcFunc;

	// Auto increment is easy here!
	if (!empty($column['auto']))
	{
		$default = 'auto_increment';
	}
	elseif (isset($column['default']) && $column['default'] !== null)
		$default = 'default \'' . $smcFunc['db_escape_string']($column['default']) . '\'';
	else
		$default = '';

	// Sort out the size... and stuff...
	$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;
	list ($type, $size) = $smcFunc['db_calculate_type']($column['type'], $column['size']);

	// Allow unsigned integers (mysql only)
	$unsigned = in_array($type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')) && !empty($column['unsigned']) ? 'unsigned ' : '';

	if ($size !== null)
		$type = $type . '(' . $size . ')';

	// Now just put it together!
	return '`' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (!empty($column['null']) ? '' : 'NOT NULL') . ' ' . $default;
}
