<?php

/**
 * This file is mainly meant for controlling the actions related to personal
 * messages. It allows viewing, sending, deleting, and marking personal
 * messages. For compatibility reasons, they are often called "instant messages".
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Autocomplete;
use StoryBB\Helper\Navigation\Navigation;
use StoryBB\Helper\Navigation\Item as NavItem;
use StoryBB\Helper\Navigation\Section as NavSection;
use StoryBB\Helper\Navigation\Tab as NavTab;
use StoryBB\Helper\Navigation\HiddenTab as NavTabHidden;
use StoryBB\Helper\Parser;
use StoryBB\Helper\PM;
use StoryBB\Helper\Verification;
use StoryBB\StringLibrary;
use StoryBB\Template;

function MessageMain()
{
	global $txt, $context, $modSettings, $sourcedir, $user_info, $smcFunc;

	is_not_guest();
	isAllowedTo('pm_read');

	loadLanguage('PersonalMessage');

	PM::load_labels();

	$context['can_issue_warning'] = allowedTo('issue_warning') && $modSettings['warning_settings'][0] == 1;

	// Are PM drafts enabled?
	$context['drafts_pm_save'] = !empty($modSettings['drafts_pm_enabled']);
	$context['drafts_autosave'] = !empty($context['drafts_pm_save']) && !empty($modSettings['drafts_autosave_enabled']);

	// Load up the members maximum message capacity.
	if ($user_info['is_admin'])
		$context['message_limit'] = 0;
	elseif (($context['message_limit'] = cache_get_data('msgLimit:' . $user_info['id'], 360)) === null)
	{
		// @todo Why do we do this?  It seems like if they have any limit we should use it.
		$request = $smcFunc['db']->query('', '
			SELECT MAX(max_messages) AS top_limit, MIN(max_messages) AS bottom_limit
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:users_groups})
				AND is_character = {int:is_not_character}',
			[
				'users_groups' => $user_info['groups'],
				'is_not_character' => 0,
			]
		);
		list ($maxMessage, $minMessage) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$context['message_limit'] = $minMessage == 0 ? 0 : $maxMessage;

		// Save us doing it again!
		cache_put_data('msgLimit:' . $user_info['id'], $context['message_limit'], 360);
	}

	// Prepare the context for the capacity bar.
	if (!empty($context['message_limit']))
	{
		$bar = ($user_info['messages'] * 100) / $context['message_limit'];

		$context['limit_bar'] = [
			'messages' => $user_info['messages'],
			'allowed' => $context['message_limit'],
			'percent' => $bar,
			'bar' => min(100, (int) $bar),
			'text' => sprintf($txt['pm_currently_using'], $user_info['messages'], round($bar, 1)),
		];
	}

	require_once($sourcedir . '/Subs-Post.php');

	$navigation = new Navigation;

	$mailbox = $navigation->add_tab(new NavTab('mailbox', $txt['pmtab_mailbox']));
	$section = $mailbox->add_section(new NavSection('mailbox', $txt['pmtab_mailbox']));
	$section->add_item(new NavItem(
		'inbox',
		$txt['inbox'],
		['f' => 'inbox'],
		'StoryBB\\Controller\\PM\\Folder',
		['pm_read']
	));
	$section->add_item(new NavItem(
		'sent_items',
		$txt['sent_items'],
		['f' => 'sent'],
		'StoryBB\\Controller\\PM\\Folder',
		['pm_read'] // This shouldn't be pm_send; no point holding your previous messages ransom just because you might not be able to send 'right now'.
	));
	$section->add_item(new NavItem(
		'compose',
		$txt['compose'],
		['sa' => 'send'],
		'StoryBB\\Controller\\PM\\Compose',
		['pm_send']
	));
	$section->add_item((new NavItem(
		'drafts',
		$txt['show_drafts'],
		['sa' => 'drafts'],
		'StoryBB\\Controller\\PM\\ShowDrafts',
		['pm_send']
	))->is_enabled(function () use ($modSettings) {
		return !empty($modSettings['drafts_pm_enabled']);
	}));

	if ($context['currently_using_labels'])
	{
		$section = $mailbox->add_section(new NavSection('labels', $txt['pm_labels']));
		foreach ($context['labels'] as $id_label => $label)
		{
			if ($id_label != -1)
			{
				$section->add_item(new NavItem(
					'label' . $id_label,
					$label['name'],
					['l' => $id_label],
					'StoryBB\\Controller\\PM\\Folder',
					['pm_read']
				));
			}
		}
	}

	$management = $navigation->add_tab(new NavTab('management', $txt['pmtab_management']));
	$section = $management->add_section(new NavSection('management', $txt['pmtab_management']));
	$section->add_item(new NavItem(
		'search_pms',
		$txt['pm_search_bar_title'],
		['sa' => 'search'],
		'StoryBB\\Controller\\PM\\Search',
		['pm_read']
	));
	$section->add_item(new NavItem(
		'prune_pms',
		$txt['pm_prune'],
		['sa' => 'prune'],
		'StoryBB\\Controller\\PM\\Prune',
		['pm_read']
	));
	$section->add_item(new NavItem(
		'manage_labels',
		$txt['pm_manage_labels'],
		['sa' => 'manage_labels'],
		'StoryBB\\Controller\\PM\\ManageLabels',
		['pm_read']
	));
	$section->add_item(new NavItem(
		'manage_rules',
		$txt['pm_manage_rules'],
		['sa' => 'manage_rules'],
		'StoryBB\\Controller\\PM\\ManageRules',
		['pm_read']
	));
	$section->add_item(new NavItem(
		'settings',
		$txt['pm_settings_short'],
		['sa' => 'settings'],
		'StoryBB\\Controller\\PM\\Settings',
		['pm_read']
	));

	$hidden = $navigation->add_tab(new NavTabHidden('hidden'));
	$section = $hidden->add_section(new NavSection('hidden', ''));
	$section->add_item(new NavItem(
		'popup',
		'',
		['sa' => 'popup'],
		'StoryBB\\Controller\\PM\\Popup',
		['pm_read']
	));
	$section->add_item(new NavItem(
		'pmactions',
		'',
		['sa' => 'pmactions'],
		'StoryBB\\Controller\\PM\\Actions',
		['pm_read']
	));
	$section->add_item(new NavItem(
		'pmactions',
		'',
		['sa' => 'report'],
		'StoryBB\\Controller\\PM\\Report',
		['pm_read']
	));

		// Set the template for this area and add the profile layer.
	if (!isset($_REQUEST['xml']))
		StoryBB\Template::add_layer('pm');

	$navigation->dispatch($_REQUEST);
	$context['navigation'] = $navigation->export(['action' => 'pm']);
}

/**
 * An error in the message...
 *
 * @param array $error_types An array of strings indicating which type of errors occurred
 * @param array $named_recipients
 * @param $recipient_ids
 */
