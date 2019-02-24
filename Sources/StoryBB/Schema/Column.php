<?php

/**
 * This class handles columnss.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Schema;

use StoryBB\Schema\InvalidColumnTypeException;

/**
 * This class handles columns.
 */
class Column
{
	protected $column;

	/**
	 * Private constructor - all instances of Column should be made via the factory methods.
	 *
	 * @param array $column The column being created in general $smcFunc format
	 */
	private function __construct(array $column)
	{
		$defaults = [
			'auto' => false,
			'null' => false,
		];
		$this->column = array_merge($defaults, $column);
	}

	/**
	 * Factory function to create a new Column of tinyint type.
	 *
	 * @return Column instance
	 */
	public static function tinyint()
	{
		return new Column([
			'type' => 'tinyint',
			'default' => 0,
			'size' => 4,
			'unsigned' => true,
		]);
	}

	/**
	 * Factory function to create a new Column of smallint type.
	 *
	 * @return Column instance
	 */
	public static function smallint()
	{
		return new Column([
			'type' => 'smallint',
			'default' => 0,
			'size' => 6,
			'unsigned' => true,
		]);
	}

	/**
	 * Factory function to create a new Column of mediumint type.
	 *
	 * @return Column instance
	 */
	public static function mediumint()
	{
		return new Column([
			'type' => 'mediumint',
			'default' => 0,
			'size' => 8,
			'unsigned' => true,
		]);
	}

	/**
	 * Factory function to create a new Column of int type.
	 *
	 * @return Column instance
	 */
	public static function int()
	{
		return new Column([
			'type' => 'int',
			'default' => 0,
			'size' => 11,
			'unsigned' => true,
		]);
	}

	/**
	 * Factory function to create a new Column of bigint type.
	 *
	 * @return Column instance
	 */
	public static function bigint()
	{
		return new Column([
			'type' => 'bigint',
			'default' => 0,
			'size' => 21,
			'unsigned' => true,
		]);
	}

	/**
	 * Factory function to create a new Column of float type.
	 *
	 * @return Column instance
	 */
	public static function float()
	{
		return new Column([
			'type' => 'float',
			'default' => 0,
		]);
	}

	/**
	 * Factory function to create a new Column of char type.
	 *
	 * @param int $size The size of the char column in characters
	 * @return Column instance
	 */
	public static function char(int $size)
	{
		return new Column([
			'type' => 'char',
			'size' => $size,
			'default' => '',
		]);
	}

	/**
	 * Factory function to create a new Column of varchar type.
	 *
	 * @param int $size The size of the varchar column in characters
	 * @return Column instance
	 */
	public static function varchar(int $size)
	{
		return new Column([
			'type' => 'varchar',
			'size' => $size,
			'default' => '',
		]);
	}

	/**
	 * Factory function to create a new Column of text type.
	 *
	 * @return Column instance
	 */
	public static function text()
	{
		return new Column([
			'type' => 'text',
		]);
	}

	/**
	 * Factory function to create a new Column of mediumtext type.
	 *
	 * @return Column instance
	 */
	public static function mediumtext()
	{
		return new Column([
			'type' => 'mediumtext',
		]);
	}

	/**
	 * Factory function to create a new Column of varbinary type.
	 *
	 * @param int $size Size of the varbinary column
	 * @return Column instance
	 */
	public static function varbinary(int $size)
	{
		return new Column([
			'type' => 'varbinary',
			'size' => $size,
		]);
	}

	/**
	 * Factory function to create a new Column of date type.
	 *
	 * @return Column instance
	 */
	public static function date()
	{
		return new Column([
			'type' => 'date',
			'default' => '1004-01-01',
		]);
	}

	/**
	 * Sets the current column to be auto incremented.
	 *
	 * @return object Returns the current instance so fluent interfacing can be used.
	 */
	public function auto_increment()
	{
		if (!in_array($this->column['type'], ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float']))
		{
			throw new InvalidColumnTypeException($this->column['type'] . ' cannot be autoincrement');
		}
		$this->column['auto'] = true;
		$this->column['default'] = true;

		return $this;
	}

	/**
	 * Sets the current column to be signed rather than the default of unsigned.
	 *
	 * @return object Returns the current instance so fluent interfacing can be used.
	 */
	public function signed()
	{
		if (!in_array($this->column['type'], ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float']))
		{
			throw new InvalidColumnTypeException($this->column['type'] . ' cannot be signed');
		}
		$this->column['unsigned'] = false;

		return $this;
	}

	/**
	 * Sets the column as being nullable.
	 *
	 * >@return object Returns the current instance so fluent interfacing can be used.
	 */
	public function nullable()
	{
		$this->column['null'] = true;

		return $this;
	}

	/**
	 * Sets the default value on the current field. Does not check the default is sane for all field types.
	 *
	 * @return object Returns the current instance so fluent interfacing can be used.
	 */
	public function default($value)
	{
		if (in_array($this->column['type'], ['text', 'mediumtext']))
		{
			throw new InvalidColumnTypeException($this->column['type'] . ' cannot have a default value');
		}
		$this->column['default'] = $value;

		return $this;
	}

	/**
	 * Returns an array suitable for db_create_table to create the table.
	 *
	 * @param string $column_name The name of the final column itself.
	 * @return array Data for db_create_table
	 */
	public function create_data(string $column_name): array
	{
		$column = $this->column;
		$column['name'] = $column_name;
		return $column;
	}
}
