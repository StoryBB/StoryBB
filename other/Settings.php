<?php

/**
 * The settings file contains all of the basic settings that need to be present when a database/cache is not available.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/********* Forum Info *********/
/**
 * The default language file set for the forum.
 * @var string
 */
$language = 'en-us';
/**
 * URL to your forum's folder. (without the trailing /!)
 * @var string
 */
$boardurl = 'http://127.0.0.1/storybb';
/**
 * Name of the cookie to set for authentication.
 * @var string
 */
$cookiename = 'SBBCookie11';

/********* Database Info *********/
/**
 * The database type
 * Default options: mysql
 * @var string
 */
$db_type = 'mysql';
/**
 * The server to connect to (or a Unix socket)
 * @var string
 */
$db_server = 'localhost';
/**
 * The database name
 * @var string
 */
$db_name = 'storybb';
/**
 * Database username
 * @var string
 */
$db_user = 'root';
/**
 * Database password
 * @var string
 */
$db_passwd = '';
/**
 * A prefix to put in front of your table names.
 * This helps to prevent conflicts
 * @var string
 */
$db_prefix = 'sbb_';
/**
 * Use a persistent database connection
 * @var int|bool
 */
$db_persist = 0;

/********* Cache Info *********/
/**
 * Select a cache system. You want to leave this up to the cache area of the admin panel for
 * proper detection of APCu, memcached, output_cache, file, etc
 * (you can add more with a mod).
 * @var string
 */
$cache_accelerator = '';
/**
 * The level at which you would like to cache. Between 0 (off) through 3 (cache a lot).
 * @var int
 */
$cache_enable = 0;
/**
 * This is only used for memcache / memcached. Should be a string of 'server:port,server:port'
 * @var array
 */
$cache_memcached = '';
/**
 * This is only used for Redis. Should be: server:port:password
 * @var string
 */
$cache_redis = '';
/**
 * This is only for the file cache system. It is the path to the cache directory.
 * It is also recommended that you place this in /tmp/ if you are going to use this.
 * @var string
 */
$cachedir = dirname(__FILE__) . '/cache';

/********* Image Proxy *********/
// This is done entirely in Settings.php to avoid loading the DB while serving the images
/**
 * Whether the proxy is enabled or not
 * @var bool
 */
$image_proxy_enabled = true;

/**
 * Secret key to be used by the proxy
 * @var string
 */
$image_proxy_secret = 'storybbisawesome';

/**
 * Maximum file size (in KB) for indiviudal files
 * @var int
 */
$image_proxy_maxsize = 5192;

/********* Directories/Files *********/
// Note: These directories do not have to be changed unless you move things.
/**
 * The absolute path to the forum's folder. (not just '.'!)
 * @var string
 */
$boarddir = dirname(__FILE__);
/**
 * Path to the Sources directory.
 * @var string
 */
$sourcedir = dirname(__FILE__) . '/Sources';

if (file_exists(dirname(__FILE__) . '/install.php'))
{
	header('Location: http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 's' : '') . '://' . (empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST']) . (strtr(dirname($_SERVER['PHP_SELF']), '\\', '/') == '/' ? '' : strtr(dirname($_SERVER['PHP_SELF']), '\\', '/')) . '/install.php'); exit;
}

// Make sure the paths are correct... at least try to fix them.
if (!file_exists($boarddir) && file_exists(dirname(__FILE__) . '/subscriptions.php'))
	$boarddir = dirname(__FILE__);
if (!file_exists($sourcedir) && file_exists($boarddir . '/Sources'))
	$sourcedir = $boarddir . '/Sources';
if (!file_exists($cachedir) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';
