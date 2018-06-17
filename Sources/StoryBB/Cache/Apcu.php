<?php

/**
 * This provides short-term caching functionality using APCu (newer version of APC)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * Our Cache API class
 * @package cacheAPI
 */
class Apcu extends API
{
	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		$supported = function_exists('apcu_fetch') && function_exists('apcu_store');

		if ($test)
			return $supported;
		return parent::isSupported() && $supported;
	}

	/**
	 * Returns the name for the cache method performed by this API. Likely to be a brand of sorts.
	 *
	 * @return string The name of the cache backend
	 */
	public function getName()
	{
		return 'APCu';
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

		return apcu_fetch($key . 'sbb');
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		// An extended key is needed to counteract a bug in APC.
		if ($value === null)
			return apcu_delete($key . 'sbb');
		else
			return apcu_store($key . 'sbb', $value, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		$this->invalidateCache();
		return apcu_clear_cache();
	}
}
