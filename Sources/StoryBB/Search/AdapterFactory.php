<?php

/**
 * Handles producing a connector to the search backend.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search;

use StoryBB\ClassManager;
use StoryBB\Search\Exception\InvalidAdapterException;

class AdapterFactory
{
	/** @var array $known_adapters List of adapters known to the system. */
	protected static $known_adapters = null;

	/**
	 * Providers the internal list of known adapters for search backends.
	 */
	protected static function get_adapters(): void
	{
		if (static::$known_adapters !== null)
		{
			return;
		}

		static::$known_adapters = [];
		foreach (ClassManager::get_classes_implementing('StoryBB\\Search\\SearchAdapter') as $class)
		{
			$code = substr(strrchr($class, '\\'), 1);
			static::$known_adapters[$code] = $class;
		}
	}

	/**
	 * Provides a list of available adapters for search backends.
	 *
	 * @return array An array of adapters available.
	 */
	public static function available_adapters(): array
	{
		static::get_adapters();
		return array_keys(static::$known_adapters);
	}

	/**
	 * Create a new search adapter object.
	 *
	 * @param string $search_type List the type of database connector needed
	 * @return SearchAdapter An object that implements SearcgAdapter for the specified search type
	 */
	public static function get_adapter_class(string $search_type)
	{
		static::get_adapters();
		if (!isset(static::$known_adapters[$search_type]))
		{
			throw new InvalidAdapterException($search_type);
		}

		return static::$known_adapters[$search_type];
	}
}
