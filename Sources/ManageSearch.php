<?php

/**
 * The admin screen to change the search settings.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\ClassManager;
use StoryBB\Container;

/**
 * Main entry point for the admin search settings screen.
 * It checks permissions, and it forwards to the appropriate function based on
 * the given sub-action.
 * Defaults to sub-action 'settings'.
 * Called by ?action=admin;area=managesearch.
 * Requires the admin_forum permission.
 *
 * @uses ManageSearch template.
 * @uses Search language file.
 */
function ManageSearch()
{
	global $context, $txt;

	isAllowedTo('admin_forum');

	loadLanguage('Search');

	$subActions = [
		'settings' => 'EditSearchSettings',
		'method' => 'EditSearchMethod',
		'createfulltext' => 'EditSearchMethod',
		'removefulltext' => 'EditSearchMethod',
	];

	// Default the sub-action to 'edit search settings'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'method';

	$context['sub_action'] = $_REQUEST['sa'];

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['manage_search'],
		'help' => '',
		'description' => $txt['search_settings_desc'],
		'tabs' => [
			'method' => [
				'description' => $txt['search_method_desc'],
			],
			'settings' => [
				'description' => $txt['search_settings_desc'],
			],
		],
	];

	routing_integration_hook('integrate_manage_search', [&$subActions]);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Edit some general settings related to the search function.
 * Called by ?action=admin;area=managesearch;sa=settings.
 * Requires the admin_forum permission.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 * @uses ManageSearch template, 'modify_settings' sub-template.
 */
