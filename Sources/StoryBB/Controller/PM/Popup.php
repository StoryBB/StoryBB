<?php

/**
 * PM popup controller.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\PM;

use StoryBB\Template;

class Popup extends AbstractPMController
{
	public function display_action()
	{
		global $context, $modSettings, $smcFunc, $memberContext, $scripturl, $user_settings, $db_show_debug;

		// We do not want to output debug information here.
		$db_show_debug = false;

		// We only want to output our little layer here.
		Template::remove_all_layers();
		$context['sub_template'] = 'personal_message_popup';
		Template::set_layout('raw');

		$context['can_send_pm'] = allowedTo('pm_send');
		$context['can_draft'] = allowedTo('pm_send') && !empty($modSettings['drafts_pm_enabled']);

		// So are we loading stuff?
		$request = $smcFunc['db']->query('', '
			SELECT id_pm
			FROM {db_prefix}pm_recipients AS pmr
			WHERE pmr.id_member = {int:current_member}
				AND is_read = {int:not_read}
			ORDER BY id_pm',
			[
				'current_member' => $context['user']['id'],
				'not_read' => 0,
			]
		);
		$pms = [];
		while ($row = $smcFunc['db']->fetch_row($request))
			$pms[] = $row[0];
		$smcFunc['db']->free_result($request);

		if (!empty($pms))
		{
			// Just quickly, it's possible that the number of PMs can get out of sync.
			$count_unread = count($pms);
			if ($count_unread != $user_settings['unread_messages'])
			{
				updateMemberData($context['user']['id'], ['unread_messages' => $count_unread]);
				$context['user']['unread_messages'] = count($pms);
			}

			// Now, actually fetch me some PMs. Make sure we track the senders, got some work to do for them.
			$senders = [];

			$request = $smcFunc['db']->query('', '
				SELECT pm.id_pm, pm.id_pm_head, COALESCE(mem.id_member, pm.id_member_from) AS id_member_from,
					COALESCE(mem.real_name, pm.from_name) AS member_from, pm.msgtime AS timestamp, pm.subject
				FROM {db_prefix}personal_messages AS pm
					LEFT JOIN {db_prefix}members AS mem ON (pm.id_member_from = mem.id_member)
				WHERE pm.id_pm IN ({array_int:id_pms})',
				[
					'id_pms' => $pms,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				if (!empty($row['id_member_from']))
					$senders[] = $row['id_member_from'];

				$row['replied_to_you'] = $row['id_pm'] != $row['id_pm_head'];
				$row['time'] = timeformat($row['timestamp']);
				$row['pm_link'] = '<a href="' . $scripturl . '?action=pm;f=inbox;pmid=' . $row['id_pm'] . '">' . $row['subject'] . '</a>';
				$context['unread_pms'][$row['id_pm']] = $row;
			}
			$smcFunc['db']->free_result($request);

			$senders = loadMemberData($senders);
			foreach ($senders as $member)
				loadMemberContext($member);

			// Having loaded everyone, attach them to the PMs.
			foreach ($context['unread_pms'] as $id_pm => $details)
				if (!empty($memberContext[$details['id_member_from']]))
					$context['unread_pms'][$id_pm]['member'] = &$memberContext[$details['id_member_from']];
		}
	}
}
