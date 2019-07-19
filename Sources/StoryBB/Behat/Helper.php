<?php

/**
 * This class handles behaviours for Behat tests within StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Behat;

/**
 * This class handles behaviours for Behat tests within StoryBB.
 */
class Helper
{
	/**
	 * @var Local instance of XPath escaper class.
	 */
	protected static $escaper;

	/**
	 * Provides escaped version of a string as an XPath literal.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped string
	 */
	public static function escape(string $text): string
	{
		if (empty(self::$escaper)) {
			self::$escaper = new \Behat\Mink\Selector\Xpath\Escaper();
		}
		return self::$escaper->escapeLiteral($label);
	}
}
