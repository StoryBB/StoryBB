<?php

/**
 * This file is automatically called and handles all manner of scheduled things.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

use StoryBB\Model\Attachment;

/**
 * This function works out what to do!
 */
function AutoTask()
{
	global $time_start, $smcFunc, $modSettings;

	// Special case for doing the mail queue.
	if (isset($_GET['scheduled']) && $_GET['scheduled'] == 'mailq')
		ReduceMailQueue();
	else
	{
		$task_string = '';

		// Select the next task to do.
		$request = $smcFunc['db_query']('', '
			SELECT id_task, task, next_time, time_offset, time_regularity, time_unit, class
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
				AND next_time <= {int:current_time}
			ORDER BY next_time ASC
			LIMIT 1',
			array(
				'not_disabled' => 0,
				'current_time' => time(),
			)
		);
		if ($smcFunc['db_num_rows']($request) != 0)
		{
			// The two important things really...
			$row = $smcFunc['db_fetch_assoc']($request);

			// When should this next be run?
			$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);

			// How long in seconds it the gap?
			$duration = $row['time_regularity'];
			if ($row['time_unit'] == 'm')
				$duration *= 60;
			elseif ($row['time_unit'] == 'h')
				$duration *= 3600;
			elseif ($row['time_unit'] == 'd')
				$duration *= 86400;
			elseif ($row['time_unit'] == 'w')
				$duration *= 604800;

			// If we were really late running this task actually skip the next one.
			if (time() + ($duration / 2) > $next_time)
				$next_time += $duration;

			// Update it now, so no others run this!
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}scheduled_tasks
				SET next_time = {int:next_time}
				WHERE id_task = {int:id_task}
					AND next_time = {int:current_next_time}',
				array(
					'next_time' => $next_time,
					'id_task' => $row['id_task'],
					'current_next_time' => $row['next_time'],
				)
			);
			$affected_rows = $smcFunc['db_affected_rows']();

			// What kind of task are we handling?
			$task = false;
			if (!empty($row['class']) && class_exists($row['class']))
			{
				$refl = new ReflectionClass($row['class']);
				if ($refl->isSubclassOf('StoryBB\\Task\\Schedulable'))
				{
					$task = new $row['class'];
				}
			}

			// Default StoryBB task or old mods?
			elseif (function_exists('scheduled_' . $row['task']))
				$task = 'scheduled_' . $row['task'];

			// The function must exist or we are wasting our time, plus do some timestamp checking, and database check!
			if (!empty($task) && (!isset($_GET['ts']) || $_GET['ts'] == $row['next_time']) && $affected_rows)
			{
				ignore_user_abort(true);

				// Get the callable.
				if (is_object($task))
				{
					$completed = $task->execute();
				}
				else
				{
					$callable_task = call_helper($task, true);

					// Perform the task.
					if (!empty($callable_task))
						$completed = call_user_func($callable_task);

					else
						$completed = false;
				}

				// Log that we did it ;)
				if ($completed)
				{
					$total_time = round(microtime(true) - $time_start, 3);
					$smcFunc['db_insert']('',
						'{db_prefix}log_scheduled_tasks',
						array(
							'id_task' => 'int', 'time_run' => 'int', 'time_taken' => 'float',
						),
						array(
							$row['id_task'], time(), (int) $total_time,
						),
						array()
					);
				}
			}
		}
		$smcFunc['db_free_result']($request);

		// Get the next timestamp right.
		$request = $smcFunc['db_query']('', '
			SELECT next_time
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
			ORDER BY next_time ASC
			LIMIT 1',
			array(
				'not_disabled' => 0,
			)
		);
		// No new task scheduled yet?
		if ($smcFunc['db_num_rows']($request) === 0)
			$nextEvent = time() + 86400;
		else
			list ($nextEvent) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		updateSettings(array('next_task_time' => $nextEvent));
	}

	// Shall we return?
	if (!isset($_GET['scheduled']))
		return true;

	// Finally, send some stuff...
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Content-Type: image/gif');
	die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
}

/**
 * Send out a daily email of all subscribed topics.
 */
