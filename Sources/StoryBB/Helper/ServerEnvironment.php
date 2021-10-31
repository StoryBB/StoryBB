<?php

/**
 * Supporting functions for the server environment.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

class ServerEnvironment
{
	/**
	 * Check if the passed url has an SSL certificate.
	 *
	 * @param string $url to check, in $boardurl format (no trailing slash).
	 * @return bool True if a valid certificate was found.
	 */
	public static function ssl_cert_found($url): bool
	{
		// First, strip the subfolder from the passed url, if any
		$parsedurl = parse_url($url);
		$url = 'ssl://' . $parsedurl['host'] . ':443'; 
		
		// Next, check the ssl stream context for certificate info 
		$result = false;
		$context = stream_context_create(["ssl" => ["capture_peer_cert" => true, "verify_peer" => true, "allow_self_signed" => true]]);
		$stream = @stream_socket_client($url, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
		if ($stream !== false)
		{
			$params = stream_context_get_params($stream);
			$result = isset($params["options"]["ssl"]["peer_certificate"]) ? true : false;
		}

		return $result;
	}

	/**
	 * Check if the passed url has a redirect to https:// by querying headers.
	 *
	 * Note that when force_ssl = 2, StoryBB issues its own redirect...  So if this
	 * returns true, it may be caused by StoryBB, not necessarily an .htaccess redirect.
	 * @param string $url to check, in $boardurl format (no trailing slash).
	 * @return bool True if a redirect was found and false if not.
	 */
	public static function https_redirect_active($url): bool
	{
		// Ask for the headers for the passed url, but via http...
		// Need to add the trailing slash, or it puts it there & thinks there's a redirect when there isn't...
		$url = str_ireplace('https://', 'http://', $url) . '/';
		$headers = @get_headers($url);
		if ($headers === false)
		{
			return false;
		}

		// Now to see if it came back https...
		// First check for a redirect status code in first row (301, 302, 307)
		if (strstr($headers[0], '301') === false && strstr($headers[0], '302') === false && strstr($headers[0], '307') === false)
		{
			return false;
		}

		// Search for the location entry to confirm https
		foreach ($headers as $header)
		{
			if (stristr($header, 'Location: https://') !== false)
			{
				return true;
			}
		}
		return false;
	}
}
