<?php

/**
 * This hook runs when characters are removed from a group.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Group;

/**
 * This hook runs when a group loses characters.
 */
class CharactersRemoved extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct(array $characters, array $groups)
	{
		$this->vars = [
			'characters' => $characters,
			'groups' => $groups,
		];
	}
}
