<?php

/**
 * This file handles autocomplete cases for the Select2 widget.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * This keeps track of all registered handling functions for auto suggest functionality and passes execution to them.
 */
function Autocomplete()
{
	global $context;

	// These are all registered types.
	$searchTypes = array(
		'member' => 'StoryBB\Helper\Autocomplete\Member',
	);

	call_integration_hook('integrate_autocomplete', array(&$searchTypes));

	// Do the minimum setup stuff.
	checkSession('get');
	StoryBB\Template::set_layout('raw');
	StoryBB\Template::remove_all_layers();

	$response = [];
	if (isset($_REQUEST['complete'], $searchTypes[$_REQUEST['complete']], $_REQUEST['term']))
	{
		$autocomplete = new $searchTypes[$_REQUEST['complete']];
		$autocomplete->set_search_term($_REQUEST['term']);

		if ($autocomplete->can_paginate())
		{
			$perpage = 10;
			$start = (isset($_REQUEST['page']) ? (int) $_REQUEST['page'] - 1 : 0) * $perpage;
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
	}

	sbb_serverResponse(json_encode($response));
}