function scheduled_daily_digest()
{
	global $is_weekly, $txt, $mbname, $scripturl, $sourcedir, $smcFunc, $context, $modSettings;

	// We'll want this...
	require_once($sourcedir . '/Subs-Post.php');
	loadEssentialThemeData();

	$is_weekly = !empty($is_weekly) ? 1 : 0;

	// Right - get all the notification data FIRST.
	$request = $smcFunc['db_query']('', '
		SELECT ln.id_topic, COALESCE(t.id_board, ln.id_board) AS id_board, mem.email_address, mem.member_name,
			mem.lngfile, mem.id_member
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			LEFT JOIN {db_prefix}topics AS t ON (ln.id_topic != {int:empty_topic} AND t.id_topic = ln.id_topic)
		WHERE mem.is_activated = {int:is_activated}',
		array(
			'empty_topic' => 0,
			'is_activated' => 1,
		)
	);
	$members = array();
	$langs = array();
	$notify = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($members[$row['id_member']]))
		{
			$members[$row['id_member']] = array(
				'email' => $row['email_address'],
				'name' => $row['member_name'],
				'id' => $row['id_member'],
				'lang' => $row['lngfile'],
			);
			$langs[$row['lngfile']] = $row['lngfile'];
		}

		// Store this useful data!
		$boards[$row['id_board']] = $row['id_board'];
		if ($row['id_topic'])
			$notify['topics'][$row['id_topic']][] = $row['id_member'];
		else
			$notify['boards'][$row['id_board']][] = $row['id_member'];
	}
	$smcFunc['db_free_result']($request);

	if (empty($boards))
		return true;

	// Just get the board names.
	$request = $smcFunc['db_query']('', '
		SELECT id_board, name
		FROM {db_prefix}boards
		WHERE id_board IN ({array_int:board_list})',
		array(
			'board_list' => $boards,
		)
	);
	$boards = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$boards[$row['id_board']] = $row['name'];
	$smcFunc['db_free_result']($request);

	if (empty($boards))
		return true;

	// Get the actual topics...
	$request = $smcFunc['db_query']('', '
		SELECT ld.note_type, t.id_topic, t.id_board, t.id_member_started, m.id_msg, m.subject,
			b.name AS board_name
		FROM {db_prefix}log_digest AS ld
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ld.id_topic
				AND t.id_board IN ({array_int:board_list}))
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ' . ($is_weekly ? 'ld.daily != {int:daily_value}' : 'ld.daily IN (0, 2)'),
		array(
			'board_list' => array_keys($boards),
			'daily_value' => 2,
		)
	);
	$types = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($types[$row['note_type']][$row['id_board']]))
			$types[$row['note_type']][$row['id_board']] = array(
				'lines' => array(),
				'name' => $row['board_name'],
				'id' => $row['id_board'],
			);

		if ($row['note_type'] == 'reply')
		{
			if (isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['count']++;
			else
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
					'id' => $row['id_topic'],
					'subject' => un_htmlspecialchars($row['subject']),
					'count' => 1,
				);
		}
		elseif ($row['note_type'] == 'topic')
		{
			if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
					'id' => $row['id_topic'],
					'subject' => un_htmlspecialchars($row['subject']),
				);
		}
		else
		{
			if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
					'id' => $row['id_topic'],
					'subject' => un_htmlspecialchars($row['subject']),
					'starter' => $row['id_member_started'],
				);
		}

		$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array();
		if (!empty($notify['topics'][$row['id_topic']]))
			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['topics'][$row['id_topic']]);
		if (!empty($notify['boards'][$row['id_board']]))
			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['boards'][$row['id_board']]);
	}
	$smcFunc['db_free_result']($request);

	if (empty($types))
		return true;

	// Let's load all the languages into a cache thingy.
	$langtxt = array();
	foreach ($langs as $lang)
	{
		loadLanguage('Post', $lang);
		loadLanguage('index', $lang);
		loadLanguage('EmailTemplates', $lang);
		$langtxt[$lang] = array(
			'subject' => $txt['digest_subject_' . ($is_weekly ? 'weekly' : 'daily')],
			'intro' => sprintf($txt['digest_intro_' . ($is_weekly ? 'weekly' : 'daily')], $mbname),
			'new_topics' => $txt['digest_new_topics'],
			'topic_lines' => $txt['digest_new_topics_line'],
			'new_replies' => $txt['digest_new_replies'],
			'mod_actions' => $txt['digest_mod_actions'],
			'replies_one' => $txt['digest_new_replies_one'],
			'replies_many' => $txt['digest_new_replies_many'],
			'sticky' => $txt['digest_mod_act_sticky'],
			'lock' => $txt['digest_mod_act_lock'],
			'unlock' => $txt['digest_mod_act_unlock'],
			'remove' => $txt['digest_mod_act_remove'],
			'move' => $txt['digest_mod_act_move'],
			'merge' => $txt['digest_mod_act_merge'],
			'split' => $txt['digest_mod_act_split'],
			'bye' => $txt['regards_team'],
		);
	}

	// The preferred way...
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs(array_keys($members), array('msg_notify_type', 'msg_notify_pref'), true);

	// Right - send out the silly things - this will take quite some space!
	foreach ($members as $mid => $member)
	{
		$frequency = !empty($prefs[$mid]['msg_notify_pref']) ? $prefs[$mid]['msg_notify_pref'] : 1;
		$notify_types = !empty($prefs[$mid]['msg_notify_type']) ? $prefs[$mid]['msg_notify_type'] : 1;

		// Did they not elect to choose this?
		if ($frequency == 4 && !$is_weekly || $frequency == 3 && $is_weekly || $notify_types == 4)
			continue;

		// Do the start stuff!
		$email = array(
			'subject' => $mbname . ' - ' . $langtxt[$lang]['subject'],
			'body' => $member['name'] . ',' . "\n\n" . $langtxt[$lang]['intro'] . "\n" . $scripturl . '?action=profile;area=notification;u=' . $member['id'] . "\n",
			'email' => $member['email'],
		);

		// All new topics?
		if (isset($types['topic']))
		{
			$titled = false;
			foreach ($types['topic'] as $id => $board)
				foreach ($board['lines'] as $topic)
					if (in_array($mid, $topic['members']))
					{
						if (!$titled)
						{
							$email['body'] .= "\n" . $langtxt[$lang]['new_topics'] . ':' . "\n" . '-----------------------------------------------';
							$titled = true;
						}
						$email['body'] .= "\n" . sprintf($langtxt[$lang]['topic_lines'], $topic['subject'], $board['name']);
					}
			if ($titled)
				$email['body'] .= "\n";
		}

		// What about replies?
		if (isset($types['reply']))
		{
			$titled = false;
			foreach ($types['reply'] as $id => $board)
				foreach ($board['lines'] as $topic)
					if (in_array($mid, $topic['members']))
					{
						if (!$titled)
						{
							$email['body'] .= "\n" . $langtxt[$lang]['new_replies'] . ':' . "\n" . '-----------------------------------------------';
							$titled = true;
						}
						$email['body'] .= "\n" . ($topic['count'] == 1 ? sprintf($langtxt[$lang]['replies_one'], $topic['subject']) : sprintf($langtxt[$lang]['replies_many'], $topic['count'], $topic['subject']));
					}

			if ($titled)
				$email['body'] .= "\n";
		}

		// Finally, moderation actions!
		if ($notify_types < 3)
		{
			$titled = false;
			foreach ($types as $note_type => $type)
			{
				if ($note_type == 'topic' || $note_type == 'reply')
					continue;

				foreach ($type as $id => $board)
					foreach ($board['lines'] as $topic)
						if (in_array($mid, $topic['members']))
						{
							if (!$titled)
							{
								$email['body'] .= "\n" . $langtxt[$lang]['mod_actions'] . ':' . "\n" . '-----------------------------------------------';
								$titled = true;
							}
							$email['body'] .= "\n" . sprintf($langtxt[$lang][$note_type], $topic['subject']);
						}
			}
		}
		if ($titled)
			$email['body'] .= "\n";

		// Then just say our goodbyes!
		$email['body'] .= "\n\n" . $txt['regards_team'];

		// Send it - low priority!
		StoryBB\Helper\Mail::send($email['email'], $email['subject'], $email['body'], null, 'digest', false, 4);
	}

	// Clean up...
	if ($is_weekly)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_digest
			WHERE daily != {int:not_daily}',
			array(
				'not_daily' => 0,
			)
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_digest
			SET daily = {int:daily_value}
			WHERE daily = {int:not_daily}',
			array(
				'daily_value' => 2,
				'not_daily' => 0,
			)
		);
	}
	else
	{
		// Clear any only weekly ones, and stop us from sending daily again.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_digest
			WHERE daily = {int:daily_value}',
			array(
				'daily_value' => 2,
			)
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_digest
			SET daily = {int:both_value}
			WHERE daily = {int:no_value}',
			array(
				'both_value' => 1,
				'no_value' => 0,
			)
		);
	}

	// Just in case the member changes their settings mark this as sent.
	$members = array_keys($members);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_notify
		SET sent = {int:is_sent}
		WHERE id_member IN ({array_int:member_list})',
		array(
			'member_list' => $members,
			'is_sent' => 1,
		)
	);

	// Log we've done it...
	return true;
}

