<?php

/**
 * Support functions for managing IP addresses.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Helper;

/**
 * Support functions for managing IP addresses.
 */
class IP
{
	/**
	 * Identify whether the IP address is a valid IPv4 address.
	 *
	 * @param string $ip The IP address
	 * @return bool True if the IP address is a valid IPv4 address
	 */
	public static function is_valid_ipv4($ip)
	{
		return preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip) == 1;
	}

	/**
	 * Identify whether the IP address is a valid IPv6 address.
	 *
	 * @param string $ip The IP address
	 * @return bool True if the IP address is a valid IPv6 address
	 */
	public static function is_valid_ipv6($ip)
	{
		// Looking for :, it won't be valid without one.
		if (strpos($ip, ':') === false)
			return false;

		// Check it's a valid address.
		return inet_pton($ip) !== false;
	}

	/**
	 * Identify whether the given string is a valid IPv4 or IPv6 address.
	 *
	 * @param string $ip
	 * @return bool
	 */
	public static function is_valid($ip)
	{
		return filter_var($ip, FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Lookup an IP; try shell_exec first because we can do a timeout on it.
	 *
	 * @param string $ip The IP to get the hostname from
	 * @return string The hostname
	 */
	public static function get_host($ip)
	{
		global $modSettings;

		if (($host = cache_get_data('hostlookup-' . $ip, 600)) !== null)
			return $host;
		$t = microtime(true);

		// Try the Linux host command, perhaps?
		if (!isset($host) && (strpos(strtolower(PHP_OS), 'win') === false || strpos(strtolower(PHP_OS), 'darwin') !== false) && mt_rand(0, 1) == 1)
		{
			if (!isset($modSettings['host_to_dis']))
				$test = @shell_exec('host -W 1 ' . @escapeshellarg($ip));
			else
				$test = @shell_exec('host ' . @escapeshellarg($ip));

			// Did host say it didn't find anything?
			if (strpos($test, 'not found') !== false)
				$host = '';
			// Invalid server option?
			elseif ((strpos($test, 'invalid option') || strpos($test, 'Invalid query name 1')) && !isset($modSettings['host_to_dis']))
				updateSettings(array('host_to_dis' => 1));
			// Maybe it found something, after all?
			elseif (preg_match('~\s([^\s]+?)\.\s~', $test, $match) == 1)
				$host = $match[1];
		}

		// This is nslookup; usually only Windows, but possibly some Unix?
		if (!isset($host) && stripos(PHP_OS, 'win') !== false && strpos(strtolower(PHP_OS), 'darwin') === false && mt_rand(0, 1) == 1)
		{
			$test = @shell_exec('nslookup -timeout=1 ' . @escapeshellarg($ip));
			if (strpos($test, 'Non-existent domain') !== false)
				$host = '';
			elseif (preg_match('~Name:\s+([^\s]+)~', $test, $match) == 1)
				$host = $match[1];
		}

		// This is the last try :/.
		if (!isset($host) || $host === false)
			$host = @gethostbyaddr($ip);

		// It took a long time, so let's cache it!
		if (microtime(true) - $t > 0.5)
			cache_put_data('hostlookup-' . $ip, $host, 600);

		return $host;
	}
}
