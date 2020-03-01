<?php

/**
 * This hook runs when an account password is reset.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Account;

/**
 * This hook runs when an account password is reset.
 */
class PasswordReset extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct($old_user, $user, $new_password)
	{
		$this->vars = [
			'old_user' => $old_user,
			'user' => $user,
			'new_password' => $new_password,
		];
	}
}
