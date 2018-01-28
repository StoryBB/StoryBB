<?php

/**
 * This class provides miscellaneous helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

class Misc
{
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

	public static function timeformat($timestamp)
	{
		return timeformat($timestamp); // See Subs.php
	}

	public static function comma($number)
	{
		return comma_format($number); // See Subs.php
	}

	public static function dynamicpartial($partial) {
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

	public static function json($data)
	{
		return json_encode($data instanceof \LightnCandy\SafeString ? (string) $data : $data);
	}

	public static function breakrow($index, $perRow, $sep) {
		$perRow = (int) $perRow;
		if ($perRow == 0) {
			return '';
		}
		if ($index > 0 && $index % $perRow == 0) return $sep;
		return '';
	}

	public static function is_numeric($x) {
		return is_numeric($x);
	}
}

?>