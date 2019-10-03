<?php

/**
 * This hook runs when one or more groups are deleted.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Group;

/**
 * This hook runs when one or more groups are deleted.
 */
class Deleted extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct(array $groups)
	{
		$this->vars = [
			'groups' => $groups,
		];
	}
}
