<?php

/**
 * This provides file-based short-term cache functionality.
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
class File extends API
{
	/**
	 * @var string The path to the current $cachedir directory.
	 */
	private $cachedir = null;

	/**
	 * Does basic setup of a cache method when we create the object but before we call connect.
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
		return 'File';
	}

	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		$supported = is_writable($this->cachedir);

		if ($test)
			return $supported;
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
		$key = $this->prefix . strtr($key, ':/', '-_');
		$cachedir = $this->cachedir;

		// StoryBB Data returns $value and $expired.  $expired has a unix timestamp of when this expires.
		if (file_exists($cachedir . '/data_' . $key . '.php') && filesize($cachedir . '/data_' . $key . '.php') > 10)
		{
			// Work around Zend's opcode caching (PHP 5.5+), they would cache older files for a couple of seconds
			// causing newer files to take effect a while later.
			if (function_exists('opcache_invalidate'))
				opcache_invalidate($cachedir . '/data_' . $key . '.php', true);

			if (function_exists('apc_delete_file'))
				@apc_delete_file($cachedir . '/data_' . $key . '.php');

			// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
			@include($cachedir . '/data_' . $key . '.php');
			if (!empty($expired) && isset($value))
			{
				@unlink($cachedir . '/data_' . $key . '.php');
				unset($value);
			}
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
		$key = $this->prefix . strtr($key, ':/', '-_');
		$cachedir = $this->cachedir;

		// Work around Zend's opcode caching (PHP 5.5+), they would cache older files for a couple of seconds
		// causing newer files to take effect a while later.
		if (function_exists('opcache_invalidate'))
			opcache_invalidate($cachedir . '/data_' . $key . '.php', true);

		if (function_exists('apc_delete_file'))
			@apc_delete_file($cachedir . '/data_' . $key . '.php');

		// Otherwise custom cache?
		if ($value === null)
			@unlink($cachedir . '/data_' . $key . '.php');
		else
		{
			$cache_data = '<' . '?' . 'php if (!defined(\'STORYBB\')) die; if (' . (time() + $ttl) . ' < time()) $expired = true; else{$expired = false; $value = \'' . addcslashes($value, "\0" . '\\\'') . '\';}' . '?' . '>';

			// Write out the cache file, check that the cache write was successful; all the data must be written
			// If it fails due to low diskspace, or other, remove the cache file
			// Suppress the warning if we get one - we can legitimately get one if there's a data race.
			$fileSize = @file_put_contents($cachedir . '/data_' . $key . '.php', $cache_data, LOCK_EX);
			if ($fileSize !== strlen($cache_data))
			{
				@unlink($cachedir . '/data_' . $key . '.php');
				return false;
			}
			else
				return true;
		}
	}

	/**
	 * Clean out the cache.
	 *
	 * @param string $type If supported, the type of cache to clear, blank/data or user.
	 * @return bool Whether or not we could clean the cache.
	 */
	public function cleanCache($type = '')
	{
		$cachedir = $this->cachedir;

		// No directory = no game.
		if (!is_dir($cachedir))
			return;

		// Remove the files in StoryBB's own disk cache, if any
		$dh = opendir($cachedir);
		while ($file = readdir($dh))
		{
			if ($file != '.' && $file != '..' && $file != 'index.php' && $file != '.htaccess' && (!$type || substr($file, 0, strlen($type)) == $type))
				@unlink($cachedir . '/' . $file);
		}
		closedir($dh);

		// Make this invalid.
		$this->invalidateCache();

		return true;
	}

	/**
	 * Invalidate all cached data.
	 *
	 * @return bool Whether or not we could invalidate the cache.
	 */
	public function invalidateCache()
	{
		// We don't worry about $cachedir here, since the key is based on the real $cachedir.
		parent::invalidateCache();

		// Since StoryBB is file based, be sure to clear the statcache.
		clearstatcache();

		return true;
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

		$config_vars[] = $txt['cache_sbb_settings'];
		$config_vars[] = array('cachedir', $txt['cachedir'], 'file', 'text', 36, 'cache_cachedir');

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir").prop("disabled", cache_type != "file");
			});';
	}

	/**
	 * Sets the $cachedir or uses the StoryBB default $cachedir.
	 *
	 * @param string $dir A valid path
	 * @return boolean If this was successful or not.
	 */
	public function setCachedir($dir = null)
	{
		global $cachedir;

		// If its invalid, use StoryBB's.
		if (is_null($dir) || !is_writable($dir))
			$this->cachedir = $cachedir;
		else
			$this->cachedir = $dir;
	}

	/**
	 * Gets the current $cachedir.
	 *
	 * @return string the value of $ttl.
	 */
	public function getCachedir()
	{
		return $this->cachedir;
	}
}
