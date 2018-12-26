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

	public static function exists()
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
		global $smcFunc;

		$columns = [];
		foreach ($this->columns as $column)
		{
			$columns[] = $column->create_data();
		}
		$indexes = [];
		foreach ($this->indexes as $index)
		{
			$indexes[] = $index->create_data();
		}

		$parameters = [];
		if (!empty($opts['prefer_engine']))
		{
			$parameters['engine'] = reset($opts['prefer_engine']);
		}

		return $smcFunc['db_create_table']($this->table_name, $columns, $indexes, $parameters);
	}
}
