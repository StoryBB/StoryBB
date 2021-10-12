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
use Symfony\Component\Routing\Generator\UrlGenerator as SymfonyUrlGenerator;

trait AdminUrlGenerator
{
	protected $_adminurlgenerator;

	public function _accept_adminurlgenerator(SymfonyUrlGenerator $urlgenerator) : void
	{
		$this->_adminurlgenerator = $urlgenerator;
	}

	protected function adminurlgenerator() : SymfonyUrlGenerator
	{
		if (empty($this->_adminurlgenerator))
		{
			throw new RuntimeException('AdminURL generator not initialised');
		}
		return $this->_adminurlgenerator;
	}
}
