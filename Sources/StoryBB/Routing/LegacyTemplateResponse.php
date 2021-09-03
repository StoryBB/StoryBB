<?php

/**
 * A class for assembling a page and sending it to the user.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Routing;

use StoryBB\Dependency\Page;
use StoryBB\Dependency\SiteSettings;
use StoryBB\Dependency\TemplateRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * A class for assembling a page and sending it to the user.
 */
class LegacyTemplateResponse extends Response
{
	use Page;
	use SiteSettings;
	use TemplateRenderer;

	public function render(string $template, array $rendercontext = []): Response
	{
		global $sourcedir, $context, $settings, $boardurl, $scripturl;

		$templater = $this->templaterenderer();

		$rendercontext['page'] = $this->page();
		$rendercontext['site_settings'] = $this->sitesettings();

		require_once($sourcedir . '/Errors.php');
		require_once($sourcedir . '/Logging.php');
		require_once($sourcedir . '/Security.php');

		$scripturl = $boardurl . '/index.php';
		$context['current_action'] = 'legacyrenderresponse';
		$context['sub_template'] = 'legacycontent';

		reloadSettings();
		frameOptionsHeader();
		loadUserSettings();
		loadBoard();
		loadPermissions();
		loadTheme();

		$context['legacycontent'] = ($templater->load($template))->render($rendercontext);
		obExit(null, null, false);
	}
}
