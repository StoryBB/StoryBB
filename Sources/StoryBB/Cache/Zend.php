<?php

/**
 * This class provides support for using the cache module from Zend Platform
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
class Zend extends API
{
	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = function_exists('zend_shm_cache_fetch') || function_exists('output_cache_get');

		if ($test)
			return $supported;
		return parent::isSupported() && $supported;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return 'Zend';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		// Zend's pricey stuff.
		if (function_exists('zend_shm_cache_fetch'))
			return zend_shm_cache_fetch('SBB::' . $key);
		elseif (function_exists('output_cache_get'))
			return output_cache_get($key, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		if (function_exists('zend_shm_cache_store'))
			return zend_shm_cache_store('SBB::' . $key, $value, $ttl);
		elseif (function_exists('output_cache_put'))
			return output_cache_put($key, $value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		$this->invalidateCache();

		return zend_shm_cache_clear('SBB');
	}
}

?>