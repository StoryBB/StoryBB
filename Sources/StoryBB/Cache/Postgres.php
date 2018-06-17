<?php

/**
 * This file handles using PostgreSQL as a cache backend.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * PostgreSQL Cache API class
 * @package cacheAPI
 */
class Postgres extends API
{

	/**
	 * @var false|resource of the pg_prepare from get_data.
	 */
	private $pg_get_data_prep;
	
	/**
	 * @var false|resource of the pg_prepare from put_data.
	 */
	private $pg_put_data_prep;

	public function __construct()
	{
		parent::__construct();

	}

	/**
	 * Connects to the cache method. This defines our $key. If this fails, we return false, otherwise we return true.
	 *
	 * @return boolean Whether or not the cache method was connected to.
	 */
	public function connect()
	{
		global $db_prefix, $db_connection;

		pg_prepare($db_connection, '', 'SELECT 1 
			FROM   pg_tables
			WHERE  schemaname = $1
			AND    tablename = $2');

		$result = pg_execute($db_connection, '', array('public', $db_prefix . 'cache'));

		if (pg_affected_rows($result) === 0)
			pg_query($db_connection, 'CREATE UNLOGGED TABLE {db_prefix}cache (key text, value text, ttl bigint, PRIMARY KEY (key))');			
	}

	/**
	 * Returns the name for the cache method performed by this API. Likely to be a brand of sorts.
	 *
	 * @return string The name of the cache backend
	 */
	public function getName()
	{
		return 'Postgres';
	}

	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		global $smcFunc, $db_connection;
		

		if ($smcFunc['db_title'] !== 'PostgreSQL')
			return false;

		$result = pg_query($db_connection, 'SHOW server_version_num');
		$res = pg_fetch_assoc($result);
		
		if ($res['server_version_num'] < 90500)
			return false;
		
		return $test ? true : parent::isSupported();
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
		global $db_prefix, $db_connection;

		$ttl = time() - $ttl;
		
		if (empty($this->pg_get_data_prep))
			$this->pg_get_data_prep = pg_prepare($db_connection, 'sbb_cache_get_data', 'SELECT value FROM ' . $db_prefix . 'cache WHERE key = $1 AND ttl >= $2 LIMIT 1');
			
		$result = pg_execute($db_connection, 'sbb_cache_get_data', array($key, $ttl));
		
		if (pg_affected_rows($result) === 0)
			return null;

		$res = pg_fetch_assoc($result);

		return $res['value'];
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
		global  $db_prefix, $db_connection;

		if (!isset($value))
			$value = '';

		$ttl = time() + $ttl;
		
		if (empty($this->pg_put_data_prep))
			$this->pg_put_data_prep = pg_prepare($db_connection, 'sbb_cache_put_data',
				'INSERT INTO ' . $db_prefix . 'cache(key,value,ttl) VALUES($1,$2,$3)
				ON CONFLICT(key) DO UPDATE SET value = excluded.value, ttl = excluded.ttl'
			);

		$result = pg_execute($db_connection, 'sbb_cache_put_data', array($key, $value, $ttl));

		if (pg_affected_rows($result) > 0)
			return true;
		else
			return false;
	}

	/**
	 * Clean out the cache.
	 *
	 * @param string $type If supported, the type of cache to clear, blank/data or user.
	 * @return bool Whether or not we could clean the cache.
	 */
	public function cleanCache($type = '')
	{
		global $smcFunc;

		$smcFunc['db_query']('',
				'TRUNCATE TABLE {db_prefix}cache',
				array()
			);

		return true;
	}
}
