<?php

/**
 * This hook runs early in the page to identify the user from their cookie.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Mutatable\Account;

/**
 * This hook runs early in the page to identify the user from their cookie.
 */
class Authenticates extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct(int &$account_id)
	{
		$this->vars = [
			'account_id' => &$account_id,
		];
	}
}
