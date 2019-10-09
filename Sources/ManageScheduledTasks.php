<?php

/**
 * This file concerns itself with scheduled tasks management.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Task\Scheduler;

/**
 * Scheduled tasks management dispatcher. This function checks permissions and delegates
 * to the appropriate function based on the sub-action.
 * Everything here requires admin_forum permission.
 *
 * @uses ManageScheduledTasks template file
 * @uses ManageScheduledTasks language file
 */
function ManageScheduledTasks()
{
	global $context, $txt;

	isAllowedTo('admin_forum');

	loadLanguage('ManageScheduledTasks');

	$subActions = [
		'taskedit' => 'EditTask',
		'tasklog' => 'TaskLog',
		'tasks' => 'ScheduledTasks',
	];

	// We need to find what's the action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'tasks';

	// Now for the lovely tabs. That we all love.
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['scheduled_tasks_title'],
		'help' => '',
		'description' => $txt['maintain_info'],
		'tabs' => [
			'tasks' => [
				'description' => $txt['maintain_tasks_desc'],
			],
			'tasklog' => [
				'description' => $txt['scheduled_log_desc'],
			],
		],
	];

	routing_integration_hook('integrate_manage_scheduled_tasks', [&$subActions]);

	// Call it.
	call_helper($subActions[$context['sub_action']]);
}

/**
 * List all the scheduled task in place on the forum.
 *
 * @uses ManageScheduledTasks template, view_scheduled_tasks sub-template
 */
