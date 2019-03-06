<?php

/**
 * This class handles tables.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Schema;

use StoryBB\Schema\Database;

/**
 * This class handles tables.
 */
class Table
{
	protected $table_name = '';
	protected $columns = [];
	protected $indexes = [];
	protected $opts = [];

	protected static $table_cache = null;

	private function __construct(string $table_name, array $columns, array $indexes = [], array $opts = [])
	{
		$this->table_name = $table_name;
		$this->columns = $columns;
		$this->indexes = $indexes;
		$this->opts = $opts;

		// Make sure all the columns requested in indexes are in the definition.

		// Make sure that if a column is defined as auto_increment, that it is also the primary key.
	}

	public static function make(string $table_name, array $columns, array $indexes = [], array $opts = [])
	{
		return new Table($table_name, $columns, $indexes, $opts);
	}

	public function get_table_name()
	{
		return $this->table_name;
	}

	public function get_columns()
	{
		return $this->columns;
	}

	public function get_indexes()
	{
		return $this->indexes;
	}

	public function exists()
	{
		global $smcFunc, $db_prefix;
		if (self::$table_cache === null)
		{
			self::$table_cache = $smcFunc['db_list_tables']();
		}

		return in_array($db_prefix . $this->table_name, self::$table_cache);
	}

	public function create()
	{
		global $smcFunc, $db_prefix;

		$columns = [];
		foreach ($this->columns as $column_name => $column)
		{
			$columns[] = $column->create_data($column_name);
		}
		$indexes = [];
		foreach ($this->indexes as $index)
		{
			$indexes[] = $index->create_data();
		}

		$parameters = [];
		if (!empty($opts['prefer_engine']))
		{
			$parameters['engine'] = $this->get_engine($opts['prefer_engine']);
		}
		else
		{
			$parameters['engine'] = $this->get_engine([]);
		}

		if (!isset($smcFunc['db_create_table']))
		{
			db_extend('Packages');
		}
		$result = $smcFunc['db_create_table']('{db_prefix}' . $this->table_name, $columns, $indexes, $parameters);
		if ($result)
		{
			self::$table_cache[] = $db_prefix . $this->table_name;
		}
		return $result;
	}

	protected function get_engine(array $possible_engines)
	{
		$engines = Database::get_engines();
		foreach ($possible_engines as $possible_engine)
		{
			if (in_array($possible_engine, $engines))
			{
				return $possible_engine;
			}
		}

		// Provide a generic safe fallback.
		return in_array('InnoDB', $engines) ? 'InnoDB' : 'MyISAM';
	}

	public function update_to(Table $dest)
	{
		global $smcFunc;

		$dest_columns = $dest->get_columns();
		$dest_indexes = $dest->get_indexes();

		$changes = [];

		foreach ($dest_columns as $column_name => $column)
		{
			if (!isset($this->columns[$column_name]))
			{
				$changes['add_columns'][$column_name] = $column;
			}
			else
			{
				$change_column = $smcFunc['db_compare_column']($this->columns[$column_name], $column);
				
			}
		}
	}
}