function messagePostError($error_types, $named_recipients, $recipient_ids = [])
{
	global $txt, $context, $scripturl, $modSettings;
	global $smcFunc, $user_info, $sourcedir;

	if (!isset($_REQUEST['xml']))
	{
		$context['sub_template'] = 'personal_message_send';
	}
	else
	{
		Template::remove_layer('sidebar_navigation');
		Template::add_helper(['cleanXml' => 'cleanXml']);
		Template::set_layout('xml');
		$context['sub_template'] = 'xml_pm_preview';
	}

	$context['page_title'] = $txt['send_message'];

	// Got some known members?
	$context['recipients'] = [
		'to' => [],
	];
	if (!empty($recipient_ids['to']))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})',
			[
				'member_list' => $recipient_ids['to'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['recipients']['to'][] = [
				'id' => $row['id_member'],
				'name' => $row['real_name'],
			];
		}
		$smcFunc['db']->free_result($request);
	}

	$recipient_ids = array_map(function($x) {
		return $x['id'];
	}, $context['recipients']['to']);
	Autocomplete::init('memberchar', '#to', $modSettings['max_pm_recipients'], $recipient_ids);

	// Set everything up like before....
	$context['subject'] = isset($_REQUEST['subject']) ? StringLibrary::escape($_REQUEST['subject']) : '';
	$context['message'] = isset($_REQUEST['message']) ? str_replace(['  '], ['&nbsp; '], StringLibrary::escape($_REQUEST['message'])) : '';
	$context['reply'] = !empty($_REQUEST['replied_to']);

	if ($context['reply'])
	{
		$_REQUEST['replied_to'] = (int) $_REQUEST['replied_to'];

		$request = $smcFunc['db']->query('', '
			SELECT
				id_pm
			FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}
				AND id_member = {int:current_member}
			LIMIT 1',
			[
				'current_member' => $user_info['id'],
				'id_pm' => $_REQUEST['replied_to'],
			]
		);
		$isReceived = $smcFunc['db']->num_rows($request) != 0;

		$request = $smcFunc['db']->query('', '
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:no_id_pm_head} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, COALESCE(mem.id_member, 0) AS id_member,
				COALESCE(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . (!$isReceived ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:replied_to})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:replied_to}' . (!$isReceived ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			[
				'current_member' => $user_info['id'],
				'no_id_pm_head' => 0,
				'replied_to' => $_REQUEST['replied_to'],
			]
		);
		if ($smcFunc['db']->num_rows($request) == 0)
		{
			if (!isset($_REQUEST['xml']))
				fatal_lang_error('pm_not_yours', false);
			else
				$error_types[] = 'pm_not_yours';
		}
		$row_quoted = $smcFunc['db']->fetch_assoc($request);
		$smcFunc['db']->free_result($request);

		censorText($row_quoted['subject']);
		censorText($row_quoted['body']);

		$context['quoted_message'] = [
			'id' => $row_quoted['id_pm'],
			'pm_head' => $row_quoted['pm_head'],
			'member' => [
				'name' => $row_quoted['real_name'],
				'username' => $row_quoted['member_name'],
				'id' => $row_quoted['id_member'],
				'href' => !empty($row_quoted['id_member']) ? $scripturl . '?action=profile;u=' . $row_quoted['id_member'] : '',
				'link' => !empty($row_quoted['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
			],
			'subject' => $row_quoted['subject'],
			'time' => timeformat($row_quoted['msgtime']),
			'timestamp' => forum_time(true, $row_quoted['msgtime']),
			'body' => Parser::parse_bbc($row_quoted['body'], true, 'pm' . $row_quoted['id_pm']),
		];
	}

	// Build the link tree....
	$context['linktree'][] = [
		'url' => $scripturl . '?action=pm;sa=send',
		'name' => $txt['new_message']
	];

	// Set each of the errors for the template.
	loadLanguage('Errors');

	$context['error_type'] = 'minor';

	$context['post_error'] = [
		'messages' => [],
		// @todo error handling: maybe fatal errors can be error_type => serious
		'error_type' => '',
	];

	foreach ($error_types as $error_type)
	{
		$context['post_error'][$error_type] = true;
		if (isset($txt['error_' . $error_type]))
		{
			if ($error_type == 'long_message')
				$txt['error_' . $error_type] = sprintf($txt['error_' . $error_type], $modSettings['max_messageLength']);
			$context['post_error']['messages'][] = $txt['error_' . $error_type];
		}

		// If it's not a minor error flag it as such.
		if (!in_array($error_type, ['new_reply', 'not_approved', 'new_replies', 'old_topic', 'need_qr_verification', 'no_subject']))
			$context['error_type'] = 'serious';
	}

	// We need to load the editor once more.
	require_once($sourcedir . '/Subs-Editor.php');

	// Create it...
	$editorOptions = [
		'id' => 'message',
		'value' => $context['message'],
		'width' => '90%',
		'height' => '250px',
		'labels' => [
			'post_button' => $txt['send_message'],
		],
		'preview_type' => 2,
	];
	create_control_richedit($editorOptions);

	// ... and store the ID again...
	$context['post_box_name'] = $editorOptions['id'];

	// Check whether we need to show the code again.
	$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
	if ($context['require_verification'] && !isset($_REQUEST['xml']))
	{
		$context['visual_verification'] = Verification::get('pm')->id();
	}

	$context['to_value'] = empty($named_recipients['to']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['to']) . '&quot;';

	call_integration_hook('integrate_pm_error');

	// No check for the previous submission is needed.
	checkSubmitOnce('free');

	// Acquire a new form sequence number.
	checkSubmitOnce('register');
}

