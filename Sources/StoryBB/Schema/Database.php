<?php

/**
 * This class handles installing or updating the DB schema for StoryBB
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Schema;

use StoryBB\Container;
use StoryBB\Database\DatabaseAdapter;
use StoryBB\Schema\Schema;

/**
 * This class handles the main database changes for StoryBB.
 */
class Database
{
	/**
	 * Go through the defined schema, see if tables need creating or updating, and action those.
	 *
	 * @param bool $safe_mode If true, do not run the queries but instead return them.
	 * @return If safe mode is enabled, return an array of queries.
	 */
	public static function update_schema(DatabaseAdapter $db, bool $safe_mode = true)
	{
		$queries = [];
		$schema = Schema::get_tables();
		foreach ($schema as $table)
		{
			if ($table->exists($db))
			{
				$existing_table = $db->get_table_structure('{db_prefix}' . $table->get_table_name());
				$queries[] = $existing_table->update_to($db, $table, $safe_mode);
			}
			else
			{
				$queries[] = $table->create($db, $safe_mode);
			}
		}

		if ($safe_mode)
		{
			return $queries;
		}
	}

	/**
	 * Get the available engines supported by the database system in use.
	 *
	 * @return array A list of engines supported by the underlying DB (currently MySQL only)
	 */
	public static function get_engines(): array
	{
		static $engines = null;

		if ($engines === null)
		{
			$container = Container::instance();
			$db = $container->get('database');

			// Figure out which engines we have
			$engines = [];
			$get_engines = $db->query('', 'SHOW ENGINES', []);

			while ($row = $db->fetch_assoc($get_engines))
			{
				if ($row['Support'] == 'YES' || $row['Support'] == 'DEFAULT')
					$engines[] = $row['Engine'];
			}

			$db->free_result($get_engines);
		}

		return $engines;
	}
}
