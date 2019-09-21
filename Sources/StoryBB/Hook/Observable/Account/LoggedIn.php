<?php

/**
 * This hook runs when an account logs in.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Account;

/**
 * This hook runs when an account logs in.
 */
class LoggedIn extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct($username, $userid, $login_duration)
	{
		$this->vars = [
			'username' => $username,
			'id_member' => $userid,
			'login_duration' => $login_duration,
		];
	}
}
