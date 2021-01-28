<?php

/**
 * This hook runs whenever the sidebar menu is populated.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Mutatable;

/**
 * This hook runs early in the page to identify the user from their cookie.
 */
class SidebarMenu extends \StoryBB\Hook\Mutatable
{
	protected $vars = [];

	public function __construct(array &$sidebar)
	{
		$this->vars = [
			'sidebar' => &$sidebar,
		];
	}
}
