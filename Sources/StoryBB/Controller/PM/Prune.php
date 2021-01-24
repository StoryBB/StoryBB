<?php

/**
 * Abstract PM controller (hybrid style)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\PM;

use StoryBB\Helper\Navigation\Navigation;

class Prune extends AbstractPMController
{
	public function display_action()
	{
		global $context, $txt, $scripturl;

		// Build the link tree elements.
		$context['linktree'][] = [
			'url' => $scripturl . '?action=pm;sa=prune',
			'name' => $txt['pm_prune']
		];

		$context['sub_template'] = 'personal_message_prune';
		$context['page_title'] = $txt['pm_prune'];
	}

	public function post_action()
	{
		global $txt, $context, $user_info, $scripturl, $smcFunc;

		// Actually delete the messages.
		if (isset($_REQUEST['age']))
		{
			// Calculate the time to delete before.
			$deleteTime = max(0, time() - (86400 * (int) $_REQUEST['age']));

			// Array to store the IDs in.
			$toDelete = [];

			// Select all the messages they have sent older than $deleteTime.
			$request = $smcFunc['db']->query('', '
				SELECT id_pm
				FROM {db_prefix}personal_messages
				WHERE deleted_by_sender = {int:not_deleted}
					AND id_member_from = {int:current_member}
					AND msgtime < {int:msgtime}',
				[
					'current_member' => $user_info['id'],
					'not_deleted' => 0,
					'msgtime' => $deleteTime,
				]
			);
			while ($row = $smcFunc['db']->fetch_row($request))
				$toDelete[] = $row[0];
			$smcFunc['db']->free_result($request);

			// Select all messages in their inbox older than $deleteTime.
			$request = $smcFunc['db']->query('', '
				SELECT pmr.id_pm
				FROM {db_prefix}pm_recipients AS pmr
					INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
				WHERE pmr.deleted = {int:not_deleted}
					AND pmr.id_member = {int:current_member}
					AND pm.msgtime < {int:msgtime}',
				[
					'current_member' => $user_info['id'],
					'not_deleted' => 0,
					'msgtime' => $deleteTime,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$toDelete[] = $row['id_pm'];
			$smcFunc['db']->free_result($request);

			// Delete the actual messages.
			deleteMessages($toDelete);
			session_flash('success', $txt['your_messages_were_deleted']);
			redirectexit('action=pm');
		}

		// If all then delete all messages the user has.
		if (!empty($_REQUEST['all']))
		{
			deleteMessages(null, null);
			session_flash('success', $txt['your_messages_were_deleted']);
			redirectexit('action=pm');
		}

		return $this->display_action();
	}
}
