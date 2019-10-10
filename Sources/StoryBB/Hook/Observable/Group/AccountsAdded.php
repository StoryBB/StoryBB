<?php

/**
 * This hook runs when accounts are added to a group.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Group;

/**
 * This hook runs when a group gets new members.
 */
class AccountsAdded extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct(array $members, int $id_group)
	{
		$this->vars = [
			'members' => $members,
			'id_group' => $id_group,
		];
	}
}
