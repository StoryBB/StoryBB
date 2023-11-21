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

use StoryBB\App;
use RuntimeException;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use StoryBB\Routing\Exception\SessionTimeoutException;

trait Session
{
	protected $_session;

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

	protected function check_session(string $sessvar, string $sessid): bool
	{
		return ($this->_session->get('session_var') === $sessvar) && ($this->_session->get('session_value') === $sessid);
	}

	protected function assert_session(string $sessvar, string $sessid): void
	{
		if (!$this->check_session($sessvar, $sessid))
		{
			throw new SessionTimeoutException;
		}
	}

	protected function assert_session_from_post(): void
	{
		$request = App::container()->get('requestvars');
		$session_var = $this->_session->get('session_var');
		$session_id = $this->_session->get('session_value');

		if (!$request->request->has($session_var) || $request->request->get($session_var) !== $session_id)
		{
			throw new SessionTimeoutException;
		}
	}
}
