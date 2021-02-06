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
use StoryBB\Dependency\Templater;
use Symfony\Component\HttpFoundation\Response;

/**
 * A class for assembling a page and sending it to the user.
 */
class LegacyRenderResponse extends Response
{
	use Page;
	use SiteSettings;
	use Templater;

	public function render(string $template, array $rendercontext = []): Response
	{
		global $sourcedir, $context, $settings, $boardurl, $scripturl;

		$templater = $this->templater();

		$rendercontext['page'] = $this->page();
		$rendercontext['site_settings'] = $this->sitesettings();

		require_once($sourcedir . '/Errors.php');
		require_once($sourcedir . '/Logging.php');
		require_once($sourcedir . '/Security.php');

		reloadSettings();
		frameOptionsHeader();
		loadUserSettings();
		loadBoard();
		loadPermissions();
		loadTheme();

		$scripturl = $boardurl . '/index.php';
		$context['current_action'] = 'legacyrenderresponse';
		$context['sub_template'] = 'legacycontent';

		$context['legacycontent'] = $templater->renderToString($template, $rendercontext);
		obExit(null, null, false);
	}
}
