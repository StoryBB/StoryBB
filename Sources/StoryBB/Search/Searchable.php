<?php

/**
 * Reflects a type of content that can be searched.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search;

interface Searchable
{
	public static function get_content_type(): string;

	public static function is_available(): bool;
}
