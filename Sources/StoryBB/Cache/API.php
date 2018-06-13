<?php

/**
 * This class provides the backbone that all short-term cache APIs need to implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
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
	protected $version_compatible = 'StoryBB 3.0 Alpha 1';

	/**
	 * @var string The minimum StoryBB version that this will work with
	 */
	protected $min_smf_version = 'StoryBB 3.0 Alpha 1';

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
	 *
	 * @access public
	 */
	public function __construct()
	{
		$this->setPrefix('');
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		global $cache_enable;

		if ($test)
			return true;
		return !empty($cache_enable);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function getPrefix()
	{
		return $this->prefix;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setDefaultTTL($ttl = 120)
	{
		$this->ttl = $ttl;

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefaultTTL()
	{
		return $this->ttl;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function quit()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCompatibleVersion()
	{
		return $this->version_compatible;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMiniumnVersion()
	{
		return $this->min_smf_version;
	}
}
