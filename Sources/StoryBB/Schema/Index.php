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

/**
 * This class handles alerts.
 */
class Index
{
	private function __construct()
	{

	}

	/**
	 * Factory function to create a primary key index.
	 *
	 * @param array Columns to index as part of the primary key
	 * @return Index instance
	 */
	public static function primary(array $columns)
	{

	}

	/**
	 * Factory function to create a simple index.
	 *
	 * @param array Columns to index as part of the key
	 * @return Index instance
	 */
	public static function key(array $columns)
	{

	}

	/**
	 * Factory function to create a unique index.
	 *
	 * @param array Columns to index as part of the primary key
	 * @return Index instance
	 */
	public static function unique(array $columns)
	{

	}
}
