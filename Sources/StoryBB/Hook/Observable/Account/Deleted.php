<?php

/**
 * This hook runs when a post is created.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Account;

/**
 * This hook runs when a post is created.
 */
class Deleted extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct(array $account_ids)
	{
		$this->vars = [
			'account_ids' => $account_ids,
		];
	}
}
