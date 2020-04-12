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
use StoryBB\Helper\SiteSettings as StoryBB_SiteSettings;

trait SiteSettings
{
	protected $_sitesettings;

	public function _accept_sitesettings(StoryBB_SiteSettings $sitesettings) : void
	{
		$this->_sitesettings = $sitesettings;
	}

	protected function sitesettings() : StoryBB_SiteSettings
	{
		if (empty($this->_sitesettings))
		{
			throw new RuntimeException('Settings not initialised');
		}
		return $this->_sitesettings;
	}
}
