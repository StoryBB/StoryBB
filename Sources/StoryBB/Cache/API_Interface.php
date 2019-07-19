<?php

/**
 * This interface specifies what is required for short-term cache API implementations.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * All cache backends supported by StoryBB are required to implement this interface.
 */
interface API_Interface
{
	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false);

	/**
	 * Returns the name for the cache method performed by this API. Likely to be a brand of sorts.
	 *
	 * @return string The name of the cache backend
	 */
	public function getName();

	/**
	 * Connects to the cache method. This defines our $key. If this fails, we return false, otherwise we return true.
	 *
	 * @return boolean Whether or not the cache method was connected to.
	 */
	public function connect();

	/**
	 * Overrides the default prefix. If left alone, this will use the default key defined in the class.
	 *
	 * @param string $key The key to use
	 * @return boolean If this was successful or not.
	 */
	public function setPrefix($key = '');

	/**
	 * Gets the prefix as defined from set or the default.
	 *
	 * @return string the value of $key.
	 */
	public function getPrefix();

	/**
	 * Sets a default Time To Live, if this isn't specified we let the class define it.
	 *
	 * @param int $ttl The default TTL
	 * @return boolean If this was successful or not.
	 */
	public function setDefaultTTL($ttl = 120);

	/**
	 * Gets the TTL as defined from set or the default.
	 *
	 * @return string the value of $ttl.
	 */
	public function getDefaultTTL();

	/**
	 * Gets data from the cache.
	 *
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param string $ttl Overrides the default TTL.
	 * @return mixed The result from the cache, if there is no data or it is invalid, we return null.
	 */
	public function getData($key, $ttl = null);

	/**
	 * Saves to data the cache.
	 *
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param mixed $value The data we wish to save.
	 * @param string $ttl Overrides the default TTL.
	 * @return bool Whether or not we could save this to the cache.
	 */
	public function putData($key, $value, $ttl = null);

	/**
	 * Clean out the cache.
	 *
	 * @param string $type If supported, the type of cache to clear, blank/data or user.
	 * @return bool Whether or not we could clean the cache.
	 */
	public function cleanCache($type = '');

	/**
	 * Invalidate all cached data.
	 *
	 * @return bool Whether or not we could invalidate the cache.
	 */
	public function invalidateCache();

	/**
	 * Closes connections to the cache method.
	 *
	 * @return bool Whether or not we could close connections.
	 */
	public function quit();

	/**
	 * Specify custom settings that the cache API supports.
	 *
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 * @return void No return is needed.
	 */
	public function cacheSettings(array &$config_vars);

	/**
	 * Gets the latest version of StoryBB this is compatible with.
	 *
	 * @return string the value of $key.
	 */
	public function getCompatibleVersion();

	/**
	 * Gets the min version that we support.
	 *
	 * @return string the value of $key.
	 */
	public function getMinimumVersion();
}
