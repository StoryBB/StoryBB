<?php

/**
 * Helper for PM pages.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

class PM
{
	public static function load_labels()
	{
		global $context, $user_info, $user_settings, $smcFunc, $txt;

		$context['labels'] = [];

		// Load the label data.
		if (!empty($user_settings['new_pm']) || ($context['labels'] = cache_get_data('labelCounts:' . $user_info['id'], 720)) === null)
		{
			// Inbox "label"
			$context['labels'][-1] = [
				'id' => -1,
				'name' => $txt['pm_msg_label_inbox'],
				'messages' => 0,
				'unread_messages' => 0,
			];

			// First get the inbox counts
			// The CASE WHEN here is because is_read is set to 3 when you reply to a message
			$result = $smcFunc['db']->query('', '
				SELECT COUNT(*) AS total, SUM(is_read & 1) AS num_read
				FROM {db_prefix}pm_recipients
				WHERE id_member = {int:current_member}
					AND in_inbox = {int:in_inbox}
					AND deleted = {int:not_deleted}',
				[
					'current_member' => $user_info['id'],
					'in_inbox' => 1,
					'not_deleted' => 0,
				]
			);

			while ($row = $smcFunc['db']->fetch_assoc($result))
			{
				$context['labels'][-1]['messages'] = $row['total'];
				$context['labels'][-1]['unread_messages'] = $row['total'] - $row['num_read'];
			}

			$smcFunc['db']->free_result($result);

			// Now load info about all the other labels
			$result = $smcFunc['db']->query('', '
				SELECT l.id_label, l.name, COALESCE(SUM(pr.is_read & 1), 0) AS num_read, COALESCE(COUNT(pr.id_pm), 0) AS total
				FROM {db_prefix}pm_labels AS l
					LEFT JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_label = l.id_label)
					LEFT JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pl.id_pm)
				WHERE l.id_member = {int:current_member}
				GROUP BY l.id_label, l.name',
				[
					'current_member' => $user_info['id'],
				]
			);

			while ($row = $smcFunc['db']->fetch_assoc($result))
			{
				$context['labels'][$row['id_label']] = [
					'id' => $row['id_label'],
					'name' => $row['name'],
					'messages' => $row['total'],
					'unread_messages' => $row['total'] - $row['num_read']
				];
			}

			$smcFunc['db']->free_result($result);

			// Store it please!
			cache_put_data('labelCounts:' . $user_info['id'], $context['labels'], 720);
		}

		// Now we have the labels, and assuming we have unsorted mail, apply our rules!
		if (!empty($user_settings['new_pm']))
		{
			ApplyRules();
			updateMemberData($user_info['id'], ['new_pm' => 0]);
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}pm_recipients
				SET is_new = {int:not_new}
				WHERE id_member = {int:current_member}',
				[
					'current_member' => $user_info['id'],
					'not_new' => 0,
				]
			);
		}

		// This determines if we have more labels than just the standard inbox.
		$context['currently_using_labels'] = count($context['labels']) > 1 ? 1 : 0;
	}
}