/**
 * Like the daily stuff - just seven times less regular ;)
 */
function scheduled_weekly_digest()
{
	global $is_weekly;

	// We just pass through to the daily function - avoid duplication!
	$is_weekly = true;
	return scheduled_daily_digest();
}

/**
 * Send a group of emails from the mail queue.
 *
 * @param bool|int $number The number to send each loop through or false to use the standard limits
 * @param bool $override_limit Whether to bypass the limit
 * @param bool $force_send Whether to forcibly send the messages now (useful when using cron jobs)
 * @return bool Whether things were sent
 */
function ReduceMailQueue($number = false, $override_limit = false, $force_send = false)
{
	global $modSettings, $smcFunc, $sourcedir;

	// Are we intending another script to be sending out the queue?
	if (!empty($modSettings['mail_queue_use_cron']) && empty($force_send))
		return false;

	// By default send 5 at once.
	if (!$number)
		$number = empty($modSettings['mail_quantity']) ? 5 : $modSettings['mail_quantity'];

	// If we came with a timestamp, and that doesn't match the next event, then someone else has beaten us.
	if (isset($_GET['ts']) && $_GET['ts'] != $modSettings['mail_next_send'] && empty($force_send))
		return false;

	// By default move the next sending on by 10 seconds, and require an affected row.
	if (!$override_limit)
	{
		$delay = !empty($modSettings['mail_queue_delay']) ? $modSettings['mail_queue_delay'] : (!empty($modSettings['mail_limit']) && $modSettings['mail_limit'] < 5 ? 10 : 5);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}settings
			SET value = {string:next_mail_send}
			WHERE variable = {literal:mail_next_send}
				AND value = {string:last_send}',
			array(
				'next_mail_send' => time() + $delay,
				'last_send' => $modSettings['mail_next_send'],
			)
		);
		if ($smcFunc['db_affected_rows']() == 0)
			return false;
		$modSettings['mail_next_send'] = time() + $delay;
	}

	// If we're not overriding how many are we allow to send?
	if (!$override_limit && !empty($modSettings['mail_limit']))
	{
		list ($mt, $mn) = @explode('|', $modSettings['mail_recent']);

		// Nothing worth noting...
		if (empty($mn) || $mt < time() - 60)
		{
			$mt = time();
			$mn = $number;
		}
		// Otherwise we have a few more we can spend?
		elseif ($mn < $modSettings['mail_limit'])
		{
			$mn += $number;
		}
		// No more I'm afraid, return!
		else
			return false;

		// Reflect that we're about to send some, do it now to be safe.
		updateSettings(array('mail_recent' => $mt . '|' . $mn));
	}

	// Now we know how many we're sending, let's send them.
	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_mail, recipient, body, subject, headers, send_html, time_sent, private
		FROM {db_prefix}mail_queue
		ORDER BY priority ASC, id_mail ASC
		LIMIT {int:limit}',
		array(
			'limit' => $number,
		)
	);
	$ids = array();
	$emails = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We want to delete these from the database ASAP, so just get the data and go.
		$ids[] = $row['id_mail'];
		$emails[] = array(
			'to' => $row['recipient'],
			'body' => $row['body'],
			'subject' => $row['subject'],
			'headers' => $row['headers'],
			'send_html' => $row['send_html'],
			'time_sent' => $row['time_sent'],
			'private' => $row['private'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Delete, delete, delete!!!
	if (!empty($ids))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}mail_queue
			WHERE id_mail IN ({array_int:mail_list})',
			array(
				'mail_list' => $ids,
			)
		);

	// Don't believe we have any left?
	if (count($ids) < $number)
	{
		// Only update the setting if no-one else has beaten us to it.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}settings
			SET value = {string:no_send}
			WHERE variable = {literal:mail_next_send}
				AND value = {string:last_mail_send}',
			array(
				'no_send' => '0',
				'last_mail_send' => $modSettings['mail_next_send'],
			)
		);
	}

	if (empty($ids))
		return false;

	if (!empty($modSettings['mail_type']) && $modSettings['smtp_host'] != '')
		require_once($sourcedir . '/Subs-Post.php');

	// Send each email, yea!
	$failed_emails = array();
	foreach ($emails as $email)
	{
		if (empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '')
		{
			$email['subject'] = strtr($email['subject'], array("\r" => '', "\n" => ''));
			if (!empty($modSettings['mail_strip_carriage']))
			{
				$email['body'] = strtr($email['body'], array("\r" => ''));
				$email['headers'] = strtr($email['headers'], array("\r" => ''));
			}

			// No point logging a specific error here, as we have no language. PHP error is helpful anyway...
			$result = mail(strtr($email['to'], array("\r" => '', "\n" => '')), $email['subject'], $email['body'], $email['headers']);

			// Try to stop a timeout, this would be bad...
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();
		}
		else
			$result = StoryBB\Helper\Mail::send_smtp(array($email['to']), $email['subject'], $email['body'], $email['headers']);

		// Hopefully it sent?
		if (!$result)
			$failed_emails[] = array($email['to'], $email['body'], $email['subject'], $email['headers'], $email['send_html'], $email['time_sent'], $email['private']);
	}

	// Any emails that didn't send?
	if (!empty($failed_emails))
	{
		// Update the failed attempts check.
		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('mail_failed_attempts', empty($modSettings['mail_failed_attempts']) ? 1 : ++$modSettings['mail_failed_attempts']),
			array('variable')
		);

		// If we have failed to many times, tell mail to wait a bit and try again.
		if ($modSettings['mail_failed_attempts'] > 5)
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}settings
				SET value = {string:next_mail_send}
				WHERE variable = {literal:mail_next_send}
					AND value = {string:last_send}',
				array(
					'next_mail_send' => time() + 60,
					'last_send' => $modSettings['mail_next_send'],
			));

		// Add our email back to the queue, manually.
		$smcFunc['db_insert']('insert',
			'{db_prefix}mail_queue',
			array('recipient' => 'string', 'body' => 'string', 'subject' => 'string', 'headers' => 'string', 'send_html' => 'string', 'time_sent' => 'string', 'private' => 'int'),
			$failed_emails,
			array('id_mail')
		);

		return false;
	}
	// We where unable to send the email, clear our failed attempts.
	elseif (!empty($modSettings['mail_failed_attempts']))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}settings
			SET value = {string:zero}
			WHERE variable = {string:mail_failed_attempts}',
			array(
				'zero' => '0',
				'mail_failed_attempts' => 'mail_failed_attempts',
		));

	// Had something to send...
	return true;
}

