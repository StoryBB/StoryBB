<?php

/**
 * Represents a language string.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

class Phrase
{
	protected $id = '';

	protected static $cache = [];

	public function __construct(string $string)
	{
		$this->id = $string;
	}

	public function __toString(): string
	{
		global $txt;

		$language = App::get_global_config_item('language');
		$split = explode(':', $this->id, 3);

		switch (count($split))
		{
			case 1:
				$lang = $language;
				$langsection = 'General';
				$string = $split[0];
				break;

			case 2:
				$lang = $language;
				[$langsection, $string] = $split;
				break;

			case 3:
				[$lang, $langsection, $string] = $split;
				break;
		}

		// Dirty hack to get just the parts we care about.
		$oldTxt = $txt;
		$txt = [];

		// Having switched out the language file, get the file we asked for (including English fallback) and store that in a local cache.
		if (!isset(static::$cache[$lang][$langsection]))
		{
			if (!function_exists('loadLanguage'))
			{
				require_once(App::get_sources_path() . '/Load.php');
			}
			loadLanguage($langsection, $lang, false, true);
			static::$cache[$lang][$langsection] = $txt;
		}

		$txt = $oldTxt;

		return static::$cache[$lang][$langsection][$string] ?? '[[' . $lang . ':' . $langsection . ':' . $string . ']]';
	}
}
