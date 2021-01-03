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
use Symfony\Component\HttpFoundation\Request;

trait RequestVars
{
	protected $_requestvars = null;

	public function _accept_requestvars(Request $request) : void
	{
		$this->_requestvars = $request;
	}

	protected function requestvars() : Request
	{
		if (!isset($this->_requestvars))
		{
			throw new RuntimeException('Request not initialised');
		}
		return $this->_requestvars;
	}
}
