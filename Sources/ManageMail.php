<?php

/**
 * This file is all about mail, how we love it so. In particular it handles the admin side of
 * mail configuration, as well as reviewing the mail queue - if enabled.
 * @todo refactor as controller-model.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;

/**
 * Main dispatcher. This function checks permissions and passes control through to the relevant section.
 */
function ManageMail()
{
	global $context, $txt, $sourcedir;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageMail');

	// We'll need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	$context['page_title'] = $txt['mailqueue_title'];

	$subActions = [
		'browse' => 'BrowseMailQueue',
		'clear' => 'ClearMailQueue',
		'settings' => 'ModifyMailSettings',
	];

	// By default we want to browse
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'browse';
	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['mailqueue_title'],
		'help' => '',
		'description' => $txt['mailqueue_desc'],
	];

	routing_integration_hook('integrate_manage_mail', [&$subActions]);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Display the mail queue...
 */
function BrowseMailQueue()
{
	global $scripturl, $context, $txt, $smcFunc;
	global $sourcedir, $modSettings;

	// First, are we deleting something from the queue?
	if (isset($_REQUEST['delete']))
	{
		checkSession();

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}mail_queue
			WHERE id_mail IN ({array_int:mail_ids})',
			[
				'mail_ids' => $_REQUEST['delete'],
			]
		);
	}

	// How many items do we have?
	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*) AS queue_size, MIN(time_sent) AS oldest
		FROM {db_prefix}mail_queue',
		[
		]
	);
	list ($mailQueueSize, $mailOldest) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	$context['oldest_mail'] = empty($mailOldest) ? $txt['mailqueue_oldest_not_available'] : time_since(time() - $mailOldest);
	$context['mail_queue_size'] = comma_format($mailQueueSize);

	$listOptions = [
		'id' => 'mail_queue',
		'title' => $txt['mailqueue_browse'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=mailqueue',
		'default_sort_col' => 'age',
		'no_items_label' => $txt['mailqueue_no_items'],
		'get_items' => [
			'function' => 'list_getMailQueue',
		],
		'get_count' => [
			'function' => 'list_getMailQueueSize',
		],
		'columns' => [
			'subject' => [
				'header' => [
					'value' => $txt['mailqueue_subject'],
				],
				'data' => [
					'function' => function($rowData) use ($smcFunc)
					{
						return StringLibrary::strlen($rowData['subject']) > 50 ? sprintf('%1$s...', StringLibrary::escape(StringLibrary::substr($rowData['subject'], 0, 47))) : StringLibrary::escape($rowData['subject']);
					},
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'subject',
					'reverse' => 'subject DESC',
				],
			],
			'recipient' => [
				'header' => [
					'value' => $txt['mailqueue_recipient'],
				],
				'data' => [
					'sprintf' => [
						'format' => '<a href="mailto:%1$s">%1$s</a>',
						'params' => [
							'recipient' => true,
						],
					],
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'recipient',
					'reverse' => 'recipient DESC',
				],
			],
			'priority' => [
				'header' => [
					'value' => $txt['mailqueue_priority'],
				],
				'data' => [
					'function' => function($rowData) use ($txt)
					{
						// We probably have a text label with your priority.
						$txtKey = sprintf('mq_mpriority_%1$s', $rowData['priority']);

						// But if not, revert to priority 0.
						return isset($txt[$txtKey]) ? $txt[$txtKey] : $txt['mq_mpriority_1'];
					},
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'priority',
					'reverse' => 'priority DESC',
				],
			],
			'age' => [
				'header' => [
					'value' => $txt['mailqueue_age'],
				],
				'data' => [
					'function' => function($rowData)
					{
						return time_since(time() - $rowData['time_sent']);
					},
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'time_sent',
					'reverse' => 'time_sent DESC',
				],
			],
			'check' => [
				'header' => [
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
				],
				'data' => [
					'function' => function($rowData)
					{
						return '<input type="checkbox" name="delete[]" value="' . $rowData['id_mail'] . '">';
					},
					'class' => 'smalltext',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=admin;area=mailqueue',
			'include_start' => true,
			'include_sort' => true,
		],
		'additional_rows' => [
			[
				'position' => 'top_of_list',
				'value' => '<input type="submit" name="delete_redirects" value="' . $txt['quickmod_delete_selected'] . '" data-confirm="' . $txt['quickmod_confirm'] . '" class="you_sure"><a class="you_sure" href="' . $scripturl . '?action=admin;area=mailqueue;sa=clear;' . $context['session_var'] . '=' . $context['session_id'] . '" data-confirm="' . $txt['mailqueue_clear_list_warning'] . '">' . $txt['mailqueue_clear_list'] . '</a> ',
			],
			[
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="delete_redirects" value="' . $txt['quickmod_delete_selected'] . '" data-confirm="' . $txt['quickmod_confirm'] . '" class="you_sure"><a class="you_sure" href="' . $scripturl . '?action=admin;area=mailqueue;sa=clear;' . $context['session_var'] . '=' . $context['session_id'] . '" data-confirm="' . $txt['mailqueue_clear_list_warning'] . '">' . $txt['mailqueue_clear_list'] . '</a> ',
			],
		],
	];

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'mail_queue_browse';
}

/**
 * This function grabs the mail queue items from the database, according to the params given.
 * Callback for $listOptions['get_items'] in BrowseMailQueue()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @return array An array with info about the mail queue items
 */
function list_getMailQueue($start, $items_per_page, $sort)
{
	global $smcFunc, $txt;

	$request = $smcFunc['db']->query('', '
		SELECT
			id_mail, time_sent, recipient, priority, private, subject
		FROM {db_prefix}mail_queue
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items_per_page}',
		[
			'start' => $start,
			'sort' => $sort,
			'items_per_page' => $items_per_page,
		]
	);
	$mails = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Private PM/email subjects and similar shouldn't be shown in the mailbox area.
		if (!empty($row['private']))
			$row['subject'] = $txt['personal_message'];

		$mails[] = $row;
	}
	$smcFunc['db']->free_result($request);

	return $mails;
}