function ScheduledTasks()
{
	global $context, $txt, $sourcedir, $smcFunc, $scripturl;

	// Mama, setup the template first - cause it's like the most important bit, like pickle in a sandwich.
	// ... ironically I don't like pickle. </grudge>
	$context['sub_template'] = 'admin_scheduled_view';
	$context['page_title'] = $txt['maintain_tasks'];
	loadLanguage('ManageScheduledTasks');

	// Saving changes?
	if (isset($_REQUEST['save']) && isset($_POST['enable_task']))
	{
		checkSession();

		// We'll recalculate the dates at the end!
		require_once($sourcedir . '/ScheduledTasks.php');

		// Enable and disable as required.
		$enablers = [0];
		foreach ($_POST['enable_task'] as $id => $enabled)
			if ($enabled)
				$enablers[] = (int) $id;

		// Do the update!
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = CASE WHEN id_task IN ({array_int:id_task_enable}) THEN 0 ELSE 1 END',
			[
				'id_task_enable' => $enablers,
			]
		);

		// Update the "allow_expire_redirect" setting...
		$get_info = $smcFunc['db']->query('', '
			SELECT disabled
			FROM {db_prefix}scheduled_tasks
			WHERE task = {string:remove_redirect}',
			[
				'remove_redirect' => 'remove_topic_redirect'
			]
		);

		$temp = $smcFunc['db']->fetch_assoc($get_info);
		$task_disabled = !empty($temp['disabled']) ? 0 : 1;
		$smcFunc['db']->free_result($get_info);

		updateSettings(['allow_expire_redirect' => $task_disabled]);

		// Pop along...
		CalculateNextTrigger();
	}

	// Want to run any of the tasks?
	if (isset($_REQUEST['run']) && isset($_POST['run_task']))
	{
		// Lets figure out which ones they want to run.
		$tasks = [];
		foreach ($_POST['run_task'] as $task => $dummy)
			$tasks[] = (int) $task;

		// Load up the tasks.
		$request = $smcFunc['db']->query('', '
			SELECT id_task, class
			FROM {db_prefix}scheduled_tasks
			WHERE id_task IN ({array_int:tasks})
			LIMIT {int:limit}',
			[
				'tasks' => $tasks,
				'limit' => count($tasks),
			]
		);

		// Lets get it on!
		require_once($sourcedir . '/ScheduledTasks.php');
		ignore_user_abort(true);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$task = false;
			if (!empty($row['class']) && class_exists($row['class']) && is_subclass_of($row['class'], 'StoryBB\\Task\\Schedulable'))
			{
				$task = new $row['class'];
			}

			// The functions got to exist for us to use it.
			if (empty($task))
				continue;

			// Try to stop a timeout, this would be bad...
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			// Try to run the task.
			try
			{
				$start_time = microtime(true);
				$task->execute();

				$total_time = round(microtime(true) - $start_time, 3);
				Scheduler::log_completed((int) $row['id_task'], $total_time);

				session_flash('success', sprintf($txt['scheduled_tasks_ran_successfully'], $task->get_name()));
			}
			catch (Exception $e)
			{
				session_flash('error', sprintf($txt['scheduled_tasks_ran_errors'], $task->get_name(), $e->getMessage()));
			}
		}
		$smcFunc['db']->free_result($request);

		redirectexit('action=admin;area=scheduledtasks');
	}

	$listOptions = [
		'id' => 'scheduled_tasks',
		'title' => $txt['scheduled_task_list'],
		'base_href' => $scripturl . '?action=admin;area=scheduledtasks',
		'get_items' => [
			'function' => 'list_getScheduledTasks',
		],
		'columns' => [
			'name' => [
				'header' => [
					'value' => $txt['scheduled_tasks_name'],
					'style' => 'width: 40%;',
				],
				'data' => [
					'sprintf' => [
						'format' => '
							<a href="' . $scripturl . '?action=admin;area=scheduledtasks;sa=taskedit;tid=%1$d">%2$s</a><br><span class="smalltext">%3$s</span>',
						'params' => [
							'id' => false,
							'name' => false,
							'desc' => false,
						],
					],
				],
			],
			'next_due' => [
				'header' => [
					'value' => $txt['scheduled_tasks_next_time'],
				],
				'data' => [
					'db' => 'next_time',
					'class' => 'smalltext',
				],
			],
			'regularity' => [
				'header' => [
					'value' => $txt['scheduled_tasks_regularity'],
				],
				'data' => [
					'db' => 'regularity',
					'class' => 'smalltext',
				],
			],
			'run_now' => [
				'header' => [
					'value' => $txt['scheduled_tasks_run_now'],
					'style' => 'width: 12%;',
					'class' => 'centercol',
				],
				'data' => [
					'sprintf' => [
						'format' => '<input type="checkbox" name="run_task[%1$d]" id="run_task_%1$d">',
						'params' => [
							'id' => false,
						],
					],
					'class' => 'centercol',
				],
			],
			'enabled' => [
				'header' => [
					'value' => $txt['scheduled_tasks_enabled'],
					'style' => 'width: 6%;',
					'class' => 'centercol',
				],
				'data' => [
					'sprintf' => [
						'format' => '<input type="hidden" name="enable_task[%1$d]" id="task_%1$d" value="0"><input type="checkbox" name="enable_task[%1$d]" id="task_check_%1$d" %2$s>',
						'params' => [
							'id' => false,
							'checked_state' => false,
						],
					],
					'class' => 'centercol',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=admin;area=scheduledtasks',
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="save" value="' . $txt['scheduled_tasks_save_changes'] . '">
					<input type="submit" name="run" value="' . $txt['scheduled_tasks_run_now'] . '">',
			],
		],
	];

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'scheduled_tasks';
}

/**
 * Callback function for createList() in ScheduledTasks().
 *
 * @param int $start The item to start with (not used here)
 * @param int $items_per_page The number of items to display per page (not used here)
 * @param string $sort A string indicating how to sort things (not used here)
 * @return array An array of information about available scheduled tasks
 */
function list_getScheduledTasks($start, $items_per_page, $sort)
{
	global $smcFunc, $txt;

	$request = $smcFunc['db']->query('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, class
		FROM {db_prefix}scheduled_tasks',
		[
		]
	);
	$known_tasks = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Find the next for regularity - don't offset as it's always server time!
		$offset = sprintf($txt['scheduled_task_reg_starting'], date('H:i', $row['time_offset']));
		$repeating = sprintf($txt['scheduled_task_reg_repeating'], $row['time_regularity'], $txt['scheduled_task_reg_unit_' . $row['time_unit']]);

		$task = class_exists($row['class']) ? new $row['class'] : false;

		$known_tasks[] = [
			'id' => $row['id_task'],
			'name' => $task ? $task->get_name() : $row['class'],
			'desc' => $task ? $task->get_description() : '',
			'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : timeformat(($row['next_time'] == 0 ? time() : $row['next_time']), true, 'server'),
			'disabled' => $row['disabled'],
			'checked_state' => $row['disabled'] ? '' : 'checked',
			'regularity' => $offset . ', ' . $repeating,
		];
	}
	$smcFunc['db']->free_result($request);

	return $known_tasks;
}

