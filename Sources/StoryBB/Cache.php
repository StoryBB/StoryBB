<?php

/**
 * General class for handling the short-term cache available to StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

/**
 * A singleton for containing the current cache backend and accessing it.
 */
class Cache
{
	/** @var $cacheAPI The cache object */
	private static $cacheAPI = null;

	/**
	 * Initialise the cache instance.
	 *
	 * @param string $overrideCache Manually specify a cache type to override configuration
	 * @param bool $fallback_file Fall back to the file cache if the specified type can't be initialised
	 * @return object Cache instance
	 */
	public static function initialize($overrideCache = null, $fallback_file = true)
	{
		global $cache_accelerator;
		// Not overriding this and we have a cacheAPI, send it back.
		if (empty($overrideCache) && is_object(self::$cacheAPI))
			return self::$cacheAPI;
		elseif (is_null(self::$cacheAPI))
			self::$cacheAPI = false;

		// What accelerator we are going to try.
		$tryAccelerator = !empty($overrideCache) ? $overrideCache : !empty($cache_accelerator) ? $cache_accelerator : 'File';
		$tryAccelerator = ucfirst(strtolower($tryAccelerator));

		// Do some basic tests.
		if (class_exists('StoryBB\\Cache\\' . $tryAccelerator))
		{
			$cache_class_name = 'StoryBB\\Cache\\' . $tryAccelerator;
			$testAPI = new $cache_class_name();

			// No Support?  NEXT!
			if (!$testAPI->isSupported())
			{
				// Can we save ourselves?
				if (!empty($fallback_file) && is_null($overrideCache) && $tryAccelerator != 'File')
					return self::initialise(null, false);
				return false;
			}

			// Connect up to the accelerator.
			$testAPI->connect();

			// Don't set this if we are overriding the cache.
			if (is_null($overrideCache))
			{
				self::$cacheAPI = $testAPI;
				return self::$cacheAPI;
			}
			else
				return $testAPI;
		}
	}

	/**
	 * Gets a value from the cache.
	 *
	 * @param string $key The cache key
	 * @param int $ttl The expected time-to-live for the cache item
	 * @return mixed The cache value, or null if not found
	 */
	public static function get($key, $ttl = 120)
	{
		global $boardurl, $modSettings, $cache_enable, $cacheAPI;
		global $cache_hits, $cache_count, $cache_misses, $cache_count_misses, $db_show_debug;

		if (empty($cache_enable) || empty(self::$cacheAPI))
			return;

		$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
		if (isset($db_show_debug) && $db_show_debug === true)
		{
			$cache_hits[$cache_count] = array('k' => $key, 'd' => 'get');
			$st = microtime(true);
			$original_key = $key;
		}

		// Ask the API to get the data.
		$value = self::$cacheAPI->getData($key, $ttl);

		if (isset($db_show_debug) && $db_show_debug === true)
		{
			$cache_hits[$cache_count]['t'] = microtime(true) - $st;
			$cache_hits[$cache_count]['s'] = isset($value) ? strlen($value) : 0;

			if (empty($value))
			{
				if (!is_array($cache_misses))
					$cache_misses = [];

				$cache_count_misses = isset($cache_count_misses) ? $cache_count_misses + 1 : 1;
				$cache_misses[$cache_count_misses] = array('k' => $original_key, 'd' => 'get');
			}
		}

		if (function_exists('call_integration_hook') && isset($value))
			call_integration_hook('cache_get_data', array(&$key, &$ttl, &$value));

		return empty($value) ? null : sbb_json_decode($value, true);
	}

	/**
	 * Puts a value into the cache.
	 *
	 * @param string $key Key to save into the cache
	 * @param mixed $value The value to save
	 * @param int $ttl The expected time-to-live for the cache item
	 */
	public static function put(string $key, $value, int $ttl = 120)
	{
		global $boardurl, $modSettings, $cache_enable, $cacheAPI;
		global $cache_hits, $cache_count, $db_show_debug;

		if (empty($cache_enable) || empty(self::$cacheAPI))
			return;

		$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
		if (isset($db_show_debug) && $db_show_debug === true)
		{
			$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $value === null ? 0 : strlen(json_encode($value)));
			$st = microtime(true);
		}

		// The API will handle the rest.
		$value = $value === null ? null : json_encode($value);
		self::$cacheAPI->putData($key, $value, $ttl);

		if (function_exists('call_integration_hook'))
			call_integration_hook('cache_put_data', array(&$key, &$value, &$ttl));

		if (isset($db_show_debug) && $db_show_debug === true)
			$cache_hits[$cache_count]['t'] = microtime(true) - $st;
	}

	/**
	 * List available cache types.
	 *
	 * @return array An array of cache ID to cache name.
	 */
	public static function list_available(): array
	{
		global $sourcedir;
		$apis = [];
		if ($dh = opendir($sourcedir . '/StoryBB/Cache'))
		{
			while (($file = readdir($dh)) !== false)
			{
				if (is_file($sourcedir . '/StoryBB/Cache/' . $file))
				{
					$class = preg_replace('~\.php$~', '', $file);
					if (strpos($class, 'API') === 0)
						continue;

					$tryCache = ucfirst(strtolower($class));
					$cache_class_name = 'StoryBB\\Cache\\' . $tryCache;
					if (!class_exists($cache_class_name))
						continue;

					$testAPI = new $cache_class_name;

					// No Support?  NEXT!
					if (!$testAPI->isSupported(true))
						continue;

					$apis[$tryCache] = $testAPI->getName();
				}
			}
		}
		closedir($dh);
		return $apis;
	}

	/**
	 * Empty out the cache if possible.
	 *
	 * @param string $type The type of data to be flushed specifically.
	 */
	public static function flush($type)
	{
		// If we can't get to the API, can't do this.
		if (empty(self::$cacheAPI))
			return;

		// Ask the API to do the heavy lifting. cleanCache also calls invalidateCache to be sure.
		self::$cacheAPI->cleanCache($type);

		call_integration_hook('integrate_clean_cache');
		clearstatcache();
	}
}