/**
 * Returns the total count of items in the mail queue.
 * Callback for $listOptions['get_count'] in BrowseMailQueue
 * @return int The total number of mail queue items
 */
function list_getMailQueueSize()
{
	global $smcFunc;

	// How many items do we have?
	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*) AS queue_size
		FROM {db_prefix}mail_queue',
		[
		]
	);
	list ($mailQueueSize) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	return $mailQueueSize;
}

/**
 * Allows to view and modify the mail settings.
 *
 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyMailSettings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings, $txtBirthdayEmails;

	loadLanguage('EmailTemplates');

	$body = $txtBirthdayEmails[(empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']) . '_body'];
	$subject = $txtBirthdayEmails[(empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']) . '_subject'];

	$emails = [];
	$processedBirthdayEmails = [];
	foreach ($txtBirthdayEmails as $key => $value)
	{
		$index = substr($key, 0, strrpos($key, '_'));
		$element = substr($key, strrpos($key, '_') + 1);
		$processedBirthdayEmails[$index][$element] = $value;
	}
	foreach ($processedBirthdayEmails as $index => $dummy)
		$emails[$index] = $index;

	$config_vars = [
			// Mail queue stuff, this rocks ;)
			['int', 'mail_limit', 'subtext' => $txt['zero_to_disable']],
			['int', 'mail_quantity'],
		'',
			// SMTP stuff.
			['select', 'mail_type', [$txt['mail_type_default'], 'SMTP', 'SMTP - STARTTLS']],
			['text', 'webmaster_email'],
			['text', 'smtp_host'],
			['text', 'smtp_port'],
			['text', 'smtp_username'],
			['password', 'smtp_password'],
		'',
			['select', 'birthday_email', $emails, 'value' => !empty($modSettings['birthday_email']) ? $modSettings['birthday_email'] : 'happy_birthday', 'javascript' => 'onchange="fetch_birthday_preview()"'],
			'birthday_subject' => ['var_message', 'birthday_subject', 'var_message' => $processedBirthdayEmails[empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']]['subject'], 'disabled' => true, 'size' => strlen($subject) + 3],
			'birthday_body' => ['var_message', 'birthday_body', 'var_message' => nl2br($body), 'disabled' => true, 'size' => ceil(strlen($body) / 25)],
	];

	settings_integration_hook('integrate_modify_mail_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['mailqueue_settings'], $config_vars];

	// Saving?
	if (isset($_GET['save']))
	{
		// Make the SMTP password a little harder to see in a backup etc.
		if (!empty($_POST['smtp_password'][1]))
		{
			$_POST['smtp_password'][0] = base64_encode($_POST['smtp_password'][0]);
			$_POST['smtp_password'][1] = base64_encode($_POST['smtp_password'][1]);
		}
		checkSession();

		// We don't want to save the subject and body previews.
		unset($config_vars['birthday_subject'], $config_vars['birthday_body']);
		settings_integration_hook('integrate_save_mail_settings');

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=mailqueue;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=mailqueue;save;sa=settings';
	$context['settings_title'] = $txt['mailqueue_settings'];

	prepareDBSettingContext($config_vars);

	$context['settings_insert_above'] = '
	<script>
		var bDay = {';

	$i = 0;
	foreach ($processedBirthdayEmails as $index => $email)
	{
		$is_last = ++$i == count($processedBirthdayEmails);
		$context['settings_insert_above'] .= '
			' . $index . ': {
				subject: ' . JavaScriptEscape($email['subject']) . ',
				body: ' . JavaScriptEscape(nl2br($email['body'])) . '
			}' . (!$is_last ? ',' : '');
	}
	$context['settings_insert_above'] .= '
		};
		function fetch_birthday_preview()
		{
			var index = $("select[name=birthday_email]").val();
			document.getElementById(\'birthday_subject\').innerHTML = bDay[index].subject;
			document.getElementById(\'birthday_body\').innerHTML = bDay[index].body;
		}
	</script>';
}

/**
 * This function clears the mail queue of all emails, and at the end redirects to browse.
 */
