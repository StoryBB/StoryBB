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
use StoryBB\User\CurrentUser as ConcreteClass;

trait CurrentUser
{
	protected $_currentuser;

	public function _accept_currentuser(ConcreteClass $_currentuser) : void
	{
		$this->_currentuser = $_currentuser;
	}

	protected function currentuser() : ConcreteClass
	{
		if (empty($this->_currentuser))
		{
			throw new RuntimeException('Current user not initialised');
		}
		return $this->_currentuser;
	}
}
