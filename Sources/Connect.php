<?php

/**
 * Connecting social accounts.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

function Connect()
{
	global $modSettings;

	$connector = isset($_GET['connector']) ? $_GET['connector'] : '';
	$known_connectors = [
		'tumblr' => '\\StoryBB\\AccountConnector\\Provider\\Tumblr',
	];

	if (!isset($known_connectors[$connector]))
	{
		die('Unknown connector');
	}

	$provider_settings = json_decode($modSettings['provider_' . $connector], true);

	$provider = new $known_connectors[$connector]($provider_settings);

	$provider->evaluate_state();
}
