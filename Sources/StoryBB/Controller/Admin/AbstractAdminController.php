<?php

/**
 * Generic admin controller handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin;

use StoryBB\App;
use StoryBB\Controller\Admin\AdminNavigation;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Dependency\AdminUrlGenerator;
use StoryBB\Dependency\Session;
use StoryBB\Dependency\TemplateRenderer;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractAdminController implements MaintenanceAccessible
{
	use TemplateRenderer;
	use AdminNavigation;
	use AdminUrlGenerator;
	use Session;

	public function requires_permissions(): array
	{
		return ['admin_forum'];
	}

	abstract public static function register_own_routes(RouteCollection $routes): void;

	public function redirect(string $route, array $params = []): Response
	{
		return new RedirectResponse($this->adminurlgenerator()->generate($route, $params));
	}

	public function render(string $template, string $route, array $rendercontext = []): Response
	{
		if (!isset($rendercontext['navigation']))
		{
			$rendercontext['navigation'] = $this->get_navigation($route);
		}

		return (App::make(RenderResponse::class))->render($template, $rendercontext);
	}
}
