<?php

/**
 * This hook runs when an account is created.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Observable\Account;

/**
 * This hook runs when an account is created.
 */
class Created extends \StoryBB\Hook\Observable
{
	protected $vars = [];

	public function __construct($memberID, array $regOptions)
	{
		$this->vars = [
			'memberID' => $memberID,
			'regOptions' => $regOptions,
		];
	}
}
