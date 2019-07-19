<?php

/**
 * This is a lightweight proxy for serving images, generally meant to be used alongside SSL
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use GuzzleHttp\Client;

define('STORYBB', 'proxy');

/**
 * Class ProxyServer
 */
class ProxyServer
{
	/** @var bool $enabled Whether or not this is enabled */
	protected $enabled;

	/** @var int $maxSize The maximum size for files to cache */
	protected $maxSize;

	/** @var string $secret A secret code used for hashing */
	protected $secret;

	/** @var string The cache directory */
	protected $cache;

	/**
	 * Constructor, loads up the Settings for the proxy
	 *
	 * @access public
	 */
	public function __construct()
	{
		global $image_proxy_enabled, $image_proxy_maxsize, $image_proxy_secret, $cachedir, $boarddir;

		require_once(dirname(__FILE__) . '/Settings.php');
		require_once($boarddir . '/vendor/autoload.php');

		// Turn off all error reporting; any extra junk makes for an invalid image.
		//error_reporting(0);

		$this->enabled = (bool) $image_proxy_enabled;
		$this->maxSize = (int) $image_proxy_maxsize;
		$this->secret = (string) $image_proxy_secret;
		$this->cache = $cachedir . '/images';
	}

	/**
	 * Checks whether the request is valid or not
	 *
	 * @access public
	 * @return bool Whether the request is valid
	 */
	public function checkRequest()
	{
		if (!$this->enabled)
			return false;

		// Try to create the image cache directory if it doesn't exist
		if (!file_exists($this->cache))
			if (!mkdir($this->cache) || !copy(dirname($this->cache) . '/index.php', $this->cache . '/index.php'))
				return false;

		if (empty($_GET['hash']) || empty($_GET['request']))
			return false;

		$hash = $_GET['hash'];
		$request = $_GET['request'];

		if (md5($request . $this->secret) != $hash)
			return false;

		// Attempt to cache the request if it doesn't exist
		if (!$this->isCached($request))
			return $this->cacheImage($request);

		return true;
	}

	/**
	 * Serves the request
	 *
	 * @access public
	 * @return void
	 */
	public function serve()
	{
		$request = $_GET['request'];
		// Did we get an error when trying to fetch the image
		$response = $this->checkRequest();
		if ($response === -1)
		{
			// Throw a 404
			send_http_status(404);
			exit;
		}
		// Right, image not cached? Simply redirect, then.
		if ($response === 0)
		{
			$this->redirect($request);
		}

		$cached_file = $this->getCachedPath($request);
		$cached = json_decode(file_get_contents($cached_file), true);

		// Is the cache expired?
		if (!$cached || time() - $cached['time'] > (5 * 86400))
		{
			@unlink($cached_file);
			if ($this->checkRequest())
				$this->serve();
			$this->redirect($request);
		}

		// Right, image not cached? Simply redirect, then.
		if (!$response)
			$this->redirect($request);

		// Make sure we're serving an image
		$contentParts = explode('/', !empty($cached['content_type']) ? $cached['content_type'] : '');
		if ($contentParts[0] != 'image')
			exit;

		header('Content-type: ' . $cached['content_type']);
		header('Content-length: ' . $cached['size']);
		echo base64_decode($cached['body']);
	}

	/**
	 * Returns the request's hashed filepath
	 *
	 * @access public
	 * @param string $request The request to get the path for
	 * @return string The hashed filepath for the specified request
	 */
	protected function getCachedPath(string $request): string
	{
		return $this->cache . '/' . sha1($request . $this->secret);
	}

	/**
	 * Check whether the image exists in local cache or not
	 *
	 * @access protected
	 * @param string $request The image to check for in the cache
	 * @return bool Whether or not the requested image is cached
	 */
	protected function isCached(string $request): bool
	{
		return file_exists($this->getCachedPath($request));
	}

	/**
	 * Attempts to cache the image while validating it
	 *
	 * @access protected
	 * @param string $request The image to cache/validate
	 * @return bool|int Whether the specified image was cached or error code when accessing
	 */
	protected function cacheImage(string $request)
	{
		$client = new Client();
		$http_request = $client->get($request);
		$responseCode = $http_request->getStatusCode();
		$response = (string) $http_request->getBody();

		if (empty($response))
			return false;

		if ($responseCode != 200) {
			return false;
		}

		// Make sure the url is returning an image
		$content_type = $http_request->getHeader('Content-Type');
		$contentParts = explode('/', !empty($content_type[0]) ? $content_type[0] : '');
		if ($contentParts[0] != 'image')
			return false;

		// Validate the filesize
		if ($http_request->getBody()->getSize() > ($this->maxSize * 1024))
			return false;

		return file_put_contents($this->getCachedPath($request), json_encode(array(
			'content_type' => $content_type[0],
			'size' => $http_request->getBody()->getSize(),
			'time' => time(),
			'body' => base64_encode($response),
		))) === false ? 1 : null;
	}

	/**
	 * Initiates a 301 redirect to the URL (presumably because proxying didn't/can't work)
	 *
	 * @param string $url The URL to be redirected to, sanitised by the parser
	 */
	protected function redirect(string $url)
	{
		header('Location: ' . un_htmlspecialchars($url), false, 301);
		exit;
	}
}

$proxy = new ProxyServer();
$proxy->serve();
