<?php

/**
 * This class provides connections to memcache (old version) for short-term caching
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * Our Cache API class
 * @package cacheAPI
 */
class Memcache extends API
{
	/**
	 * @var \Memcache The memcache instance.
	 */
	private $memcache = null;

	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		global $cache_memcached;

		$supported = class_exists('memcache');

		if ($test)
			return $supported;
		return parent::isSupported() && $supported && !empty($cache_memcached);
	}

	/**
	 * Connects to the cache method. This defines our $key. If this fails, we return false, otherwise we return true.
	 *
	 * @return boolean Whether or not the cache method was connected to.
	 */
	public function connect()
	{
		global $db_persist, $cache_memcached;

		$servers = explode(',', $cache_memcached);
		$port = 0;

		// Don't try more times than we have servers!
		$connected = false;
		$level = 0;

		// We should keep trying if a server times out, but only for the amount of servers we have.
		while (!$connected && $level < count($servers))
		{
			++$level;
			$this->memcache = new Memcache();
			$server = trim($servers[array_rand($servers)]);

			// Normal host names do not contain slashes, while e.g. unix sockets do. Assume alternative transport pipe with port 0.
			if (strpos($server, '/') !== false)
				$host = $server;
			else
			{
				$server = explode(':', $server);
				$host = $server[0];
				$port = isset($server[1]) ? $server[1] : 11211;
			}

			// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
			if (empty($db_persist))
				$connected = $this->memcache->connect($host, $port);
			else
				$connected = $this->memcache->pconnect($host, $port);
		}

		return $connected;
	}

	/**
	 * Returns the name for the cache method performed by this API. Likely to be a brand of sorts.
	 *
	 * @return string The name of the cache backend
	 */
	public function getName()
	{
		return 'Memcache';
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
		$key = $this->prefix . strtr($key, ':/', '-_');

		$value = $this->memcache->get($key);

		// $value should return either data or false (from failure, key not found or empty array).
		if ($value === false)
			return null;
		return $value;
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
		$key = $this->prefix . strtr($key, ':/', '-_');

		return $this->memcache->set($key, $value, 0, $ttl);
	}

	/**
	 * Closes connections to the cache method.
	 *
	 * @return bool Whether or not we could close connections.
	 */
	public function quit()
	{
		return $this->memcache->close();
	}

	/**
	 * Clean out the cache.
	 *
	 * @param string $type If supported, the type of cache to clear, blank/data or user.
	 * @return bool Whether or not we could clean the cache.
	 */
	public function cleanCache($type = '')
	{
		$this->invalidateCache();
		return $this->memcache->flush();
	}

	/**
	 * Return the version of the Memcache server.
	 *
	 * @return string Version number
	 */
	public function getServerVersion(): string
	{
		return $this->memcache->getVersion();
	}
}
