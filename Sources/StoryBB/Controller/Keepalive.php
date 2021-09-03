<?php

/**
 * The help page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use Exception;
use StoryBB\App;
use StoryBB\Container;
use StoryBB\Controller\MaintenanceAccessible;
use StoryBB\Controller\Unloggable;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\RequestVars;
use StoryBB\Dependency\Session;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\StringLibrary;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Keepalive implements Routable, MaintenanceAccessible, Unloggable
{
	use Session;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('login_keepalive', (new Route('/login/keepalive', ['_controller' => [static::class, 'action_keepalive']])));
	}

	public function action_keepalive(): Response
	{
		return new Response("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B", Response::HTTP_OK, ['content-type' => 'image/gif']);
	}
}
