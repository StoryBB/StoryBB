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
	/**
	 * Go through the defined schema, see if tables need creating or updating, and action those.
	 */
	public static function update_schema()
	{
		global $smcFunc;

		$schema = Schema::get_schema();
		foreach ($schema as $table)
		{
			if ($table->exists())
			{
				$table->update();
			}
			else
			{
				$table->create();
			}
		}
	}

	/**
	 * Get the available engines supported by the database system in use.
	 *
	 * @return array A list of engines supported by the underlying DB (currently MySQL only)
	 */
	public static function get_engines(): array
	{
		global $smcFunc;
		static $engines = null;

		if ($engines === null)
		{
			// Figure out which engines we have
			$engines = [];
			$get_engines = $smcFunc['db_query']('', 'SHOW ENGINES', array());

			while ($row = $smcFunc['db_fetch_assoc']($get_engines))
			{
				if ($row['Support'] == 'YES' || $row['Support'] == 'DEFAULT')
					$engines[] = $row['Engine'];
			}

			$smcFunc['db_free_result']($get_engines);
		}

		return $engines;
	}
}
