<?php

/**
 * This file handles autocomplete cases for the Select2 widget.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use StoryBB\App;
use StoryBB\Dependency\RequestVars;
use StoryBB\Dependency\Session;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Routing\Behaviours\Routable;
use StoryBB\Routing\Behaviours\Unloggable;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * This keeps track of all registered handling functions for auto suggest functionality and passes execution to them.
 */
class Autocomplete implements Routable, Unloggable
{
	use RequestVars;
	use Session;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('autocomplete', (new Route('/autocomplete/{type}/{sessvar}/{sessid}', ['_controller' => [static::class, 'execute']])));
	}

	public function execute(string $type, string $sessvar, string $sessid): Response
	{
		$this->assert_session($sessvar, $sessid);

		$response = ['results' => []];
		$request = $this->requestvars();

		$helper = App::container()->get('autocomplete');
		$searchTypes = $helper->get_registered_types();

		$term = trim($request->query->get('term', ''));

		if (isset($searchTypes[$type]) && $term)
		{
			$autocomplete = App::make($searchTypes[$type]);
			$autocomplete->set_search_term($term);
		}

		if ($autocomplete->can_paginate())
		{
			$perpage = 10;
			$start = (((int) $request->query->get('page', 1)) - 1) * $perpage;
			$limit = $perpage;

			$total = $autocomplete->get_count();

			$response = [
				'results' => $autocomplete->get_results($start, $limit),
				'pagination' => [
					'more' => ($start * $perpage + $limit) < $total,
				]
			];
		}
		else
		{
			$response = [
				'results' => $autocomplete->get_results(),
			];
		}

		return new JsonResponse($response);
	}
}
