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
use StoryBB\Helper\Parser;

class Folder extends AbstractPMController
{
	public function display_action()
	{
		global $txt, $scripturl, $modSettings, $context, $subjects_request;
		global $messages_request, $user_info, $recipients, $options, $smcFunc, $user_settings;

		// Some stuff for the labels...
		$context['current_label_id'] = isset($_REQUEST['l']) && isset($context['labels'][$_REQUEST['l']]) ? (int) $_REQUEST['l'] : -1;
		$context['current_label'] = &$context['labels'][$context['current_label_id']]['name'];
		$context['folder'] = !isset($_REQUEST['f']) || $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent';

		// This is convenient.  Do you know how annoying it is to do this every time?!
		$context['current_label_redirect'] = 'action=pm;f=' . $context['folder'] . (isset($_GET['start']) ? ';start=' . $_GET['start'] : '') . (isset($_REQUEST['l']) ? ';l=' . ((int) $_REQUEST['l']) : '');
		$context['can_issue_warning'] = allowedTo('issue_warning') && $modSettings['warning_settings'][0] == 1;

		// Are PM drafts enabled?
		$context['drafts_pm_save'] = !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_send');
		$context['drafts_autosave'] = !empty($context['drafts_pm_save']) && !empty($modSettings['drafts_autosave_enabled']);

		// Build the linktree for all the actions...
		$context['linktree'][] = [
			'url' => $scripturl . '?action=pm',
			'name' => $txt['personal_messages']
		];

		// Make sure the starting location is valid.
		if (isset($_GET['start']) && $_GET['start'] != 'new')
			$_GET['start'] = (int) $_GET['start'];
		elseif (!isset($_GET['start']) && !empty($options['view_newest_pm_first']))
			$_GET['start'] = 0;
		else
			$_GET['start'] = 'new';

		// Set up some basic theme stuff.
		$context['from_or_to'] = $context['folder'] != 'sent' ? 'from' : 'to';
		$context['get_pmessage'] = [$this, 'get_messages'];
		$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : [];

		$labelJoin = '';
		$labelQuery = '';
		$labelQuery2 = '';

		// StoryBB logic: If you're viewing a label, it's still the inbox
		if ($context['folder'] == 'inbox' && $context['current_label_id'] == -1)
		{
			$labelQuery = '
				AND pmr.in_inbox = 1';
		}
		elseif ($context['folder'] != 'sent')
		{
			$labelJoin = '
				INNER JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_pm = pmr.id_pm)';

			$labelQuery2 = '
				AND pl.id_label = ' . $context['current_label_id'];
		}

		// Sorting the folder.
		$sort_methods = [
			'date' => 'pm.id_pm',
			'name' => 'COALESCE(mem.real_name, \'\')',
			'subject' => 'pm.subject',
		];

		// They didn't pick one, use the forum default.
		if (!isset($_GET['sort']) || !isset($sort_methods[$_GET['sort']]))
		{
			$context['sort_by'] = 'date';
			$_GET['sort'] = 'pm.id_pm';
			// An overriding setting?
			$descending = !empty($options['view_newest_pm_first']);
		}
		// Otherwise use the defaults: ascending, by date.
		else
		{
			$context['sort_by'] = $_GET['sort'];
			$_GET['sort'] = $sort_methods[$_GET['sort']];
			$descending = isset($_GET['desc']);
		}

		$context['sort_direction'] = $descending ? 'down' : 'up';