/**
 * Delete the specified personal messages.
 *
 * @param array|null $personal_messages An array containing the IDs of PMs to delete or null to delete all of them
 * @param string|null $folder Which "folder" to delete PMs from - 'sent' to delete them from the outbox, null or anything else to delete from the inbox
 * @param array|int|null $owner An array of IDs of users whose PMs are being deleted, the ID of a single user or null to use the current user's ID
 */
function deleteMessages($personal_messages, $folder = null, $owner = null)
{
	global $user_info, $smcFunc;

	if ($owner === null)
		$owner = [$user_info['id']];
	elseif (empty($owner))
		return;
	elseif (!is_array($owner))
		$owner = [$owner];

	if ($personal_messages !== null)
	{
		if (empty($personal_messages) || !is_array($personal_messages))
			return;

		foreach ($personal_messages as $index => $delete_id)
			$personal_messages[$index] = (int) $delete_id;

		$where = '
				AND id_pm IN ({array_int:pm_list})';
	}
	else
		$where = '';

	if ($folder == 'sent' || $folder === null)
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}personal_messages
			SET deleted_by_sender = {int:is_deleted}
			WHERE id_member_from IN ({array_int:member_list})
				AND deleted_by_sender = {int:not_deleted}' . $where,
			[
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
			]
		);
	}
	if ($folder != 'sent' || $folder === null)
	{
		// Calculate the number of messages each member's gonna lose...
		$request = $smcFunc['db']->query('', '
			SELECT id_member, COUNT(*) AS num_deleted_messages, CASE WHEN is_read & 1 >= 1 THEN 1 ELSE 0 END AS is_read
			FROM {db_prefix}pm_recipients
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where . '
			GROUP BY id_member, is_read',
			[
				'member_list' => $owner,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
			]
		);
		// ...And update the statistics accordingly - now including unread messages!.
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if ($row['is_read'])
				updateMemberData($row['id_member'], ['instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages']]);
			else
				updateMemberData($row['id_member'], ['instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages'], 'unread_messages' => $where == '' ? 0 : 'unread_messages - ' . $row['num_deleted_messages']]);

			// If this is the current member we need to make their message count correct.
			if ($user_info['id'] == $row['id_member'])
			{
				$user_info['messages'] -= $row['num_deleted_messages'];
				if (!($row['is_read']))
					$user_info['unread_messages'] -= $row['num_deleted_messages'];
			}
		}
		$smcFunc['db']->free_result($request);

		// Do the actual deletion.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}pm_recipients
			SET deleted = {int:is_deleted}
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where,
			[
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
			]
		);

		$labels = [];

		// Get any labels that the owner may have applied to this PM
		// The join is here to ensure we only get labels applied by the specified member(s)
		$get_labels = $smcFunc['db']->query('', '
			SELECT pml.id_label
			FROM {db_prefix}pm_labels AS l
			INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
			WHERE l.id_member IN ({array_int:member_list})' . $where,
			[
				'member_list' => $owner,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($get_labels))
		{
			$labels[] = $row['id_label'];
		}

		$smcFunc['db']->free_result($get_labels);

		if (!empty($labels))
		{
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}pm_labeled_messages
				WHERE id_label IN ({array_int:labels})' . $where,
				[
					'labels' => $labels,
					'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
				]
			);
		}
	}

	// If sender and recipients all have deleted their message, it can be removed.
	$request = $smcFunc['db']->query('', '
		SELECT pm.id_pm AS sender, pmr.id_pm
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.deleted = {int:not_deleted})
		WHERE pm.deleted_by_sender = {int:is_deleted} AND pmr.id_pm is null
			' . str_replace('id_pm', 'pm.id_pm', $where),
		[
			'not_deleted' => 0,
			'is_deleted' => 1,
			'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : [],
		]
	);
	$remove_pms = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$remove_pms[] = $row['sender'];
	$smcFunc['db']->free_result($request);

	if (!empty($remove_pms))
	{
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:pm_list})',
			[
				'pm_list' => $remove_pms,
			]
		);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm IN ({array_int:pm_list})',
			[
				'pm_list' => $remove_pms,
			]
		);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}pm_labeled_messages
			WHERE id_pm IN ({array_int:pm_list})',
			[
				'pm_list' => $remove_pms,
			]
		);
	}

	// Any cached numbers may be wrong now.
	cache_put_data('labelCounts:' . $user_info['id'], null, 720);
}

