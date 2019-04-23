<?php

/**
 * A library for setting up autocompletes.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Helper;

use Exception;

/**
 * A helper to make setting up those pesky autocompletes easier.
 */
class Autocomplete
{
	/**
	 * Set up a new instance of an autocomplete.
	 *
	 * @param string $type Type of autocomplete, e.g. 'member'
	 * @param string $target CSS/jQuery selector to target, e.g. '#to'
	 * @param int $maximum Maximum number of items allowed in the autocomplete
	 */
	public static function init(string $type, string $target, int $maximum = 1, array $default = null)
	{
		$searchTypes = self::get_registered_types();
		if (!isset($searchTypes[$type]))
		{
			throw new Exception('Unknown autocomplete type: ' . $type);
		}

		$autocomplete = new $searchTypes[$type];

		// First, set up the generic stuff.
		loadJavaScriptFile('select2/select2.min.js', ['minimize' => false, 'default_theme' => true], 'select2');
		loadCSSFile('select2.min.css', ['minimize' => false, 'default_theme' => true], 'select2');

		if (!empty($default))
		{
			$autocomplete->set_values($default);
		}
		addInlineJavaScript($autocomplete->get_js($target, $maximum), true);
	}

	/**
	 * 
	 */
	public static function get_registered_types(): array
	{
		$searchTypes = array(
			'member' => 'StoryBB\\Helper\\Autocomplete\\Member',
			'character' => 'StoryBB\\Helper\\Autocomplete\\Character',
			'rawcharacter' => 'StoryBB\\Helper\\Autocomplete\\RawCharacter',
			'group' => 'StoryBB\Helper\\Autocomplete\\Group',
		);

		call_integration_hook('integrate_autocomplete', array(&$searchTypes));
		return $searchTypes;
	}
}
