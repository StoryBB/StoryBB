<?php

/**
 * A class for serving smileys.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Dependency;

use RuntimeException;
use StoryBB\Helper\Filesystem as StoryBB_Filesystem;

trait Filesystem
{
	protected $_fs;

	public function _accept_filesystem(StoryBB_Filesystem $fs) : void
	{
		$this->_fs = $fs;
	}

	protected function filesystem() : StoryBB_Filesystem
	{
		if (empty($this->_fs))
		{
			throw new RuntimeException('Filesystem not initialised');
		}
		return $this->_fs;
	}
}
