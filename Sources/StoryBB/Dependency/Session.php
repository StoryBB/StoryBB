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
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;

trait Session
{
	protected $_db;

	public function _accept_session($session) : void
	{
		$this->_session = $session;
	}

	protected function session() : SymfonySession
	{
		if (empty($this->_session))
		{
			throw new RuntimeException('Session not initialised');
		}
		return $this->_session;
	}
}
