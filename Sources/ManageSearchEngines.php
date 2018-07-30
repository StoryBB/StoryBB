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
		'logs' => 'SpiderLogs',
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
		recacheSpiderNames();
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=sengines;sa=settings');
	}

	// Final settings...
	$context['post_url'] = $scripturl . '?action=admin;area=sengines;save;sa=settings';
	$context['settings_title'] = $txt['settings'];

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

/**
 * This function takes any unprocessed hits and turns them into stats.
 */
function consolidateSpiderStats()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('consolidate_spider_stats', '
		SELECT id_spider, MAX(log_time) AS last_seen, COUNT(*) AS num_hits
		FROM {db_prefix}log_spider_hits
		WHERE processed = {int:not_processed}
		GROUP BY id_spider, MONTH(log_time), DAYOFMONTH(log_time)',
		array(
			'not_processed' => 0,
		)
	);
	$spider_hits = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$spider_hits[] = $row;
	$smcFunc['db_free_result']($request);

	if (empty($spider_hits))
		return;

	// Attempt to update the master data.
	$stat_inserts = array();
	foreach ($spider_hits as $stat)
	{
		// We assume the max date is within the right day.
		$date = strftime('%Y-%m-%d', $stat['last_seen']);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_spider_stats
			SET page_hits = page_hits + {int:hits},
				last_seen = CASE WHEN last_seen > {int:last_seen} THEN last_seen ELSE {int:last_seen} END
			WHERE id_spider = {int:current_spider}
				AND stat_date = {date:last_seen_date}',
			array(
				'last_seen_date' => $date,
				'last_seen' => $stat['last_seen'],
				'current_spider' => $stat['id_spider'],
				'hits' => $stat['num_hits'],
			)
		);
		if ($smcFunc['db_affected_rows']() == 0)
			$stat_inserts[] = array($date, $stat['id_spider'], $stat['num_hits'], $stat['last_seen']);
	}

	// New stats?
	if (!empty($stat_inserts))
		$smcFunc['db_insert']('ignore',
			'{db_prefix}log_spider_stats',
			array('stat_date' => 'date', 'id_spider' => 'int', 'page_hits' => 'int', 'last_seen' => 'int'),
			$stat_inserts,
			array('stat_date', 'id_spider')
		);

	// All processed.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_spider_hits
		SET processed = {int:is_processed}
		WHERE processed = {int:not_processed}',
		array(
			'is_processed' => 1,
			'not_processed' => 0,
		)
	);
}

/**
 * See what spiders have been up to.
 */
function SpiderLogs()
{
	global $context, $txt, $sourcedir, $scripturl, $smcFunc, $modSettings;

	// Load the template and language just incase.
	loadLanguage('Search');
	$context['spider_logs_delete_confirm'] = addcslashes($txt['spider_logs_delete_confirm'], "'");

	// Did they want to delete some entries?
	if ((!empty($_POST['delete_entries']) && isset($_POST['older'])) || !empty($_POST['removeAll']))
	{
		checkSession();
		validateToken('admin-sl');

		if (!empty($_POST['delete_entries']) && isset($_POST['older']))
		{
			$deleteTime = time() - (((int) $_POST['older']) * 24 * 60 * 60);

			// Delete the entires.
			$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_spider_hits
			WHERE log_time < {int:delete_period}',
				array(
					'delete_period' => $deleteTime,
				)
			);
		}
		else
		{
			// Deleting all of them
			$smcFunc['db_query']('', '
			TRUNCATE TABLE {db_prefix}log_spider_hits',
				array()
			);
		}
	}

	$listOptions = array(
		'id' => 'spider_logs',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'title' => $txt['spider_logs'],
		'no_items_label' => $txt['spider_logs_empty'],
		'base_href' => $context['admin_area'] == 'sengines' ? $scripturl . '?action=admin;area=sengines;sa=logs' : $scripturl . '?action=admin;area=logs;sa=spiderlog',
		'default_sort_col' => 'log_time',
		'get_items' => array(
			'function' => 'list_getSpiderLogs',
		),
		'get_count' => array(
			'function' => 'list_getNumSpiderLogs',
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['spider'],
				),
				'data' => array(
					'db' => 'spider_name',
				),
				'sort' => array(
					'default' => 's.spider_name',
					'reverse' => 's.spider_name DESC',
				),
			),
			'log_time' => array(
				'header' => array(
					'value' => $txt['spider_time'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						return timeformat($rowData['log_time']);
					},
				),
				'sort' => array(
					'default' => 'sl.id_hit DESC',
					'reverse' => 'sl.id_hit',
				),
			),
			'viewing' => array(
				'header' => array(
					'value' => $txt['spider_viewing'],
				),
				'data' => array(
					'db' => 'url',
				),
			),
		),
		'form' => array(
			'token' => 'admin-sl',
			'href' => $scripturl . '?action=admin;area=sengines;sa=logs',
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['spider_logs_info'],
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="removeAll" value="' . $txt['spider_log_empty_log'] . '" data-confirm="' . $txt['spider_log_empty_log_confirm'] . '" class="button_submit you_sure">',
			),
		),
	);

	createToken('admin-sl');

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// Now determine the actions of the URLs.
	if (!empty($context['spider_logs']['rows']))
	{
		$urls = array();

		// Grab the current /url.
		foreach ($context['spider_logs']['rows'] as $k => $row)
		{
			// Feature disabled?
			if (empty($row['data']['viewing']['value']))
				$context['spider_logs']['rows'][$k]['viewing']['value'] = '<em>' . $txt['spider_disabled'] . '</em>';
			else
				$urls[$k] = array($row['data']['viewing']['value'], -1);
		}

		// Now stick in the new URLs.
		require_once($sourcedir . '/Who.php');
		$urls = determineActions($urls, 'whospider_');
		foreach ($urls as $k => $new_url)
		{
			$context['spider_logs']['rows'][$k]['data']['viewing']['value'] = $new_url;
		}
	}

	$context['page_title'] = $txt['spider_logs'];
	$context['sub_template'] = 'admin_spider_logs';
}

/**
 * Callback function for createList()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array An array of spider log data
 */
function list_getSpiderLogs($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT sl.id_spider, sl.url, sl.log_time, s.spider_name
		FROM {db_prefix}log_spider_hits AS sl
			INNER JOIN {db_prefix}spiders AS s ON (s.id_spider = sl.id_spider)
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items}',
		array(
			'sort' => $sort,
			'start' => $start,
			'items' => $items_per_page,
		)
	);
	$spider_logs = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$spider_logs[] = $row;
	$smcFunc['db_free_result']($request);

	return $spider_logs;
}

/**
 * Callback function for createList()
 * @return int The number of spider log entries
 */
function list_getNumSpiderLogs()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS num_logs
		FROM {db_prefix}log_spider_hits',
		array(
		)
	);
	list ($numLogs) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $numLogs;
}

/**
 * Recache spider names?
 */
function recacheSpiderNames()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_spider, spider_name
		FROM {db_prefix}spiders',
		array()
	);
	$spiders = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$spiders[$row['id_spider']] = $row['spider_name'];
	$smcFunc['db_free_result']($request);

	updateSettings(array('spider_name_cache' => json_encode($spiders)));
}