/**
 * Mark the specified personal messages read.
 *
 * @param array|null $personal_messages An array of PM IDs to mark or null to mark all
 * @param int|null $label The ID of a label. If set, only messages with this label will be marked.
 * @param int|null $owner If owner is set, marks messages owned by that member id
 */
function markMessages($personal_messages = null, $label = null, $owner = null)
{
	global $user_info, $context, $smcFunc;

	if ($owner === null)
		$owner = $user_info['id'];

	$in_inbox = '';

	// Marking all messages with a specific label as read?
	// If we know which PMs we're marking read, then we don't need label info
	if ($personal_messages === null && $label !== null && $label != '-1')
	{
		$personal_messages = [];
		$get_messages = $smcFunc['db']->query('', '
			SELECT id_pm
			FROM {db_prefix}pm_labeled_messages
			WHERE id_label = {int:current_label}',
			[
				'current_label' => $label,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($get_messages))
		{
			$personal_messages[] = $row['id_pm'];
		}

		$smcFunc['db']->free_result($get_messages);
	}
	elseif ($label = '-1')
	{
		// Marking all PMs in your inbox read
		$in_inbox = '
			AND in_inbox = {int:in_inbox}';
	}

	$smcFunc['db']->query('', '
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read | 1
		WHERE id_member = {int:id_member}
			AND NOT (is_read & 1 >= 1)' . ($personal_messages !== null ? '
			AND id_pm IN ({array_int:personal_messages})' : '') . $in_inbox,
		[
			'personal_messages' => $personal_messages,
			'id_member' => $owner,
			'in_inbox' => 1,
		]
	);

	// If something wasn't marked as read, get the number of unread messages remaining.
	if ($smcFunc['db']->affected_rows() > 0)
	{
		if ($owner == $user_info['id'])
		{
			foreach ($context['labels'] as $label)
				$context['labels'][(int) $label['id']]['unread_messages'] = 0;
		}

		$result = $smcFunc['db']->query('', '
			SELECT id_pm, in_inbox, COUNT(*) AS num
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:id_member}
				AND NOT (is_read & 1 >= 1)
				AND deleted = {int:is_not_deleted}
			GROUP BY id_pm, in_inbox',
			[
				'id_member' => $owner,
				'is_not_deleted' => 0,
			]
		);
		$total_unread = 0;
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$total_unread += $row['num'];

			if ($owner != $user_info['id'] || empty($row['id_pm']))
				continue;

			$this_labels = [];

			// Get all the labels
			$result2 = $smcFunc['db']->query('', '
				SELECT pml.id_label
				FROM {db_prefix}pm_labels AS l
					INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
				WHERE l.id_member = {int:id_member}
					AND pml.id_pm = {int:current_pm}',
				[
					'id_member' => $owner,
					'current_pm' => $row['id_pm'],
				]
			);

			while ($row2 = $smcFunc['db']->fetch_assoc($result2))
			{
				$this_labels[] = $row2['id_label'];
			}

			$smcFunc['db']->free_result($result2);

			foreach ($this_labels as $this_label)
				$context['labels'][$this_label]['unread_messages'] += $row['num'];

			if ($row['in_inbox'] == 1)
				$context['labels'][-1]['unread_messages'] += $row['num'];
		}
		$smcFunc['db']->free_result($result);

		// Need to store all this.
		cache_put_data('labelCounts:' . $owner, $context['labels'], 720);
		updateMemberData($owner, ['unread_messages' => $total_unread]);

		// If it was for the current member, reflect this in the $user_info array too.
		if ($owner == $user_info['id'])
			$user_info['unread_messages'] = $total_unread;
	}
}

