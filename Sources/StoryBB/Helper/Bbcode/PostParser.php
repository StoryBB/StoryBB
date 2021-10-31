<?php

/**
 * Parse content according to its bbc and smiley content.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Bbcode;

use StoryBB\Container;
use StoryBB\Helper\TLD;
use StoryBB\Hook\Mutatable;
use StoryBB\StringLibrary;

/**
 * Parse content according to its bbc and smiley content.
 */
class PostParser extends AbstractParser
{
	protected $allowed_options = [
		'smileys' => true,
		'author' => true,
		'cache' => true,
	];

	public function __construct()
	{
		global $modSettings;

		$this->allow_smileys = true;
		[$codes, $this->no_autolink_urls] = static::bbcode_definitions();

		$this->bbcode = [];

		$disabled = [];
		if (!empty($modSettings['disabledBBC']))
		{
			$temp = explode(',', strtolower($modSettings['disabledBBC']));
			foreach ($temp as $tag)
			{
				$disabled[trim($tag)] = true;
			}
		}

		foreach ($codes as $code)
		{
			if (!isset($disabled[$code['tag']]))
			{
				$this->bbcode[] = $code;
			}
		}
	}
}
