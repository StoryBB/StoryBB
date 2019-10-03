<?php

/**
 * This hook runs when an account is activated.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Account;

/**
 * This hook runs when an account logs in.
 */
class Activated extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct($username, $userid)
	{
		$this->vars = [
			'username' => $username,
			'id_member' => $userid,
		];
	}
}