/**
 * This will apply rules to all unread messages. If all_messages is set will, clearly, do it to all!
 *
 * @param bool $all_messages Whether to apply this to all messages or just unread ones
 */
function ApplyRules($all_messages = false)
{
	global $user_info, $smcFunc, $context, $options;

	// Want this - duh!
	loadRules();

	// No rules?
	if (empty($context['rules']))
		return;

	// Just unread ones?
	$ruleQuery = $all_messages ? '' : ' AND pmr.is_new = 1';

	// @todo Apply all should have timeout protection!
	// Get all the messages that match this.
	$request = $smcFunc['db']->query('', '
		SELECT
			pmr.id_pm, pm.id_member_from, pm.subject, pm.body, mem.id_group
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
		WHERE pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}
			' . $ruleQuery,
		[
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		]
	);
	$actions = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		foreach ($context['rules'] as $rule)
		{
			$match = false;
			// Loop through all the criteria hoping to make a match.
			foreach ($rule['criteria'] as $criterium)
			{
				if (($criterium['t'] == 'mid' && $criterium['v'] == $row['id_member_from']) || ($criterium['t'] == 'gid' && $criterium['v'] == $row['id_group']) || ($criterium['t'] == 'sub' && strpos($row['subject'], $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($row['body'], $criterium['v']) !== false))
					$match = true;
				// If we're adding and one criteria don't match then we stop!
				elseif ($rule['logic'] == 'and')
				{
					$match = false;
					break;
				}
			}

			// If we have a match the rule must be true - act!
			if ($match)
			{
				if ($rule['delete'])
					$actions['deletes'][] = $row['id_pm'];
				else
				{
					foreach ($rule['actions'] as $ruleAction)
					{
						if ($ruleAction['t'] == 'lab')
						{
							// Get a basic pot started!
							if (!isset($actions['labels'][$row['id_pm']]))
								$actions['labels'][$row['id_pm']] = [];
							$actions['labels'][$row['id_pm']][] = $ruleAction['v'];
						}
					}
				}
			}
		}
	}
	$smcFunc['db']->free_result($request);

	// Deletes are easy!
	if (!empty($actions['deletes']))
		deleteMessages($actions['deletes']);

	// Relabel?
	if (!empty($actions['labels']))
	{
		foreach ($actions['labels'] as $pm => $labels)
		{
			// Quickly check each label is valid!
			$realLabels = [];
			foreach ($context['labels'] as $label)
			{
				if (in_array($label['id'], $labels) && $label['id'] != -1 || empty($options['pm_remove_inbox_label']))
				{
					// Make sure this stays in the inbox
					if ($label['id'] == '-1')
					{
						$smcFunc['db']->query('', '
							UPDATE {db_prefix}pm_recipients
							SET in_inbox = {int:in_inbox}
							WHERE id_pm = {int:id_pm}
								AND id_member = {int:current_member}',
							[
								'in_inbox' => 1,
								'id_pm' => $pm,
								'current_member' => $user_info['id'],
							]
						);
					}
					else
					{
						$realLabels[] = $label['id'];
					}
				}
			}

			$inserts = [];
			// Now we insert the label info
			foreach ($realLabels as $a_label)
				$inserts[] = [$pm, $a_label];

			$smcFunc['db']->insert('ignore',
				'{db_prefix}pm_labeled_messages',
				['id_pm' => 'int', 'id_label' => 'int'],
				$inserts,
				[]
			);
		}
	}
}

