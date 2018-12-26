<?php

/**
 * This class handles installing or updating the DB schema for StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Schema;
use StoryBB\Schema\Schema;

/**
 * This class handles the main database changes for StoryBB.
 */
class Database
{
	public static function update_schema()
	{
		global $smcFunc;

		$schema = Schema::get_schema();
		foreach ($schema as $table)
		{
			if ($table->already_exists())
			{
				// Update table.
			}
			else
			{
				$table->create();
			}
		}
	}
}