/**
 * Function for editing a task.
 *
 * @uses ManageScheduledTasks template, edit_scheduled_tasks sub-template
 */
function EditTask()
{
	global $context, $txt, $sourcedir, $smcFunc;

	// Just set up some lovely context stuff.
	$context[$context['admin_menu_name']]['current_subsection'] = 'tasks';
	$context['sub_template'] = 'admin_scheduled_edit';
	$context['page_title'] = $txt['scheduled_task_edit'];
	$context['server_time'] = timeformat(time(), false, 'server');

	// Cleaning...
	if (!isset($_GET['tid']))
		fatal_lang_error('no_access', false);
	$_GET['tid'] = (int) $_GET['tid'];

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();
		validateToken('admin-st');

		// We'll need this for calculating the next event.
		require_once($sourcedir . '/ScheduledTasks.php');

		// Do we have a valid offset?
		preg_match('~(\d{1,2}):(\d{1,2})~', $_POST['offset'], $matches);

		// If a half is empty then assume zero offset!
		if (!isset($matches[2]) || $matches[2] > 59)
			$matches[2] = 0;
		if (!isset($matches[1]) || $matches[1] > 23)
			$matches[1] = 0;

		// Now the offset is easy; easy peasy - except we need to offset by a few hours...
		$offset = $matches[1] * 3600 + $matches[2] * 60 - date('Z');

		// The other time bits are simple!
		$interval = max((int) $_POST['regularity'], 1);
		$unit = in_array(substr($_POST['unit'], 0, 1), ['m', 'h', 'd', 'w']) ? substr($_POST['unit'], 0, 1) : 'd';

		// Don't allow one minute intervals.
		if ($interval == 1 && $unit == 'm')
			$interval = 2;

		// Is it disabled?
		$disabled = !isset($_POST['enabled']) ? 1 : 0;

		// Do the update!
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = {int:disabled}, time_offset = {int:time_offset}, time_unit = {string:time_unit},
				time_regularity = {int:time_regularity}
			WHERE id_task = {int:id_task}',
			[
				'disabled' => $disabled,
				'time_offset' => $offset,
				'time_regularity' => $interval,
				'id_task' => $_GET['tid'],
				'time_unit' => $unit,
			]
		);

		// Check the next event.
		CalculateNextTrigger($_GET['tid'], true);

		// Return to the main list.
		redirectexit('action=admin;area=scheduledtasks');
	}

	// Load the task, understand? Que? Que?
	$request = $smcFunc['db']->query('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, class
		FROM {db_prefix}scheduled_tasks
		WHERE id_task = {int:id_task}',
		[
			'id_task' => $_GET['tid'],
		]
	);

	// Should never, ever, happen!
	if ($smcFunc['db']->num_rows($request) == 0)
		fatal_lang_error('no_access', false);

	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$task = class_exists($row['class']) ? new $row['class'] : false;
		$context['task'] = [
			'id' => $row['id_task'],
			'name' => $task ? $task->get_name() : $row['class'],
			'desc' => $task ? $task->get_description() : '',
			'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : timeformat($row['next_time'] == 0 ? time() : $row['next_time'], true, 'server'),
			'disabled' => (bool) $row['disabled'],
			'offset' => $row['time_offset'],
			'regularity' => $row['time_regularity'],
			'offset_formatted' => date('H:i', $row['time_offset']),
			'unit' => $row['time_unit'],
		];
	}
	$smcFunc['db']->free_result($request);

	createToken('admin-st');
}

/**
 * Show the log of all tasks that have taken place.
 *
 * @uses ManageScheduledTasks language file
 */
