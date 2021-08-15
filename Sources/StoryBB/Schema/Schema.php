<?php

/**
 * This class handles the main database schema for StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Schema;

use StoryBB\Schema\Table;
use StoryBB\Schema\Column;
use StoryBB\Schema\Index;

/**
 * This class handles the main database schema for StoryBB.
 */
class Schema
{
	/**
	 * Returns a list of all tablegroups within the schema.
	 *
	 * @return array An array of the classes that are the tablegroups.
	 */
	public static function get_all_tablegroups(): array
	{
		return [
			'\\StoryBB\\Schema\\TableGroup\\Achievements',
			'\\StoryBB\\Schema\\TableGroup\\Affiliates',
			'\\StoryBB\\Schema\\TableGroup\\ForumContent',
			'\\StoryBB\\Schema\\TableGroup\\PersonalMessages',
			'\\StoryBB\\Schema\\TableGroup\\Permissions',
			'\\StoryBB\\Schema\\TableGroup\\Policies',
			'\\StoryBB\\Schema\\TableGroup\\Polls',
			'\\StoryBB\\Schema\\TableGroup\\Shippers',
			'\\StoryBB\\Schema\\TableGroup\\Tasks',
			'\\StoryBB\\Schema\\TableGroup\\Uncategorised',
		];
	}

	/**
	 * Returns all the tables in core StoryBB, without prefixes.
	 *
	 * @return array An array of Table instances representing the schema.
	 */
	public static function get_tables(): array
	{
		$schema = [];

		$tablegroups = static::get_all_tablegroups();
		foreach ($tablegroups as $tablegroup)
		{
			$schema = array_merge($schema, $tablegroup::return_tables());
		}

		return $schema;
	}
}
