<?php

/**
 * A library for cookies.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

/**
 * A library for cookies.
 */
class Cookie
{
	/**
	 * Get the domain and path for the cookie
	 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
	 * - uses boardurl to determine these two things.
	 *
	 * @param bool $local Whether we want local cookies
	 * @param bool $global Whether we want global cookies
	 * @return array An array to set the cookie on with domain and path in it, in that order
	 */
	public static function url_parts($local, $global)
	{
		global $boardurl, $modSettings;

		// Parse the URL with PHP to make life easier.
		$parsed_url = parse_url($boardurl);

		// Is local cookies off?
		if (empty($parsed_url['path']) || !$local)
			$parsed_url['path'] = '';

		if (!empty($modSettings['globalCookiesDomain']) && strpos($boardurl, $modSettings['globalCookiesDomain']) !== false)
			$parsed_url['host'] = $modSettings['globalCookiesDomain'];

		// Globalize cookies across domains (filter out IP-addresses)?
		elseif ($global && !IP::is_valid_ipv4($parsed_url['host']) && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			$parsed_url['host'] = '.' . $parts[1];

		// We shouldn't use a host at all if both options are off.
		elseif (!$local && !$global)
			$parsed_url['host'] = '';

		// The host also shouldn't be set if there aren't any dots in it.
		elseif (!isset($parsed_url['host']) || strpos($parsed_url['host'], '.') === false)
			$parsed_url['host'] = '';

		return [$parsed_url['host'], $parsed_url['path'] . '/'];
	}
}
