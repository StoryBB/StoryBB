<?php

/**
 * The help page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Help implements Routable
{
	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('help', new Route('/help', ['_controller' => [static::class, 'help']]));
	}

	/**
	 * @deprecated This is a placeholder for the link system to prove it works at all.
	 */
	public function help(): Response
	{
		global $scripturl;
		return new RedirectResponse($scripturl . '?action=help');
	}
}
