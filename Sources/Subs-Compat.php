<?php

/**
 * This file provides compatibility functions and code for older versions of
 * PHP, such as the sha1() function, missing extensions, or 64-bit vs 32-bit
 * systems. It is only included for those older versions or when the respective
 * extension or function cannot be found.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Compatibility function.
 * crc32 doesn't work as expected on 64-bit functions - make our own.
 * https://php.net/crc32#79567
 * @param string $number
 * @return string The crc32 polynomial of $number
 */
if (!function_exists('sbb_crc32'))
{
	function sbb_crc32($number)
	{
		$crc = crc32($number);

		if ($crc & 0x80000000)
		{
			$crc ^= 0xffffffff;
			$crc += 1;
			$crc = -$crc;
		}

		return $crc;
	}
}
