<?php

/**
 * This class handles indexes.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Schema;

use StoryBB\Schema\InvalidIndexException;
use StoryBB\Schema\InvalidIndexTypeException;

/**
 * This class handles alerts.
 */
class Index
{
	private $index;

	/**
	 * Creates an instance representing the index.
	 *
	 * @param array $columns Columns in internal format (a la parse_columns)
	 * @param string $type Type of index to be produced
	 * @return instance
	 */
	private function __construct(array $columns, string $type)
	{
		if (empty($columns))
		{
			throw new InvalidIndexException('An index must contain columns');
		}
		if (!in_array($type, ['primary', 'unique', 'index']))
		{
			throw new InvalidIndexTypeException('Unsupported index type: ' . $type);
		}

		$this->index = [
			'columns' => $columns,
			'type' => $type,
		];
	}

	/**
	 * Converts the syntax used externally for columns (incl. prefix) into internal format.
	 * e.g. ['myfield, 'myfield2' => 15] -> ['myfield', 'myfield2(15)']
	 *
	 * @param array $columns An array of columns where values are columns, or key/values are partial columns.
	 * @return array An array in SQL format
	 */
	protected static function parse_columns(array $columns): array
	{
		$sqlcols = [];
		foreach ($columns as $id => $column)
		{
			if (is_numeric($id) && !is_numeric($column))
			{
				// This came in as a regular column for the array.
				$sqlcols[] = $column;
			}
			else
			{
				$sqlcols[] = $id . '(' . $column . ')';
			}
		}

		return $sqlcols;
	}

	/**
	 * Factory function to create a primary key index.
	 *
	 * @param array Columns to index as part of the primary key
	 * @return Index instance
	 */
	public static function primary(array $columns)
	{
		return new self(self::parse_columns($columns), 'primary');
	}

	/**
	 * Factory function to create a simple index.
	 *
	 * @param array Columns to index as part of the key
	 * @return Index instance
	 */
	public static function key(array $columns)
	{
		return new self(self::parse_columns($columns), 'index');
	}

	/**
	 * Factory function to create a unique index.
	 *
	 * @param array Columns to index as part of the unique key
	 * @return Index instance
	 */
	public static function unique(array $columns)
	{
		return new self(self::parse_columns($columns), 'unique');
	}

	/**
	 * Returns an array suitable for db_create_table to create the indexes on that table.
	 *
	 * @return array Data for db_create_table
	 */
	public function create_data()
	{
		return $this->index;
	}
}
