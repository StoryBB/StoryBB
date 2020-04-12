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
use Latte\Engine;

trait Templater
{
	protected $_templater;

	public function _accept_templater(Engine $templater) : void
	{
		$this->_templater = $templater;
	}

	protected function templater() : Engine
	{
		if (empty($this->_templater))
		{
			throw new RuntimeException('Templater not initialised');
		}
		return $this->_templater;
	}
}