/**
 * Calculate the next time the passed tasks should be triggered.
 *
 * @param string|array $tasks The ID of a single task or an array of tasks
 * @param bool $forceUpdate Whether to force the tasks to run now
 */
function CalculateNextTrigger($tasks = array(), $forceUpdate = false)
{
	global $modSettings, $smcFunc;

	$task_query = '';
	if (!is_array($tasks))
		$tasks = array($tasks);

	// Actually have something passed?
	if (!empty($tasks))
	{
		if (!isset($tasks[0]) || is_numeric($tasks[0]))
			$task_query = ' AND id_task IN ({array_int:tasks})';
		else
			$task_query = ' AND task IN ({array_string:tasks})';
	}
	$nextTaskTime = empty($tasks) ? time() + 86400 : $modSettings['next_task_time'];

	// Get the critical info for the tasks.
	$request = $smcFunc['db_query']('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit
		FROM {db_prefix}scheduled_tasks
		WHERE disabled = {int:no_disabled}
			' . $task_query,
		array(
			'no_disabled' => 0,
			'tasks' => $tasks,
		)
	);
	$tasks = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);

		// Only bother moving the task if it's out of place or we're forcing it!
		if ($forceUpdate || $next_time < $row['next_time'] || $row['next_time'] < time())
			$tasks[$row['id_task']] = $next_time;
		else
			$next_time = $row['next_time'];

		// If this is sooner than the current next task, make this the next task.
		if ($next_time < $nextTaskTime)
			$nextTaskTime = $next_time;
	}
	$smcFunc['db_free_result']($request);

	// Now make the changes!
	foreach ($tasks as $id => $time)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}scheduled_tasks
			SET next_time = {int:next_time}
			WHERE id_task = {int:id_task}',
			array(
				'next_time' => $time,
				'id_task' => $id,
			)
		);

	// If the next task is now different update.
	if ($modSettings['next_task_time'] != $nextTaskTime)
		updateSettings(array('next_task_time' => $nextTaskTime));
}

