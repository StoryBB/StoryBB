<?php

/**
 * This file contains all the screens that relate to search engines.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Entry point for this section.
 */
function SearchEngines()
{
	global $context, $txt, $modSettings;

	isAllowedTo('admin_forum');

	loadLanguage('Search');

	$subActions = array(
		'settings' => 'ManageSearchEngineSettings',
	);
	$default = 'settings';

	// Ensure we have a valid subaction.
	$context['sub_action'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : $default;

	$context['page_title'] = $txt['search_engines'];

	// Some more tab data.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['search_engines'],
		'description' => $txt['search_engines_description'],
	);

	call_integration_hook('integrate_manage_search_engines', array(&$subActions));

	// Call the function!
	call_helper($subActions[$context['sub_action']]);
}

/**
 * This is really just the settings page.
 *
 * @param bool $return_config Whether to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ManageSearchEngineSettings($return_config = false)
{
	global $context, $txt, $scripturl, $sourcedir, $smcFunc;

	$config_vars = array(
	);

	call_integration_hook('integrate_modify_search_engine_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// We'll want this for our easy save.
	require_once($sourcedir . '/ManageServer.php');

	// Setup the template.
	$context['page_title'] = $txt['settings'];

	// Are we saving them - are we??
	if (isset($_GET['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_search_engine_settings');
		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=sengines;sa=settings');
	}

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=sengines;save;sa=settings';
	$context['settings_title'] = $txt['settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}
