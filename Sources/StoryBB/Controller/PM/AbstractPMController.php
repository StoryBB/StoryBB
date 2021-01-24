<?php

/**
 * Abstract PM controller (hybrid style)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\PM;

use StoryBB\Helper\Navigation\Navigation;

abstract class AbstractPMController
{
	protected $navigation;
	protected $params;

	public function __construct(Navigation $nav, array $params)
	{
		$this->navigation = $nav;
		$this->params = $params;
	}

	abstract public function display_action();
}
