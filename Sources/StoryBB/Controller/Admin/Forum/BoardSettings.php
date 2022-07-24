<?php

/**
 * Forum board settings.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin\Forum;

use StoryBB\App;
use StoryBB\Container;
use StoryBB\Phrase;
use StoryBB\Controller\Admin\AbstractSettingsPageController;
use StoryBB\Routing\Behaviours\Administrative;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Dependency\TemplateRenderer;
use StoryBB\Routing\RenderResponse;
use StoryBB\Form\Element;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Response;

class BoardSettings extends AbstractSettingsPageController implements Administrative, MaintenanceAccessible
{
	const BASE_ROUTE = 'forum/settings';

	public function get_title(): Phrase
	{
		return new Phrase('Admin:board_settings');
	}

	public function define_settings(): array
	{
		return [
			'Admin:board_settings' => [
				'topic_move_any' => [Element\Checkbox::class],
			],
		];
	}
}
