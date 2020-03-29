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
use Symfony\Component\Routing\Generator\UrlGenerator as SymfonyUrlGenerator;
use StoryBB\Database\DatabaseAdapter;

trait UrlGenerator
{
	protected $_urlgenerator;

	public function _accept_urlgenerator(SymfonyUrlGenerator $urlgenerator) : void
	{
		$this->_urlgenerator = $urlgenerator;
	}

	protected function urlgenerator() : SymfonyUrlGenerator
	{
		if (empty($this->_urlgenerator))
		{
			throw new RuntimeException('URL generator not initialised');
		}
		return $this->_urlgenerator;
	}
}
