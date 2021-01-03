<?php

/**
 * A base class for handling serving files.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use StoryBB\ClassManager;
use Symfony\Component\Routing\RouteCollection;

class File implements Routable
{
	public static function register_own_routes(RouteCollection $routes): void
	{
		foreach (ClassManager::get_classes_implementing('StoryBB\\FileHandler\\Servable') as $servable)
		{
			$servable::register_route($routes);
		}
	}
}