		// Figure out how many messages there are.
		if ($context['folder'] == 'sent')
			$request = $smcFunc['db']->query('', '
				SELECT COUNT(DISTINCT pm.id_pm_head)
				FROM {db_prefix}personal_messages AS pm
				WHERE pm.id_member_from = {int:current_member}
					AND pm.deleted_by_sender = {int:not_deleted}',
				[
					'current_member' => $user_info['id'],
					'not_deleted' => 0,
				]
			);
		else
			$request = $smcFunc['db']->query('', '
				SELECT COUNT(DISTINCT pm.id_pm_head)
				FROM {db_prefix}pm_recipients AS pmr
					INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)' . $labelJoin . '
				WHERE pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:not_deleted}' . $labelQuery . $labelQuery2,
				[
					'current_member' => $user_info['id'],
					'not_deleted' => 0,
				]
			);
		list ($max_messages) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		// Only show the button if there are messages to delete.
		$context['show_delete'] = $max_messages > 0;
		$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

		// Start on the last page.
		if (!is_numeric($_GET['start']) || $_GET['start'] >= $max_messages)
			$_GET['start'] = ($max_messages - 1) - (($max_messages - 1) % $maxPerPage);
		elseif ($_GET['start'] < 0)
			$_GET['start'] = 0;

		// ... but wait - what if we want to start from a specific message?
		if (isset($_GET['pmid']))
		{
			$pmID = (int) $_GET['pmid'];

			// Make sure you have access to this PM.
			if (!isAccessiblePM($pmID, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
				fatal_lang_error('no_access', false);

			$context['current_pm'] = $pmID;
			$_GET['start'] = 0;
		}

		// Set up the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;f=' . $context['folder'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . ';sort=' . $context['sort_by'] . ($descending ? ';desc' : ''), $_GET['start'], $max_messages, $maxPerPage);
		$context['start'] = $_GET['start'];

		// Determine the navigation context.
		$context['links'] = [
			'first' => $_GET['start'] >= $maxPerPage ? $scripturl . '?action=pm;start=0' : '',
			'prev' => $_GET['start'] >= $maxPerPage ? $scripturl . '?action=pm;start=' . ($_GET['start'] - $maxPerPage) : '',
			'next' => $_GET['start'] + $maxPerPage < $max_messages ? $scripturl . '?action=pm;start=' . ($_GET['start'] + $maxPerPage) : '',
			'last' => $_GET['start'] + $maxPerPage < $max_messages ? $scripturl . '?action=pm;start=' . (floor(($max_messages - 1) / $maxPerPage) * $maxPerPage) : '',
			'up' => $scripturl,
		];
		$context['page_info'] = [
			'current_page' => $_GET['start'] / $maxPerPage + 1,
			'num_pages' => floor(($max_messages - 1) / $maxPerPage) + 1
		];

		// First work out what messages we need to see
		if ($context['folder'] != 'sent' && $context['folder'] != 'inbox')
		{
			$labelJoin = '
				INNER JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_pm = pm.id_pm)';

			$labelQuery = '';
			$labelQuery2 = '
				AND pl.id_label = ' . $context['current_label_id'];
		}

		$request = $smcFunc['db']->query('pm_conversation_list', '
				SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:deleted_by}
					' . $labelQuery . ')') . $labelJoin . ($context['sort_by'] == 'name' ? ('
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
				WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
					AND pm.deleted_by_sender = {int:deleted_by}' : '1=1') . (empty($context['current_pm']) ? '' : '
					AND pm.id_pm = {int:pmsg}') . $labelQuery2 . '
				GROUP BY pm.id_pm_head'.($_GET['sort'] != 'pm.id_pm' ? ',' . $_GET['sort'] : '') . '
				ORDER BY ' . ($_GET['sort'] == 'pm.id_pm' ? 'id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($context['current_pm']) ? '
				LIMIT ' . $_GET['start'] . ', ' . $maxPerPage : ''),
				[
					'current_member' => $user_info['id'],
					'deleted_by' => 0,
					'sort' => $_GET['sort'],
					'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
					'pmsg' => $context['current_pm'] ?? 0,
				]
		);

		// Load the id_pms and initialize recipients.
		$pms = [];
		$lastData = [];
		$posters = $context['folder'] == 'sent' ? [$user_info['id']] : [];
		$recipients = [];

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!isset($recipients[$row['id_pm']]))
			{
				if (isset($row['id_member_from']))
					$posters[$row['id_pm']] = $row['id_member_from'];
				$pms[$row['id_pm']] = $row['id_pm'];
				$recipients[$row['id_pm']] = [
					'to' => [],
				];
			}

			// Keep track of the last message so we know what the head is without another query!
			if ((empty($pmID) && (empty($options['view_newest_pm_first']) || !isset($lastData))) || empty($lastData) || (!empty($pmID) && $pmID == $row['id_pm']))
				$lastData = [
					'id' => $row['id_pm'],
					'head' => $row['id_pm_head'],
				];
		}
		$smcFunc['db']->free_result($request);

		// Make sure that we have been given a correct head pm id!
		if (!empty($pmID) && (!isset($lastData['id']) || $pmID != $lastData['id']))
			fatal_lang_error('no_access', false);

		if (!empty($pms))
		{
			if (empty($pmID))
			{
				$context['current_pm'] = 0;
			}
			else
			{
				$context['current_pm'] = $pmID;
			}

			// This is a list of the pm's that are used for "full" display.
			$display_pms = [$context['current_pm']];

			// At this point we know the main id_pm's. But we are looking at conversations so we need the others!
			$request = $smcFunc['db']->query('', '
				SELECT pm.id_pm, pm.id_member_from, pm.deleted_by_sender, pmr.id_member, pmr.deleted
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				WHERE pm.id_pm_head = {int:id_pm_head}
					AND ((pm.id_member_from = {int:current_member} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:current_member} AND pmr.deleted = {int:not_deleted}))
				ORDER BY pm.id_pm',
				[
					'current_member' => $user_info['id'],
					'id_pm_head' => $lastData['head'],
					'not_deleted' => 0,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				// This is, frankly, a joke. We will put in a workaround for people sending to themselves - yawn!
				if ($context['folder'] == 'sent' && $row['id_member_from'] == $user_info['id'] && $row['deleted_by_sender'] == 1)
					continue;
				elseif ($row['id_member'] == $user_info['id'] & $row['deleted'] == 1)
					continue;

				if (!isset($recipients[$row['id_pm']]))
					$recipients[$row['id_pm']] = [
						'to' => [],
					];
				$display_pms[] = $row['id_pm'];
				$posters[$row['id_pm']] = $row['id_member_from'];
			}
			$smcFunc['db']->free_result($request);

			// This is pretty much EVERY pm!
			$all_pms = array_merge($pms, $display_pms);
			$all_pms = array_unique($all_pms);

			// Get recipients.
			$request = $smcFunc['db']->query('', '
				SELECT pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.in_inbox, pmr.is_read
				FROM {db_prefix}pm_recipients AS pmr
					LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
				WHERE pmr.id_pm IN ({array_int:pm_list})',
				[
					'pm_list' => $all_pms,
				]
			);
			$context['message_labels'] = [];
			foreach ($all_pms as $all_pms_id) {
				$context['message_labels'][$all_pms_id] = [];
			}

			$context['message_replied'] = [];
			$context['message_unread'] = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				$recipients[$row['id_pm']]['to'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

				$context['folder'] == '';

				if ($row['id_member_to'] == $user_info['id'] && $context['folder'] != 'sent')
				{
					$context['message_replied'][$row['id_pm']] = $row['is_read'] & 2;
					$context['message_unread'][$row['id_pm']] = $row['is_read'] == 0;

					// Get the labels for this PM
					$request2 = $smcFunc['db']->query('', '
						SELECT id_label
						FROM {db_prefix}pm_labeled_messages
						WHERE id_pm = {int:current_pm}',
						[
							'current_pm' => $row['id_pm'],
						]
					);

					while ($row2 = $smcFunc['db']->fetch_assoc($request2))
					{
						$l_id = $row2['id_label'];
						if (isset($context['labels'][$l_id]))
							$context['message_labels'][$row['id_pm']][$l_id] = ['id' => $l_id, 'name' => $context['labels'][$l_id]['name']];
					}

					$smcFunc['db']->free_result($request2);

					// Is this in the inbox as well?
					if ($row['in_inbox'] == 1)
					{
						$context['message_labels'][$row['id_pm']][-1] = ['id' => -1, 'name' => $context['labels'][-1]['name']];
					}
				}
			}
			$smcFunc['db']->free_result($request);

			// Load any users....
			loadMemberData($posters);

			// Get the order right.
			$orderBy = [];
			foreach (array_reverse($pms) as $pm)
				$orderBy[] = 'pm.id_pm = ' . $pm;

			// Seperate query for these bits!
			$subjects_request = $smcFunc['db']->query('', '
				SELECT pm.id_pm, pm.id_pm_head, pm.subject, COALESCE(pm.id_member_from, 0) AS id_member_from, pm.msgtime, COALESCE(mem.real_name, pm.from_name) AS from_name,
					mem.id_member
				FROM {db_prefix}personal_messages AS pm
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_pm IN ({array_int:pm_list})
				ORDER BY ' . implode(', ', $orderBy) . '
				LIMIT {int:limit}',
				[
					'pm_list' => $pms,
					'limit' => count($pms),
				]
			);

			// Execute the query!
			$messages_request = [];
			if ($context['current_pm'])
			{
				$messages_request = $smcFunc['db']->query('', '
					SELECT pm.id_pm, pm.id_pm_head, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
					FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '
						LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') . ($context['sort_by'] == 'name' ? '
						LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})' : '') . '
					WHERE pm.id_pm IN ({array_int:display_pms})' . ($context['folder'] == 'sent' ? '
					GROUP BY pm.id_pm, pm.id_pm_head, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name' : '') . '
					ORDER BY pm.id_pm' . ($descending ? ' DESC' : ' ASC') . '
					LIMIT {int:limit}',
					[
						'display_pms' => $display_pms,
						'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
						'limit' => count($display_pms),
						'sort' => $_GET['sort'],
					]
				);

				// Build the conversation button array.
				$context['conversation_buttons'] = [
					'delete' => ['text' => 'delete_conversation', 'url' => $scripturl . '?action=pm;sa=pmactions;pm_actions[' . $context['current_pm'] . ']=delete;conversation;returnto=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';' . $context['session_var'] . '=' . $context['session_id'], 'custom' => 'data-confirm="' . $txt['remove_conversation'] . '"', 'class' => 'you_sure'],
				];
			}

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_conversation_buttons');
		}
		else
			$messages_request = false;

		$context['can_send_pm'] = allowedTo('pm_send');
		$context['can_send_email'] = allowedTo('moderate_forum');
		$context['sub_template'] = 'personal_message_folder';
		$context['page_title'] = $txt['pm_inbox'];
		
		//This is a dirty hack. The new template system can't read the messages from a pump the way the old one could.
		while($message = $context['get_pmessage']('message')) {
			$context['messages'][] = $message;
		}
		while($message = $context['get_pmessage']('subject')) {
			$context['subjects'][] = $message;
		}

		// Finally mark the relevant messages as read.
		if ($context['folder'] != 'sent' && !empty($context['labels'][(int) $context['current_label_id']]['unread_messages']))
		{
			if (!empty($context['current_pm']))
				markMessages($display_pms, $context['current_label_id']);
		}
	}

	public function get_messages($type = 'subject', $reset = false)
	{
		global $txt, $scripturl, $modSettings, $context, $messages_request, $memberContext, $recipients, $smcFunc;
		global $user_info, $subjects_request;

		// Count the current message number....
		static $counter = null;
		if ($counter === null || $reset)
			$counter = $context['start'];

		static $temp_pm_selected = null;
		if ($temp_pm_selected === null)
		{
			$temp_pm_selected = isset($_SESSION['pm_selected']) ? $_SESSION['pm_selected'] : [];
			$_SESSION['pm_selected'] = [];
		}

		// Process the data for multiple messages.
		if ($subjects_request && $type == 'subject')
		{
			$subject = $smcFunc['db']->fetch_assoc($subjects_request);
			if (!$subject)
			{
				$smcFunc['db']->free_result($subjects_request);
				return false;
			}

			$subject['subject'] = $subject['subject'] == '' ? $txt['no_subject'] : $subject['subject'];
			censorText($subject['subject']);

			$output = [
				'id' => $subject['id_pm'],
				'member' => [
					'id' => $subject['id_member_from'],
					'name' => $subject['from_name'],
					'link' => ($subject['id_member_from'] != 0) ? '<a href="' . $scripturl . '?action=profile;u=' . $subject['id_member_from'] . '">' . $subject['from_name'] . '</a>' : $subject['from_name'],
				],
				'recipients' => &$recipients[$subject['id_pm']],
				'subject' => $subject['subject'],
				'time' => timeformat($subject['msgtime']),
				'timestamp' => forum_time(true, $subject['msgtime']),
				'number_recipients' => count($recipients[$subject['id_pm']]['to']),
				'labels' => $context['message_labels'][$subject['id_pm_head']] ?? [],
				'fully_labeled' => count($context['message_labels'][$subject['id_pm']]) == count($context['labels']),
				'is_replied_to' => &$context['message_replied'][$subject['id_pm']],
				'is_unread' => &$context['message_unread'][$subject['id_pm']],
				'is_selected' => !empty($temp_pm_selected) && in_array($subject['id_pm'], $temp_pm_selected),
			];

			if (!isset($context['current_msg_labels']))
			{
				$context['current_msg_labels'] = $output['labels'];
			}

			return $output;
		}

		// Bail if it's false, ie. no messages.
		if ($messages_request == false)
			return false;

		// Reset the data?
		if ($reset == true)
			return @$smcFunc['db']->seek($messages_request, 0);

		// Get the next one... bail if anything goes wrong.
		$message = $smcFunc['db']->fetch_assoc($messages_request);
		if (!$message)
		{
			if ($type != 'subject')
				$smcFunc['db']->free_result($messages_request);

			return false;
		}

		// Use '(no subject)' if none was specified.
		$message['subject'] = $message['subject'] == '' ? $txt['no_subject'] : $message['subject'];

		$context['current_msg_labels'] = $context['message_labels'][$message['id_pm_head']] ?? [];

		// Load the message's information - if it's not there, load the guest information.
		if (!loadMemberContext($message['id_member_from'], true))
		{
			$memberContext[$message['id_member_from']]['name'] = $message['from_name'];
			$memberContext[$message['id_member_from']]['id'] = 0;

			// Sometimes the forum sends messages itself (Warnings are an example) - in this case don't label it from a guest.
			$memberContext[$message['id_member_from']]['group'] = $message['from_name'] == $context['forum_name_html_safe'] ? '' : $txt['guest_title'];
			$memberContext[$message['id_member_from']]['link'] = $message['from_name'];
			$memberContext[$message['id_member_from']]['email'] = '';
			$memberContext[$message['id_member_from']]['show_email'] = false;
			$memberContext[$message['id_member_from']]['is_guest'] = true;
		}
		else
		{
			$memberContext[$message['id_member_from']]['can_view_profile'] = allowedTo('profile_view') || ($message['id_member_from'] == $user_info['id'] && !$user_info['is_guest']);
			$memberContext[$message['id_member_from']]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$message['id_member_from']]['warning_status'] && ($context['user']['can_mod'] || (!empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $message['id_member_from'] == $user_info['id'])));
			// Show the email if it's your own PM
			$memberContext[$message['id_member_from']]['show_email'] |= $message['id_member_from'] == $user_info['id'];
		}

		$memberContext[$message['id_member_from']]['show_profile_buttons'] = $modSettings['show_profile_buttons'] && (!empty($memberContext[$message['id_member_from']]['can_view_profile']) || $memberContext[$message['id_member_from']]['show_email'] || $context['can_send_pm']);

		// Censor all the important text...
		censorText($message['body']);
		censorText($message['subject']);

		// Run UBBC interpreter on the message.
		$message['body'] = Parser::parse_bbc($message['body'], true, 'pm' . $message['id_pm']);

		// Send the array.
		$output = [
			'id' => $message['id_pm'],
			'css_class' => 'windowbg',
			'member' => &$memberContext[$message['id_member_from']],
			'subject' => $message['subject'],
			'time' => timeformat($message['msgtime']),
			'timestamp' => forum_time(true, $message['msgtime']),
			'counter' => $counter,
			'body' => $message['body'],
			'recipients' => &$recipients[$message['id_pm']],
			'number_recipients' => count($recipients[$message['id_pm']]['to']),
			'labels' => &$context['message_labels'][$message['id_pm']],
			'fully_labeled' => count($context['message_labels'][$message['id_pm']]) == count($context['labels']),
			'is_replied_to' => &$context['message_replied'][$message['id_pm']],
			'is_unread' => &$context['message_unread'][$message['id_pm']],
			'is_selected' => !empty($temp_pm_selected) && in_array($message['id_pm'], $temp_pm_selected),
			'is_message_author' => $message['id_member_from'] == $user_info['id'],
			'can_see_ip' => allowedTo('moderate_forum'),
			'labels' => $context['message_labels'][$message['id_pm_head']] ?? [],
		];

		$output['member']['custom_fields'] = [];
		foreach ($output['member']['characters'] as $character)
		{
			if ($character['is_main'])
			{
				$output['member']['custom_fields'] = $character['custom_fields'];
			}
		}

		if ($context['can_send_pm'])
		{
			$output += [
				'report_link' => $scripturl . '?action=pm;sa=report;l=' . $context['current_label_id'] . ';pmsg=' . $message['id_pm'],
				'report_title' => $txt['pm_report_to_admin'],
			];

			if (!$output['member']['is_guest'])
			{
				if ($output['number_recipients'] > 1)
				{
					$output['quickbuttons']['reply_to_all'] = [
						'url' => $scripturl . '?action=pm;sa=send;pmsg=' . $message['id_pm'] . ';quote;u=all',
						'class' => 'main_icons reply_all_button',
						'label' => $txt['reply_to_all'],
					];
				}

				$output['quickbuttons']['reply'] = [
					'url' => $scripturl . '?action=pm;sa=send;pmsg=' . $message['id_pm'] . ';u=' . $output['member']['id'],
					'class' => 'main_icons reply_button',
					'label' => $txt['reply'],
				];

				$output['quickbuttons']['quote'] = [
					'url' => $scripturl . '?action=pm;sa=send;pmsg=' . $message['id_pm'] . ';quote' . ($context['folder'] != 'sent' ? ';u=' . $output['member']['id'] : ''),
					'class' => 'main_icons quote',
					'label' => $txt['quote_action'],
				];
			}
			else
			{
				$output['quickbuttons']['replyquote'] = [
					'url' => $scripturl . '?action=pm;sa=send;pmsg=' . $message['id_pm'] . ';quote',
					'class' => 'main_icons quote',
					'label' => $txt['reply_quote'],
				];
			}
		}

		$counter++;

		call_integration_hook('integrate_prepare_pm_context', [&$output, &$message, $counter]);

		return $output;
	}
}