/**
 * Load up all the rules for the current user.
 *
 * @param bool $reload Whether or not to reload all the rules from the database if $context['rules'] is set
 */
function LoadRules($reload = false)
{
	global $user_info, $context, $smcFunc;

	if (isset($context['rules']) && !$reload)
		return;

	$request = $smcFunc['db']->query('', '
		SELECT
			id_rule, rule_name, criteria, actions, delete_pm, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member = {int:current_member}',
		[
			'current_member' => $user_info['id'],
		]
	);
	$context['rules'] = [];
	// Simply fill in the data!
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$context['rules'][$row['id_rule']] = [
			'id' => $row['id_rule'],
			'name' => $row['rule_name'],
			'criteria' => sbb_json_decode($row['criteria'], true),
			'actions' => sbb_json_decode($row['actions'], true),
			'delete' => $row['delete_pm'],
			'logic' => $row['is_or'] ? 'or' : 'and',
		];

		if ($row['delete_pm'])
			$context['rules'][$row['id_rule']]['actions'][] = ['t' => 'del', 'v' => 1];
	}
	$smcFunc['db']->free_result($request);
}

/**
 * Check if the PM is available to the current user.
 *
 * @param int $pmID The ID of the PM
 * @param string $validFor Which folders this is valud for - can be 'inbox', 'outbox' or 'in_or_outbox'
 * @return boolean Whether the PM is accessible in that folder for the current user
 */
function isAccessiblePM($pmID, $validFor = 'in_or_outbox')
{
	global $user_info, $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT
			pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted} AS valid_for_outbox,
			pmr.id_pm IS NOT NULL AS valid_for_inbox
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.id_member = {int:id_current_member} AND pmr.deleted = {int:not_deleted})
		WHERE pm.id_pm = {int:id_pm}
			AND ((pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted}) OR pmr.id_pm IS NOT NULL)',
		[
			'id_pm' => $pmID,
			'id_current_member' => $user_info['id'],
			'not_deleted' => 0,
		]
	);

	if ($smcFunc['db']->num_rows($request) === 0)
	{
		$smcFunc['db']->free_result($request);
		return false;
	}

	$validationResult = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	switch ($validFor)
	{
		case 'inbox':
			return !empty($validationResult['valid_for_inbox']);
		break;

		case 'outbox':
			return !empty($validationResult['valid_for_outbox']);
		break;

		case 'in_or_outbox':
			return !empty($validationResult['valid_for_inbox']) || !empty($validationResult['valid_for_outbox']);
		break;

		default:
			trigger_error('Undefined validation type given', E_USER_ERROR);
		break;
	}
}
