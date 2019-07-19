<?php

/**
 * This class provides connections to Redis for short-term caching
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * Our Cache API class
 * @package cacheAPI
 */
class Redis extends API
{
	/**
	 * @var \Redis The memcache instance.
	 */
	private $redis = null;

	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		global $cache_redis;

		$supported = class_exists('Redis');

		if ($test)
			return $supported;
		return parent::isSupported() && $supported && !empty($cache_redis);
	}

	/**
	 * Connects to the cache method. This defines our $key. If this fails, we return false, otherwise we return true.
	 *
	 * @return boolean Whether or not the cache method was connected to.
	 */
	public function connect()
	{
		global $cache_redis;

		switch (substr_count($cache_redis, ':'))
		{
			case 0:
			default:
				return false;

			case 1:
				list ($host, $port) = explode(':', $cache_redis);
				$password = null;
				break;

			case 2:
				list ($host, $port, $password) = explode(':', $cache_redis, 3);
				break;
		}

		$this->redis = new \Redis;
		if (empty($port))
		{
			$port = 6379;
		}

		// Do the initial connection.
		$connected = $this->redis->connect($host, $port);
		if (!$connected)
		{
			return false;
		}

		// If we have a password, use it.
		if (!empty($password))
		{
			$connected = $this->redis->auth($password);
		}

		if ($connected)
		{
			$this->redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
			$this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
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
		return 'Redis';
	}

	/**
	 * Overrides the default prefix. If left alone, this will use the default key defined in the class.
	 *
	 * @param string $prefix The key to use
	 * @return boolean If this was successful or not.
	 */
	public function setPrefix($prefix = '')
	{
		global $boardurl, $cachedir;

		// Find a valid good file to do mtime checks on.
		if (file_exists($cachedir . '/' . 'index.php'))
			$filemtime = $cachedir . '/' . 'index.php';
		elseif (is_dir($cachedir . '/'))
			$filemtime = $cachedir . '/';
		else
			$filemtime = $boardurl . '/index.php';

		// Set the default if no prefix was specified.
		if (empty($prefix))
			$this->prefix = 'StoryBB-' . substr(md5($boardurl . filemtime($filemtime)), 0, 16) . '-';
		else
			$this->prefix = $prefix;

		return true;
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
		$value = $this->redis->get($key);

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
		return $this->redis->set($key, $value, $ttl ? $ttl : $this->getDefaultTTL());
	}

	/**
	 * Closes connections to the cache method.
	 *
	 * @return bool Whether or not we could close connections.
	 */
	public function quit()
	{
		return $this->redis->close();
	}

	/**
	 * Clean out the cache.
	 *
	 * @param string $type If supported, the type of cache to clear, blank/data or user.
	 * @return bool Whether or not we could clean the cache.
	 */
	public function cleanCache($type = '')
	{
		return $this->redis->flushDb();
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

		$config_vars[] = $txt['cache_redis_settings'];
		$config_vars[] = array('cache_redis', $txt['cache_redis_server'], 'file', 'text', 0, 'cache_redis', 'postinput' => '<br /><div class="smalltext"><em>' . $txt['cache_redis_server_subtext'] . '</em></div>');
	}
}
