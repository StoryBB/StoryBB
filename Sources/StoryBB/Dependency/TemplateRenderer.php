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
use Twig\Environment as TwigEnvironment;

trait TemplateRenderer
{
	protected $_templaterenderer;

	public function _accept_templaterenderer(TwigEnvironment $templater) : void
	{
		$this->_templaterenderer = $templater;
	}

	protected function templaterenderer() : TwigEnvironment
	{
		if (empty($this->_templaterenderer))
		{
			throw new RuntimeException('Templater not initialised');
		}
		return $this->_templaterenderer;
	}
}
