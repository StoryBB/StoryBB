<?php

/**
 * This class provides miscellaneous helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

/**
 * This class provides miscellaneous helpers for StoryBB's templates.
 */
class Misc
{
	/**
	 * List the different helpers available in this class.
	 * @return array Helpers, assocating name to method
	 */
	public static function _list()
	{
		return ([
			'timeformat' => 'StoryBB\\Template\\Helper\\Misc::timeformat',
			'comma' => 'StoryBB\\Template\\Helper\\Misc::comma',
			'dynamicpartial' => 'StoryBB\\Template\\Helper\\Misc::dynamicpartial',
			'json' => 'StoryBB\\Template\\Helper\\Misc::json',
			'breakRow' => 'StoryBB\\Template\\Helper\\Misc::breakrow',
			'is_numeric' => 'StoryBB\\Template\\Helper\\Misc::is_numeric',
		]);
	}

	/**
	 * Formats a timestamp according to user preferences
	 * @param int $timestamp The timestamp to output (Unix epoch etc)
	 * @return string The time suitably formatted
	 */
	public static function timeformat($timestamp)
	{
		return timeformat($timestamp); // See Subs.php
	}

	/**
	 * Accepts a number and converts it to include commas according to current locale settings.
	 * @param int|float $number A number to output
	 * @return string The number formatted with thousands and decimals separators
	 */
	public static function comma($number)
	{
		return comma_format($number); // See Subs.php
	}

	/**
	 * Loads and renders a partial by way of the name of the partial being supplied
	 * @param string $partial A partial to load (can be a dynamic expression result)
	 * @return $string The rendered partial
	 */
	public static function dynamicpartial($partial)
	{
		global $context, $txt, $scripturl, $settings, $modSettings, $options;
		$template = \StoryBB\Template::load_partial($partial);
		$phpStr = \StoryBB\Template::compile($template, [], 'dynamicpartial-' . $settings['theme_id'] . '-' . $partial);
		return \StoryBB\Template::prepare($phpStr, [
			'context' => $context,
			'txt' => $txt,
			'scripturl' => $scripturl,
			'settings' => $settings,
			'modSettings' => $modSettings,
			'options' => $options,
		]);
	}

	/**
	 * Exports arbitrary data in JSON format for templates
	 * @param mixed $data The data to export
	 * @return string JSON to be exported
	 */
	public static function json($data)
	{
		return json_encode($data instanceof \LightnCandy\SafeString ? (string) $data : $data);
	}

	/**
	 * Issues a separator to break a row after a number of items.
	 * @param int $index The current index from the loop of items
	 * @param int $perRow The number of items per row
	 * @param string $sep The separator HTML between rows
	 * @return string HTML, conditionally the separator if we're correctly between rows
	 */
	public static function breakrow($index, $perRow, $sep)
	{
		$perRow = (int) $perRow;
		if ($perRow == 0) {
			return '';
		}
		if ($index > 0 && $index % $perRow == 0) return $sep;
		return '';
	}

	/**
	 * Checks if a variable is numeric for template purposes.
	 * @param mixed $x Variable to check
	 * @return bool True if $x is numeric
	 */
	public static function is_numeric($x)
	{
		return is_numeric($x);
	}
}
