<?php

/**
 * This class provides the backbone that all short-term cache APIs need to implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * This provides generic functionality for cache backends, that might need to be overridden.
 */
abstract class API implements API_Interface
{
	/**
	 * @var string The last version of StoryBB that this was tested on. Helps protect against API changes.
	 */
	protected $version_compatible = 'StoryBB 1.0 Alpha 1';

	/**
	 * @var string The minimum StoryBB version that this will work with
	 */
	protected $min_sbb_version = 'StoryBB 1.0 Alpha 1';

	/**
	 * @var string The prefix for all keys.
	 */
	protected $prefix = '';

	/**
	 * @var int The default TTL.
	 */
	protected $ttl = 120;

	/**
	 * Does basic setup of a cache method when we create the object but before we call connect.
	 */
	public function __construct()
	{
		$this->setPrefix('');
	}

	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		global $cache_enable;

		if ($test)
			return true;
		return !empty($cache_enable);
	}

	/**
	 * Connects to the cache method. This defines our $key. If this fails, we return false, otherwise we return true.
	 *
	 * @return boolean Whether or not the cache method was connected to.
	 */
	public function connect()
	{
	}

	/**
	 * Returns the name for the cache method performed by this API. Likely to be a brand of sorts.
	 *
	 * @return string The name of the cache backend
	 */
	public function getName()
	{
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
			$this->prefix = md5($boardurl . filemtime($filemtime)) . '-StoryBB-';
		else
			$this->prefix = $prefix;

		return true;
	}

	/**
	 * Gets the prefix as defined from set or the default.
	 *
	 * @return string the value of $key.
	 */
	public function getPrefix()
	{
		return $this->prefix;
	}

	/**
	 * Sets a default Time To Live, if this isn't specified we let the class define it.
	 *
	 * @param int $ttl The default TTL
	 * @return boolean If this was successful or not.
	 */
	public function setDefaultTTL($ttl = 120)
	{
		$this->ttl = $ttl;

		return true;
	}

	/**
	 * Gets the TTL as defined from set or the default.
	 *
	 * @return string the value of $ttl.
	 */
	public function getDefaultTTL()
	{
		return $this->ttl;
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
	}

	/**
	 * Clean out the cache.
	 *
	 * @param string $type If supported, the type of cache to clear, blank/data or user.
	 * @return bool Whether or not we could clean the cache.
	 */
	public function cleanCache($type = '')
	{
	}

	/**
	 * Invalidate all cached data.
	 *
	 * @return bool Whether or not we could invalidate the cache.
	 */
	public function invalidateCache()
	{
		global $cachedir;

		// Invalidate cache, to be sure!
		// ... as long as index.php can be modified, anyway.
		if (is_writable($cachedir . '/' . 'index.php'))
			@touch($cachedir . '/' . 'index.php');

		return true;
	}

	/**
	 * Closes connections to the cache method.
	 *
	 * @return bool Whether or not we could close connections.
	 */
	public function quit()
	{
	}

	/**
	 * Specify custom settings that the cache API supports.
	 *
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 * @return void No return is needed.
	 */
	public function cacheSettings(array &$config_vars)
	{
	}

	/**
	 * Gets the latest version of StoryBB this is compatible with.
	 *
	 * @return string the value of $key.
	 */
	public function getCompatibleVersion()
	{
		return $this->version_compatible;
	}

	/**
	 * Gets the min version that we support.
	 *
	 * @return string the value of $key.
	 */
	public function getMinimumVersion()
	{
		return $this->min_sbb_version;
	}
}