/**
 * Simply returns a time stamp of the next instance of these time parameters.
 *
 * @param int $regularity The regularity
 * @param string $unit What unit are we using - 'm' for minutes, 'd' for days, 'w' for weeks or anything else for seconds
 * @param int $offset The offset
 * @return int The timestamp for the specified time
 */
function next_time($regularity, $unit, $offset)
{
	// Just in case!
	if ($regularity == 0)
		$regularity = 2;

	$curMin = date('i', time());

	// If the unit is minutes only check regularity in minutes.
	if ($unit == 'm')
	{
		$off = date('i', $offset);

		// If it's now just pretend it ain't,
		if ($off == $curMin)
			$next_time = time() + $regularity;
		else
		{
			// Make sure that the offset is always in the past.
			$off = $off > $curMin ? $off - 60 : $off;

			while ($off <= $curMin)
				$off += $regularity;

			// Now we know when the time should be!
			$next_time = time() + 60 * ($off - $curMin);
		}
	}
	// Otherwise, work out what the offset would be with today's date.
	else
	{
		$next_time = mktime(date('H', $offset), date('i', $offset), 0, date('m'), date('d'), date('Y'));

		// Make the time offset in the past!
		if ($next_time > time())
		{
			$next_time -= 86400;
		}

		// Default we'll jump in hours.
		$applyOffset = 3600;
		// 24 hours = 1 day.
		if ($unit == 'd')
			$applyOffset = 86400;
		// Otherwise a week.
		if ($unit == 'w')
			$applyOffset = 604800;

		$applyOffset *= $regularity;

		// Just add on the offset.
		while ($next_time <= time())
		{
			$next_time += $applyOffset;
		}
	}

	return $next_time;
}