function EditSearchSettings($return_config = false)
{
	global $txt, $context, $scripturl, $sourcedir, $modSettings;

	// What are we editing anyway?
	$config_vars = [
			// Some simple settings.
			['int', 'search_results_per_page'],
			['int', 'search_max_results', 'subtext' => $txt['search_max_results_disable']],
		'',
			// Some limitations.
			['int', 'search_floodcontrol_time', 'subtext' => $txt['search_floodcontrol_time_desc'], 6, 'postinput' => $txt['seconds']],
	];

	call_integration_hook('integrate_modify_search_settings', [&$config_vars]);

	// Perhaps the search method wants to add some settings?
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if (is_callable([$searchAPI, 'searchSettings']))
		call_user_func_array([$searchAPI, 'searchSettings'], [&$config_vars]);

	if ($return_config)
		return [$txt['search_settings_title'], $config_vars];

	$context['page_title'] = $txt['search_settings_title'];

	// We'll need this for the settings.
	require_once($sourcedir . '/ManageServer.php');

	// A form was submitted.
	if (isset($_REQUEST['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_search_settings');

		if (empty($_POST['search_results_per_page']))
			$_POST['search_results_per_page'] = !empty($modSettings['search_results_per_page']) ? $modSettings['search_results_per_page'] : $modSettings['defaultMaxMessages'];
		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=managesearch;sa=settings;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Prep the template!
	$context['post_url'] = $scripturl . '?action=admin;area=managesearch;save;sa=settings';
	$context['settings_title'] = $txt['search_settings_title'];

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

/**
 * Edit the search method and search index used.
 * Calculates the size of the current search indexes in use.
 * Allows to create and delete a fulltext index on the messages table.
 * Called by ?action=admin;area=managesearch;sa=method.
 * Requires the admin_forum permission.
 *
 * @uses ManageSearch template, 'select_search_method' sub-template.
 */
function EditSearchMethod()
{
	global $txt, $context, $modSettings, $smcFunc, $db_type, $db_prefix;

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_method_title'];
	$context['sub_template'] = 'search_select_method';
	$context['supports_fulltext'] = $smcFunc['db']->search_support('fulltext');

	// Load any apis.
	$context['search_apis'] = loadSearchAPIs();

	// Detect whether a fulltext index is set.
	if ($context['supports_fulltext'])
		detectFulltextIndex();

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removefulltext' && !empty($context['fulltext_index']))
	{
		checkSession('get');
		validateToken('admin-msm', 'get');

		$smcFunc['db']->query('', '
			ALTER TABLE {db_prefix}messages
			DROP INDEX ' . implode(',
			DROP INDEX ', $context['fulltext_index']),
			[
				'db_error_skip' => true,
			]
		);

		$context['fulltext_index'] = [];

		// Go back to the default search method.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext')
			updateSettings([
				'search_index' => '',
			]);
	}
	elseif (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-msmpost');

		updateSettings([
			'search_index' => empty($_POST['search_index']) || (!in_array($_POST['search_index'], ['fulltext']) && !isset($context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
			'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
			'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
		]);
	}

	$context['table_info'] = [
		'data_length' => 0,
		'index_length' => 0,
		'fulltext_length' => 0,
	];

	// Get some info about the messages table, to show its size and index size.
	if ($db_type == 'mysql')
	{
		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
			$request = $smcFunc['db']->query('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				[
					'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
				]
			);
		else
			$request = $smcFunc['db']->query('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				[
					'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
				]
			);
		if ($request !== false && $smcFunc['db']->num_rows($request) == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $smcFunc['db']->fetch_assoc($request);
			$context['table_info']['data_length'] = $row['Data_length'];
			$context['table_info']['index_length'] = $row['Index_length'];
			$context['table_info']['fulltext_length'] = $row['Index_length'];
			$smcFunc['db']->free_result($request);
		}
	}
	else
		$context['table_info'] = [
			'data_length' => $txt['not_applicable'],
			'index_length' => $txt['not_applicable'],
			'fulltext_length' => $txt['not_applicable'],
		];

	// Format the data and index length in kilobytes.
	foreach ($context['table_info'] as $type => $size)
	{
		// If it's not numeric then just break.  This database engine doesn't support size.
		if (!is_numeric($size))
			break;

		$context['table_info'][$type] = comma_format($context['table_info'][$type] / 1024) . ' ' . $txt['search_method_kilobytes'];
	}

	createToken('admin-msmpost');
	createToken('admin-msm', 'get');
}

/**
 * Get the installed Search API implementations.
 * This function checks for patterns in comments on top of the Search-API files!
 * In addition to filenames pattern.
 * It loads the search API classes if identified.
 * This function is used by EditSearchMethod to list all installed API implementations.
 */
function loadSearchAPIs()
{
	$backends = [];
	foreach (ClassManager::get_classes_implementing('StoryBB\\Search\\Searchable') as $class)
	{
		$instance = new $class;
		if (!$instance->is_supported)
		{
			continue;
		}
		$backend = substr(strrchr($class, '\\'), 1);
		$backend_name = strtolower($backend);
		$backends[$backend_name] = [
			'setting_index' => $backend_name,
			'instance' => $instance,
			'has_template' => $instance->has_template,
			'label' => $instance->getName(),
			'desc' => $instance->getDescription(),
		];
	}

	return $backends;
}

/**
 * Checks if the message table already has a fulltext index created and returns the key name
 * Determines if a db is capable of creating a fulltext index
 */
function detectFulltextIndex()
{
	global $smcFunc, $context, $db_prefix;

	$request = $smcFunc['db']->query('', '
		SHOW INDEX
		FROM {db_prefix}messages',
		[
		]
	);
	$context['fulltext_index'] = [];
	if ($request !== false || $smcFunc['db']->num_rows($request) != 0)
	{
		while ($row = $smcFunc['db']->fetch_assoc($request))
		if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT'))
			$context['fulltext_index'][] = $row['Key_name'];
		$smcFunc['db']->free_result($request);

		if (is_array($context['fulltext_index']))
			$context['fulltext_index'] = array_unique($context['fulltext_index']);
	}

	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		$request = $smcFunc['db']->query('', '
		SHOW TABLE STATUS
		FROM {string:database_name}
		LIKE {string:table_name}',
		[
			'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
			'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
		]
		);
	else
		$request = $smcFunc['db']->query('', '
		SHOW TABLE STATUS
		LIKE {string:table_name}',
		[
			'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
		]
		);

	if ($request !== false)
	{
		while ($row = $smcFunc['db']->fetch_assoc($request))
		if (isset($row['Engine']) && strtolower($row['Engine']) != 'myisam' && !(strtolower($row['Engine']) == 'innodb' && version_compare($smcFunc['db']->get_version(), '5.6.4', '>=')))
			$context['cannot_create_fulltext'] = true;
		$smcFunc['db']->free_result($request);
	}
}
