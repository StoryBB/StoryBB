<?php

/**
 * A class for assembling a page and sending it to the user.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Routing;

use StoryBB\Dependency\SiteSettings;
use StoryBB\Dependency\Templater;
use Symfony\Component\HttpFoundation\Response;

/**
 * A class for assembling a page and sending it to the user.
 */
class RenderResponse extends Response
{
	use SiteSettings;
	use Templater;

	public function render(string $template, array $rendercontext = []): Response
	{
		$templater = $this->templater();

		$rendercontext['site_settings'] = $this->sitesettings();

		$this->setContent($templater->renderToString($template, $rendercontext));
		return $this;
	}
}