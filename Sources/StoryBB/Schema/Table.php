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
 * This class handles tables. Specifically it models a single table and describes the columns and indexes
 * it should have. A convenient byproduct of this approach is that since it applies going forwards,
 * this object can also represent a partial table (i.e. minimum requires columns) and the same
 * structure can be used for a plugin system to describe additional columns it needs to add.
 */
class Table
{
	/** @var string $table_name The internal table name, without prefix. */
	protected $table_name = '';

	/** @var array $columns An array of Column objects that are the columns for this table. */
	protected $columns = [];

	/** @var array $indexes An array of Index objects that are the indexes for this table. */
	protected $indexes = [];

	/** @var array $opts An array of options for this table's creation/setup. */
	protected $opts = [];

	/** @var array $table_cache A current list of tables we know about to avoid more looksup than necessary. */
	protected static $table_cache = null;

	/**
	 * Constructs a Table object. Not to be called publically.
	 *
	 * @param string $table_name The name of the table being investigated or manipulated.
	 * @param array $columns The columns this table object should have.
	 * @param array $indexes The indexes this table object should have.
	 * @param array $opts An array of options about this object, e.g. table engine preferences.
	 * @return Table The table instance.
	 */
	private function __construct(string $table_name, array $columns, array $indexes = [], array $opts = [])
	{
		$this->table_name = $table_name;
		$this->columns = $columns;
		$this->indexes = $indexes;
		$this->opts = $opts;

		// Make sure all the columns requested in indexes are in the definition.

		// Make sure that if a column is defined as auto_increment, that it is also the primary key.
	}

	/**
	 * Factory method.
	 * @param string $table_name The name of the table being investigated or manipulated.
	 * @param array $columns The columns this table object should have.
	 * @param array $indexes The indexes this table object should have.
	 * @param array $opts An array of options about this object, e.g. table engine preferences.
	 * @return Table The table instance.
	 */
	public static function make(string $table_name, array $columns, array $indexes = [], array $opts = [])
	{
		return new Table($table_name, $columns, $indexes, $opts);
	}

	/**
	 * Returns the name of this table.
	 *
	 * @return string The table's name.
	 */
	public function get_table_name(): string
	{
		return $this->table_name;
	}

	/**
	 * Returns the columns in this table.
	 *
	 * @return array An array of Column objects that this table needs to have.
	 */
	public function get_columns(): array
	{
		return $this->columns;
	}

	/**
	 * Returns the indexes in this table.
	 *
	 * @return array An array of Index objects that this table needs to have.
	 */
	public function get_indexes(): array
	{
		return $this->indexes;
	}

	/**
	 * Identify whether a given table exists (silently taking account of the prefix)
	 *
	 * @return True if the database table this object describes already exists in the database.
	 */
	public function exists(): bool
	{
		global $smcFunc, $db_prefix;
		if (self::$table_cache === null)
		{
			self::$table_cache = $smcFunc['db_list_tables']();
		}

		return in_array($db_prefix . $this->table_name, self::$table_cache);
	}

	/**
	 * Create the table defined in this object into the database.
	 *
	 * @return mixed False on creation, otherwise raw database result object
	 */
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

	/**
	 * Given an array of possible engines requested for a table, return which one should be used.
	 *
	 * @param array $possible_engines Array of strings, e.g. ['InnoDB', 'MEMORY']
	 * @return string Name of the selected engine based on preferences and availability, e.g. 'InnoDB'
	 * @todo moove this into MySQL library specifically later
	 */
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

	/**
	 * Given that this object represents an existing table, supply a destination table object
	 * to convert this table to.
	 *
	 * @param Table $dest The destination table object
	 */
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
