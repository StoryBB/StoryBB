<?php

/**
 * The admin home page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin;

use StoryBB\Routing\Behaviours\Administrative;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Response;

class Dashboard extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	private $navigation;

	public function requires_permissions(): array
	{
		$this->navigation = $this->get_navigation('dashboard');
		return $this->navigation['configuration']['sections']['overview']['items']['dashboard']['permissions'] ?? ['admin_forum'];
	}

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('dashboard', (new Route('/dashboard', ['_controller' => [static::class, 'get_dashboard']])));
	}

	public function get_dashboard(): Response
	{
		return $this->render('admin/dashboard.twig', 'dashboard', ['navigation' => $this->navigation]);
	}
}
