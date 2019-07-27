<?php

/**
 * This class produces an object that represents the database.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Database;

use StoryBB\Database\DatabaseAdapter;

/**
 * Provides a DatabaseAdapter object based on the type of database connector needed.
 */
class AdapterFactory
{
	/** @var array $known_adapters List of adapters known to the system. */
	protected static $known_adapters = null;

	/**
	 * Providers the internal list of known adapters for databases.
	 */
	protected static function get_adapters()
	{
		self::$known_adapters = [
			'mysql' => 'StoryBB\\Database\\MySQL',
		];
	}

	/**
	 * Provides a list of available adapters for databases.
	 *
	 * @return array An array of adapters available.
	 */
	public static function available_adapters(): array
	{
		self::get_adapters();
		return array_keys(self::$known_adapters);
	}

	/**
	 * Create a new DB adapter object.
	 *
	 * @param string $db_type List the type of database connector needed
	 * @return DatabaseAdapter An object that implements DatabaseAdapter for the specified DB type
	 */
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
