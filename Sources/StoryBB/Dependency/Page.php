<?php

/**
 * A class for serving smileys.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Dependency;

use RuntimeException;
use StoryBB\Page as StoryBB_Page;

trait Page
{
	protected $_page;

	public function _accept_page(StoryBB_Page $page) : void
	{
		$this->_page = $page;
	}

	protected function page() : StoryBB_Page
	{
		if (empty($this->_page))
		{
			throw new RuntimeException('Page not initialised');
		}
		return $this->_page;
	}
}
