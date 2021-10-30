<?php

/**
 * Generic admin settings page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin\Configuration;

use StoryBB\App;
use StoryBB\Container;
use StoryBB\Phrase;
use StoryBB\Controller\Administrative;
use StoryBB\Controller\Admin\AbstractSettingsPageController;
use StoryBB\Controller\MaintenanceAccessible;
use StoryBB\Dependency\TemplateRenderer;
use StoryBB\Routing\RenderResponse;
use StoryBB\Form\Element;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Response;

class GeneralConfiguration extends AbstractSettingsPageController implements Administrative, MaintenanceAccessible
{
	const BASE_ROUTE = 'configuration';

	public function get_title(): Phrase
	{
		return new Phrase('Admin:general_configuration');
	}

	public function define_settings(): array
	{
		return [
			'Admin:general_configuration' => [
				'forum_name' => [Element\Text::class, 'required' => true],
				'allow_guestAccess' => [Element\Checkbox::class],
			],
		];
	}
}