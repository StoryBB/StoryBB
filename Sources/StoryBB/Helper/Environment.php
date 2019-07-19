<?php

/**
 * Support functions for the system environment.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

/**
 * Support functions for the system environment.
 */
class Environment
{
	/**
	 * Helper function to set the system memory to a needed value
	 * - If the needed memory is greater than current, will attempt to get more
	 * - if in_use is set to true, will also try to take the current memory usage in to account
	 *
	 * @param string $needed The amount of memory to request, if needed, like 256M
	 * @param bool $in_use Set to true to account for current memory usage of the script
	 * @return boolean True if we have at least the needed memory
	 */
	public static function setMemoryLimit(string $needed, bool $in_use = false): bool
	{
		// Everything converted to bytes.
		$memory_current = self::memoryReturnBytes(ini_get('memory_limit'));
		$memory_needed = self::memoryReturnBytes($needed);

		// Should we account for how much is currently being used?
		if ($in_use)
			$memory_needed += function_exists('memory_get_usage') ? memory_get_usage() : (2 * 1048576);

		// If more is needed, request it.
		if ($memory_current < $memory_needed)
		{
			@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
			$memory_current = self::memoryReturnBytes(ini_get('memory_limit'));
		}

		$memory_current = max($memory_current, self::memoryReturnBytes(get_cfg_var('memory_limit')));

		// Return if successful.
		return (bool) ($memory_current >= $memory_needed);
	}

	/**
	 * Helper function to convert memory string settings to bytes
	 *
	 * @param string $val The byte string, like 256M or 1G
	 * @return integer The string converted to a proper integer in bytes
	 */
	public static function memoryReturnBytes($val)
	{
		if (is_integer($val))
			return $val;

		// Separate the number from the designator
		$val = trim($val);
		$num = intval(substr($val, 0, strlen($val) - 1));
		$last = strtolower(substr($val, -1));

		// convert to bytes
		switch ($last)
		{
			case 'g':
				$num *= 1024;
			case 'm':
				$num *= 1024;
			case 'k':
				$num *= 1024;
		}
		return $num;
	}
}