/**
 * This loads the bare minimum data to allow us to load language files!
 */
function loadEssentialThemeData()
{
	global $settings, $modSettings, $smcFunc, $mbname, $context, $sourcedir, $txt;

	// Get all the default theme variables.
	$result = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND id_theme IN (1, {int:theme_guests})',
		array(
			'no_member' => 0,
			'theme_guests' => !empty($modSettings['theme_guests']) ? $modSettings['theme_guests'] : 1,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$settings[$row['variable']] = $row['value'];

		// Is this the default theme?
		if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1')
			$settings['default_' . $row['variable']] = $row['value'];
	}
	$smcFunc['db_free_result']($result);

	// Check we have some directories setup.
	if (empty($settings['template_dirs']))
	{
		$settings['template_dirs'] = array($settings['theme_dir']);

		// Based on theme (if there is one).
		if (!empty($settings['base_theme_dir']))
			$settings['template_dirs'][] = $settings['base_theme_dir'];

		// Lastly the default theme.
		if ($settings['theme_dir'] != $settings['default_theme_dir'])
			$settings['template_dirs'][] = $settings['default_theme_dir'];
	}

	// Assume we want this.
	$context['forum_name'] = $mbname;

	// Check loadLanguage actually exists!
	if (!function_exists('loadLanguage'))
	{
		require_once($sourcedir . '/Load.php');
		require_once($sourcedir . '/Subs.php');
	}

	loadLanguage('index+Modifications');

	// Just in case it wasn't already set elsewhere.
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	// Tell fatal_lang_error() to not reload the theme.
	$context['theme_loaded'] = true;
}