function ClearMailQueue()
{
	global $sourcedir, $smcFunc;

	checkSession('get');

	// This is certainly needed!
	require_once($sourcedir . '/ScheduledTasks.php');

	// If we don't yet have the total to clear, find it.
	if (!isset($_GET['te']))
	{
		// How many items do we have?
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*) AS queue_size
			FROM {db_prefix}mail_queue',
			[
			]
		);
		list ($_GET['te']) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
	}
	else
		$_GET['te'] = (int) $_GET['te'];

	$_GET['sent'] = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;

	// Send 50 at a time, then go for a break...
	while (ReduceMailQueue(50, true, true) === true)
	{
		// Sent another 50.
		$_GET['sent'] += 50;
		pauseMailQueueClear();
	}

	return BrowseMailQueue();
}

/**
 * Used for pausing the mail queue.
 */
function pauseMailQueueClear()
{
	global $context, $txt, $time_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we already used our maximum time?
	if (time() - $time_start < 5)
		return;

	$context['continue_get_data'] = '?action=admin;area=mailqueue;sa=clear;te=' . $_GET['te'] . ';sent=' . $_GET['sent'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Keep browse selected.
	$context['selected'] = 'browse';

	// What percent through are we?
	$context['continue_percent'] = round(($_GET['sent'] / $_GET['te']) * 100, 1);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}

/**
 * Little utility function to calculate how long ago a time was.
 *
 * @param int $time_diff The time difference, in seconds
 * @return string A string indicating how many days, hours, minutes or seconds (depending on $time_diff)
 */
function time_since($time_diff)
{
	global $txt;

	if ($time_diff < 0)
		$time_diff = 0;

	// Just do a bit of an if fest...
	if ($time_diff > 86400)
	{
		$days = round($time_diff / 86400, 1);
		return sprintf($days == 1 ? $txt['mq_day'] : $txt['mq_days'], $time_diff / 86400);
	}
	// Hours?
	elseif ($time_diff > 3600)
	{
		$hours = round($time_diff / 3600, 1);
		return sprintf($hours == 1 ? $txt['mq_hour'] : $txt['mq_hours'], $hours);
	}
	// Minutes?
	elseif ($time_diff > 60)
	{
		$minutes = (int) ($time_diff / 60);
		return sprintf($minutes == 1 ? $txt['mq_minute'] : $txt['mq_minutes'], $minutes);
	}
	// Otherwise must be second
	else
		return sprintf($time_diff == 1 ? $txt['mq_second'] : $txt['mq_seconds'], $time_diff);
}
