<?php

/**
 * Generating randomness as needed.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

class Random
{
	/**
	 * Returns a string of random bytes (raw) of the specified length,
	 * using CSPRNG sources.
	 *
	 * @todo Add an exception catcher around it and try to mitigate by calling other sources?
	 * @param int $length Number of bytes to produce
	 * @return string The random string
	 */
	public static function get_random_bytes(int $length = 32): string
	{
		return random_bytes($length);
	}
}
