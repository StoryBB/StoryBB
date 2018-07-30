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

	if (!empty($modSettings['spider_mode']))
	{
		$subActions = array(
			'editspiders' => 'EditSpider',
			'logs' => 'SpiderLogs',
			'settings' => 'ManageSearchEngineSettings',
			'spiders' => 'ViewSpiders',
		);
		$default = 'stats';
	}
	else
	{
		$subActions = array(
			'settings' => 'ManageSearchEngineSettings',
		);
		$default = 'settings';
	}

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
		// How much detail?
		array('select', 'spider_mode', 'subtext' => $txt['spider_mode_note'], array($txt['spider_mode_off'], $txt['spider_mode_standard'], $txt['spider_mode_high'], $txt['spider_mode_vhigh']), 'onchange' => 'disableFields();'),
	);

	// Set up a message.
	$context['settings_message'] = '<span class="smalltext">' . sprintf($txt['spider_settings_desc'], $scripturl . '?action=admin;area=logs;sa=settings;' . $context['session_var'] . '=' . $context['session_id']) . '</span>';

	// Do some javascript.
	$javascript_function = '
		function disableFields()
		{
			disabledState = document.getElementById(\'spider_mode\').value == 0;';

	foreach ($config_vars as $variable)
		if ($variable[1] != 'spider_mode')
			$javascript_function .= '
			if (document.getElementById(\'' . $variable[1] . '\'))
				document.getElementById(\'' . $variable[1] . '\').disabled = disabledState;';

	$javascript_function .= '
		}
		disableFields();';

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
	addInlineJavaScript($javascript_function, true);

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

/**
 * View a list of all the spiders we know about.
 */
function ViewSpiders()
{
	global $context, $txt, $sourcedir, $scripturl, $smcFunc, $modSettings;

	if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60)
	{
		consolidateSpiderStats();
		$_SESSION['spider_stat'] = time();
	}

	// Are we adding a new one?
	if (!empty($_POST['addSpider']))
		return EditSpider();
	// User pressed the 'remove selection button'.
	elseif (!empty($_POST['removeSpiders']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();
		validateToken('admin-ser');

		// Make sure every entry is a proper integer.
		foreach ($_POST['remove'] as $index => $spider_id)
			$_POST['remove'][(int) $index] = (int) $spider_id;

		// Delete them all!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}spiders
			WHERE id_spider IN ({array_int:remove_list})',
			array(
				'remove_list' => $_POST['remove'],
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_spider_hits
			WHERE id_spider IN ({array_int:remove_list})',
			array(
				'remove_list' => $_POST['remove'],
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_spider_stats
			WHERE id_spider IN ({array_int:remove_list})',
			array(
				'remove_list' => $_POST['remove'],
			)
		);

		cache_put_data('spider_search', null, 300);
		recacheSpiderNames();
	}

	// Get the last seens.
	$request = $smcFunc['db_query']('', '
		SELECT id_spider, MAX(last_seen) AS last_seen_time
		FROM {db_prefix}log_spider_stats
		GROUP BY id_spider',
		array(
		)
	);

	$context['spider_last_seen'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['spider_last_seen'][$row['id_spider']] = $row['last_seen_time'];
	$smcFunc['db_free_result']($request);

	createToken('admin-ser');
	$listOptions = array(
		'id' => 'spider_list',
		'title' => $txt['spiders'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=sengines;sa=spiders',
		'default_sort_col' => 'name',
		'get_items' => array(
			'function' => 'list_getSpiders',
		),
		'get_count' => array(
			'function' => 'list_getNumSpiders',
		),
		'no_items_label' => $txt['spiders_no_entries'],
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['spider_name'],
				),
				'data' => array(
					'function' => function($rowData) use ($smcFunc, $scripturl)
					{
						return sprintf('<a href="%1$s?action=admin;area=sengines;sa=editspiders;sid=%2$d">%3$s</a>', $scripturl, $rowData['id_spider'], $smcFunc['htmlspecialchars']($rowData['spider_name']));
					},
				),
				'sort' => array(
					'default' => 'spider_name',
					'reverse' => 'spider_name DESC',
				),
			),
			'last_seen' => array(
				'header' => array(
					'value' => $txt['spider_last_seen'],
				),
				'data' => array(
					'function' => function($rowData) use ($context, $txt)
					{
						return isset($context['spider_last_seen'][$rowData['id_spider']]) ? timeformat($context['spider_last_seen'][$rowData['id_spider']]) : $txt['spider_last_never'];
					},
				),
			),
			'user_agent' => array(
				'header' => array(
					'value' => $txt['spider_agent'],
				),
				'data' => array(
					'db_htmlsafe' => 'user_agent',
				),
				'sort' => array(
					'default' => 'user_agent',
					'reverse' => 'user_agent DESC',
				),
			),
			'ip_info' => array(
				'header' => array(
					'value' => $txt['spider_ip_info'],
				),
				'data' => array(
					'db_htmlsafe' => 'ip_info',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'ip_info',
					'reverse' => 'ip_info DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
						'params' => array(
							'id_spider' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=sengines;sa=spiders',
			'token' => 'admin-ser',
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="removeSpiders" value="' . $txt['spiders_remove_selected'] . '" data-confirm="' . $txt['spider_remove_selected_confirm'] . '" class="button_submit you_sure">
					<input type="submit" name="addSpider" value="' . $txt['spiders_add'] . '" class="button_submit">
				',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'spider_list';
}

/**
 * Callback function for createList()
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array An array of information about known spiders
 */
function list_getSpiders($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_spider, spider_name, user_agent, ip_info
		FROM {db_prefix}spiders
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items}',
		array(
			'sort' => $sort,
			'start' => $start,
			'items' => $items_per_page,
		)
	);
	$spiders = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$spiders[$row['id_spider']] = $row;
	$smcFunc['db_free_result']($request);

	return $spiders;
}

/**
 * Callback function for createList()
 * @return int The number of known spiders
 */
function list_getNumSpiders()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS num_spiders
		FROM {db_prefix}spiders',
		array(
		)
	);
	list ($numSpiders) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $numSpiders;
}

/**
 * Here we can add, and edit, spider info!
 */
function EditSpider()
{
	global $context, $smcFunc, $txt;

	// Some standard stuff.
	$context['id_spider'] = !empty($_GET['sid']) ? (int) $_GET['sid'] : 0;
	$context['page_title'] = $context['id_spider'] ? $txt['spiders_edit'] : $txt['spiders_add'];
	$context['sub_template'] = 'admin_spider_edit';

	// Are we saving?
	if (!empty($_POST['save']))
	{
		checkSession();
		validateToken('admin-ses');

		$ips = array();
		// Check the IP range is valid.
		$ip_sets = explode(',', $_POST['spider_ip']);
		foreach ($ip_sets as $set)
		{
			$test = ip2range(trim($set));
			if (!empty($test))
				$ips[] = $set;
		}
		$ips = implode(',', $ips);

		// Goes in as it is...
		if ($context['id_spider'])
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}spiders
				SET spider_name = {string:spider_name}, user_agent = {string:spider_agent},
					ip_info = {string:ip_info}
				WHERE id_spider = {int:current_spider}',
				array(
					'current_spider' => $context['id_spider'],
					'spider_name' => $_POST['spider_name'],
					'spider_agent' => $_POST['spider_agent'],
					'ip_info' => $ips,
				)
			);
		else
			$smcFunc['db_insert']('insert',
				'{db_prefix}spiders',
				array(
					'spider_name' => 'string', 'user_agent' => 'string', 'ip_info' => 'string',
				),
				array(
					$_POST['spider_name'], $_POST['spider_agent'], $ips,
				),
				array('id_spider')
			);


		cache_put_data('spider_search', null);
		recacheSpiderNames();

		redirectexit('action=admin;area=sengines;sa=spiders');
	}

	// The default is new.
	$context['spider'] = array(
		'id' => 0,
		'name' => '',
		'agent' => '',
		'ip_info' => '',
	);

	// An edit?
	if ($context['id_spider'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_spider, spider_name, user_agent, ip_info
			FROM {db_prefix}spiders
			WHERE id_spider = {int:current_spider}',
			array(
				'current_spider' => $context['id_spider'],
			)
		);
		if ($row = $smcFunc['db_fetch_assoc']($request))
			$context['spider'] = array(
				'id' => $row['id_spider'],
				'name' => $row['spider_name'],
				'agent' => $row['user_agent'],
				'ip_info' => $row['ip_info'],
			);
		$smcFunc['db_free_result']($request);
	}

	createToken('admin-ses');
}


/**
 * Log the spider presence online.
 *
 * @todo Different file?
 */
function logSpider()
{
	global $smcFunc, $modSettings, $context;

	if (empty($modSettings['spider_mode']) || empty($_SESSION['id_robot']))
		return;

	// Attempt to update today's entry.
	if ($modSettings['spider_mode'] == 1)
	{
		$date = strftime('%Y-%m-%d', forum_time(false));
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_spider_stats
			SET last_seen = {int:current_time}, page_hits = page_hits + 1
			WHERE id_spider = {int:current_spider}
				AND stat_date = {date:current_date}',
			array(
				'current_date' => $date,
				'current_time' => time(),
				'current_spider' => $_SESSION['id_robot'],
			)
		);

		// Nothing updated?
		if ($smcFunc['db_affected_rows']() == 0)
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}log_spider_stats',
				array(
					'id_spider' => 'int', 'last_seen' => 'int', 'stat_date' => 'date', 'page_hits' => 'int',
				),
				array(
					$_SESSION['id_robot'], time(), $date, 1,
				),
				array('id_spider', 'stat_date')
			);
		}
	}
	// If we're tracking better stats than track, better stats - we sort out the today thing later.
	else
	{
		if ($modSettings['spider_mode'] > 2)
		{
			$url = $_GET + array('USER_AGENT' => $_SERVER['HTTP_USER_AGENT']);
			unset($url['sesc'], $url[$context['session_var']]);
			$url = json_encode($url);
		}
		else
			$url = '';

		$smcFunc['db_insert']('insert',
			'{db_prefix}log_spider_hits',
			array('id_spider' => 'int', 'log_time' => 'int', 'url' => 'string'),
			array($_SESSION['id_robot'], time(), $url),
			array()
		);
	}
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
			if (empty($row['data']['viewing']['value']) && isset($modSettings['spider_mode']) && $modSettings['spider_mode'] < 3)
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
