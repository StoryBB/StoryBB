<?php

/**
 * This file provides functionality for using SQLite 3 databases for short term caching.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * SQLite Cache API class
 * @package cacheAPI
 */
class Sqlite extends API
{
	/**
	 * @var string The path to the current $cachedir directory.
	 */
	private $cachedir = null;
	/**
	 * @var SQLite3 Class instance to connect to the SQLite3 database for our cache
	 */
	private $cacheDB = null;
	/**
	 * @var int Time the class was instantiated for calculating TTLs
	 */
	private $cacheTime = 0;

	/**
	 * Class constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Set our default cachedir.
		$this->setCachedir();
	}

	/**
	 * Returns the name for the cache method performed by this API. Likely to be a brand of sorts.
	 *
	 * @return string The name of the cache backend
	 */
	public function getName()
	{
		return 'SQLite';
	}

	/**
	 * Connects to the cache method. This defines our $key. If this fails, we return false, otherwise we return true.
	 *
	 * @return boolean Whether or not the cache method was connected to.
	 */
	public function connect()
	{
		$database = $this->cachedir . '/' . 'SQLite3Cache.db3';
		$this->cacheDB = new SQLite3($database);
		$this->cacheDB->busyTimeout(1000);
		if (filesize($database) == 0)
		{
			$this->cacheDB->exec('CREATE TABLE cache (key text unique, value blob, ttl int);');
			$this->cacheDB->exec('CREATE INDEX ttls ON cache(ttl);');
		}
		$this->cacheTime = time();
	}

	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		$supported = class_exists("SQLite3") && is_writable($this->cachedir);

		if ($test)
		{
			return $supported;
		}

		return parent::isSupported() && $supported;
	}

	/**
	 * Gets data from the cache.
	 *
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param string $ttl Overrides the default TTL.
	 * @return mixed The result from the cache, if there is no data or it is invalid, we return null.
	 */
	public function getData($key, $ttl = null)
	{
		$ttl = time() - $ttl;
		$query = 'SELECT value FROM cache WHERE key = \'' . $this->cacheDB->escapeString($key) . '\' AND ttl >= ' . $ttl . ' LIMIT 1';
		$result = $this->cacheDB->query($query);

		$value = null;
		while ($res = $result->fetchArray(SQLITE3_ASSOC))
		{
			$value = $res['value'];
		}

		return !empty($value) ? $value : null;
	}

	/**
	 * Saves to data the cache.
	 *
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param mixed $value The data we wish to save.
	 * @param string $ttl Overrides the default TTL.
	 * @return bool Whether or not we could save this to the cache.
	 */
	public function putData($key, $value, $ttl = null)
	{
		$ttl = $this->cacheTime + $ttl;
		$query = 'REPLACE INTO cache VALUES (\'' . $this->cacheDB->escapeString($key) . '\', \'' . $this->cacheDB->escapeString($value) . '\', ' . $this->cacheDB->escapeString($ttl) . ');';
		$result = $this->cacheDB->exec($query);

		return $result;
	}

	/**
	 * Clean out the cache.
	 *
	 * @param string $type If supported, the type of cache to clear, blank/data or user.
	 * @return bool Whether or not we could clean the cache.
	 */
	public function cleanCache($type = '')
	{
		$query = 'DELETE FROM cache;';
		$result = $this->cacheDB->exec($query);

		return $result;
	}

	/**
	 * Specify custom settings that the cache API supports.
	 *
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 * @return void No return is needed.
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$config_vars[] = $txt['cache_sqlite_settings'];
		$config_vars[] = array('cachedir_sqlite', $txt['cachedir_sqlite'], 'file', 'text', 36, 'cache_sqlite_cachedir');

		if (!isset($context['settings_post_javascript']))
		{
			$context['settings_post_javascript'] = '';
		}

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir_sqlite").prop("disabled", cache_type != "sqlite");
			});';
	}

	/**
	 * Sets the $cachedir or uses the StoryBB default $cachedir..
	 *
	 * @param string $dir A valid path
	 * @return boolean If this was successful or not.
	 */
	public function setCachedir($dir = null)
	{
		global $cachedir_sqlite;

		// If its invalid, use StoryBB's.
		if (is_null($dir) || !is_writable($dir))
		{
			$this->cachedir = $cachedir_sqlite;
		}
		else
		{
			$this->cachedir = $dir;
		}
	}
}
