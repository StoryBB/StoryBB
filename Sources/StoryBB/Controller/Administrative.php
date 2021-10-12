<?php

/**
 * Base interface for any controller that indicates it can be called in maintenance mode.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use StoryBB\Discoverable;
use Symfony\Component\Routing\RouteCollection;

/**
 * Any admin controllers handlers must implement this interface.
 */
interface Administrative extends Discoverable
{
	public static function register_own_routes(RouteCollection $routes): void;
}
