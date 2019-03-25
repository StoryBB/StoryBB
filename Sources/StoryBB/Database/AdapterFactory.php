<?php

/**
 * This class produces an object that represents the database.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Database;

use StoryBB\Database\DatabaseAdapter;

class AdapterFactory
{
	protected static $known_adapters = null;

	protected static function get_adapters()
	{
		self::$known_adapters = [
			'mysql' => 'StoryBB\\Database\\MySQL',
		];
	}

	public static function available_adapters(): array
	{
		self::get_adapters();
		return array_keys(self::$known_adapters);
	}

	public static function get_adapter(string $db_type): DatabaseAdapter
	{
		self::get_adapters();
		if (!isset(self::$known_adapters[$db_type]))
		{
			throw new InvalidAdapterException($db_type);
		}

		return new self::$known_adapters[$db_type];
	}
}
