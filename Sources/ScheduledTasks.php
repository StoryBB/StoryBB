<?php

/**
 * This file is automatically called and handles all manner of scheduled things.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
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
			$affected_rows = $smcFunc['db']->affected_rows();

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
						[]
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
		if ($smcFunc['db']->affected_rows() == 0)
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
	$ids = [];
	$emails = [];
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
	$failed_emails = [];
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
function CalculateNextTrigger($tasks = [], $forceUpdate = false)
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
	$tasks = [];
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

	loadLanguage('General');

	// Just in case it wasn't already set elsewhere.
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	// Tell fatal_lang_error() to not reload the theme.
	$context['theme_loaded'] = true;
}