function TaskLog()
{
	global $scripturl, $context, $txt, $smcFunc, $sourcedir;

	// Lets load the language just incase we are outside the Scheduled area.
	loadLanguage('ManageScheduledTasks');

	// Empty the log?
	if (!empty($_POST['removeAll']))
	{
		checkSession();
		validateToken('admin-tl');

		$smcFunc['db']->truncate_table('log_scheduled_tasks');
	}

	// Setup the list.
	$listOptions = [
		'id' => 'task_log',
		'items_per_page' => 30,
		'title' => $txt['scheduled_log'],
		'no_items_label' => $txt['scheduled_log_empty'],
		'base_href' => $context['admin_area'] == 'scheduledtasks' ? $scripturl . '?action=admin;area=scheduledtasks;sa=tasklog' : $scripturl . '?action=admin;area=logs;sa=tasklog',
		'default_sort_col' => 'date',
		'get_items' => [
			'function' => 'list_getTaskLogEntries',
		],
		'get_count' => [
			'function' => 'list_getNumTaskLogEntries',
		],
		'columns' => [
			'name' => [
				'header' => [
					'value' => $txt['scheduled_tasks_name'],
				],
				'data' => [
					'db' => 'name'
				],
			],
			'date' => [
				'header' => [
					'value' => $txt['scheduled_log_time_run'],
				],
				'data' => [
					'function' => function($rowData)
					{
						return timeformat($rowData['time_run'], true);
					},
				],
				'sort' => [
					'default' => 'lst.id_log DESC',
					'reverse' => 'lst.id_log',
				],
			],
			'time_taken' => [
				'header' => [
					'value' => $txt['scheduled_log_time_taken'],
				],
				'data' => [
					'sprintf' => [
						'format' => $txt['scheduled_log_time_taken_seconds'],
						'params' => [
							'time_taken' => false,
						],
					],
				],
				'sort' => [
					'default' => 'lst.time_taken',
					'reverse' => 'lst.time_taken DESC',
				],
			],
		],
		'form' => [
			'href' => $context['admin_area'] == 'scheduledtasks' ? $scripturl . '?action=admin;area=scheduledtasks;sa=tasklog' : $scripturl . '?action=admin;area=logs;sa=tasklog',
			'token' => 'admin-tl',
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="removeAll" value="' . $txt['scheduled_log_empty_log'] . '" data-confirm="' . $txt['scheduled_log_empty_log_confirm'] . '" class="you_sure">',
			],
		],
	];

	createToken('admin-tl');

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'task_log';

	// Make it all look tify.
	$context[$context['admin_menu_name']]['current_subsection'] = 'tasklog';
	$context['page_title'] = $txt['scheduled_log'];
}

/**
 * Callback function for createList() in TaskLog().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to display per page
 * @param string $sort A string indicating how to sort the results
 * @return array An array of info about task log entries
 */
function list_getTaskLogEntries($start, $items_per_page, $sort)
{
	global $smcFunc, $txt;

	$request = $smcFunc['db']->query('', '
		SELECT lst.id_log, lst.id_task, lst.time_run, lst.time_taken, st.class
		FROM {db_prefix}log_scheduled_tasks AS lst
			INNER JOIN {db_prefix}scheduled_tasks AS st ON (st.id_task = lst.id_task)
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items}',
		[
			'sort' => $sort,
			'start' => $start,
			'items' => $items_per_page,
		]
	);
	$log_entries = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$task = class_exists($row['class']) ? new $row['class'] : false;
		$log_entries[] = [
			'id' => $row['id_log'],
			'name' => $task ? $task->get_name() : $row['class'],
			'time_run' => $row['time_run'],
			'time_taken' => $row['time_taken'],
		];
	}
	$smcFunc['db']->free_result($request);

	return $log_entries;
}

/**
 * Callback function for createList() in TaskLog().
 * @return int The number of log entries
 */
function list_getNumTaskLogEntries()
{
	global $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_scheduled_tasks',
		[
		]
	);
	list ($num_entries) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	return $num_entries;
}
