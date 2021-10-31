<?php

/**
 * Any controller (routable) handlers must implement this interface.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Routing\Behaviours;

use StoryBB\Discoverable;
use Symfony\Component\Routing\RouteCollection;

/**
 * Any controllers handlers must implement this interface.
 */
interface Routable extends Discoverable
{
	public static function register_own_routes(RouteCollection $routes): void;
}
