<?php

/**
 * Support functions for managing IP addresses.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
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
	public static function is_valid_ipv4(string $ip): bool
	{
		return preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip) == 1;
	}

	/**
	 * Identify whether the IP address is a valid IPv6 address.
	 *
	 * @param string $ip The IP address
	 * @return bool True if the IP address is a valid IPv6 address
	 */
	public static function is_valid_ipv6(string $ip): bool
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
	public static function is_valid(string $ip): bool
	{
		return filter_var($ip, FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Lookup an IP; try shell_exec first because we can do a timeout on it.
	 *
	 * @param string $ip The IP to get the hostname from
	 * @return string The hostname
	 */
	public static function get_host(string $ip): string
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

	/**
	 * Convert a single IP to a ranged IP.
	 * internal function used to convert a user-readable format to a format suitable for the database.
	 *
	 * @param string $fullip The full IP
	 * @return array An array of IP parts
	 */
	public static function to_range($fullip)
	{
		// Pretend that 'unknown' is 255.255.255.255. (since that can't be an IP anyway.)
		if ($fullip == 'unknown')
			$fullip = '255.255.255.255';

		$ip_parts = explode('-', $fullip);
		$ip_array = [];

		// if ip 22.12.31.21
		if (count($ip_parts) == 1 && self::is_valid($fullip))
		{
			$ip_array['low'] = $fullip;
			$ip_array['high'] = $fullip;
			return $ip_array;
		} // if ip 22.12.* -> 22.12.* - 22.12.*
		elseif (count($ip_parts) == 1)
		{
			$ip_parts[0] = $fullip;
			$ip_parts[1] = $fullip;
		}

		// if ip 22.12.31.21-12.21.31.21
		if (count($ip_parts) == 2 && self::is_valid($ip_parts[0]) && self::is_valid($ip_parts[1]))
		{
			$ip_array['low'] = $ip_parts[0];
			$ip_array['high'] = $ip_parts[1];
			return $ip_array;
		}
		elseif (count($ip_parts) == 2) // if ip 22.22.*-22.22.*
		{
			$valid_low = self::is_valid($ip_parts[0]);
			$valid_high = self::is_valid($ip_parts[1]);
			$count = 0;
			$mode = (preg_match('/:/', $ip_parts[0]) > 0 ? ':' : '.');
			$max = ($mode == ':' ? 'ffff' : '255');
			$min = 0;
			if(!$valid_low)
			{
				$ip_parts[0] = preg_replace('/\*/', '0', $ip_parts[0]);
				$valid_low = self::is_valid($ip_parts[0]);
				while (!$valid_low)
				{
					$ip_parts[0] .= $mode . $min;
					$valid_low = self::is_valid($ip_parts[0]);
					$count++;
					if ($count > 9) break;
				}
			}

			$count = 0;
			if(!$valid_high)
			{
				$ip_parts[1] = preg_replace('/\*/', $max, $ip_parts[1]);
				$valid_high = self::is_valid($ip_parts[1]);
				while (!$valid_high)
				{
					$ip_parts[1] .= $mode . $max;
					$valid_high = self::is_valid($ip_parts[1]);
					$count++;
					if ($count > 9) break;
				}
			}

			if($valid_high && $valid_low)
			{
				$ip_array['low'] = $ip_parts[0];
				$ip_array['high'] = $ip_parts[1];
			}

		}

		return $ip_array;
	}

	/**
	 * Convert a range of given IP number into a single string.
	 * It's practically the reverse function of to_range().
	 *
	 * @example
	 * IP::from_range(array(10, 10, 10, 0), array(10, 10, 20, 255)) returns '10.10.10-20.*
	 *
	 * @param array $low The low end of the range in IPv4 format
	 * @param array $high The high end of the range in IPv4 format
	 * @return string A string indicating the range
	 */
	public function from_range($low, $high)
	{
		$low = inet_dtop($low);
		$high = inet_dtop($high);

		if ($low == '255.255.255.255') return 'unknown';
		if ($low == $high)
			return $low;
		else
			return $low . '-' . $high;
	}
}
