<?php

/**
 * This hook runs whenever the list of bbcode is gathered.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Mutatable\BBCode;

/**
 * This hook runs early in the page to identify the user from their cookie.
 */
class Listing extends \StoryBB\Hook\Mutatable
{
	protected $vars = [];

	public function __construct(array &$codes, array &$no_autolink_tags)
	{
		$this->vars = [
			'codes' => &$codes,
			'no_autolink_tags' => $no_autolink_tags,
		];
	}
}
