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

use StoryBB\StringLibrary;
use StoryBB\Helper\Parser;

class ShowDrafts extends AbstractPMController
{
	public function display_action()
	{
		global $txt, $user_info, $scripturl, $modSettings, $context, $smcFunc, $options;

		loadLanguage('Drafts');

		// init
		$draft_type = 1;

		// If just deleting a draft, do it and then redirect back.
		if (!empty($_REQUEST['delete']))
		{
			checkSession('get');
			$id_delete = (int) $_REQUEST['delete'];
			$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}user_drafts
				WHERE id_draft = {int:id_draft}
					AND id_member = {int:id_member}
					AND type = {int:draft_type}
				LIMIT 1',
				[
					'id_draft' => $id_delete,
					'id_member' => $user_info['id'],
					'draft_type' => $draft_type,
				]
			);

			// now redirect back to the list
			redirectexit('action=pm;sa=drafts;start=' . $start);
		}

		// perhaps a draft was selected for editing? if so pass this off
		if (!empty($_REQUEST['id_draft']) && !empty($context['drafts_pm_save']))
		{
			checkSession('get');
			$id_draft = (int) $_REQUEST['id_draft'];
			redirectexit('action=pm;sa=send;id_draft=' . $id_draft);
		}

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		{
			$_REQUEST['viewscount'] = 10;
		}

		// Get the count of applicable drafts
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(id_draft)
			FROM {db_prefix}user_drafts
			WHERE id_member = {int:id_member}
				AND type={int:draft_type}' . (!empty($modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : ''),
			[
				'id_member' => $user_info['id'],
				'draft_type' => $draft_type,
				'time' => (!empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0),
			]
		);
		list ($msgCount) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		$maxIndex = $maxPerPage;

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=drafts', $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the total for better performance.
		$start = $context['start'];
		$reverse = $_REQUEST['start'] > $msgCount / 2;
		if ($reverse)
		{
			$maxIndex = $msgCount < $context['start'] + $maxPerPage + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : $maxPerPage;
			$start = $msgCount < $context['start'] + $maxPerPage + 1 || $msgCount < $context['start'] + $maxPerPage ? 0 : $msgCount - $context['start'] - $maxPerPage;
		}

		// Load in this user's PM drafts
		$request = $smcFunc['db']->query('', '
			SELECT
				ud.id_member, ud.id_draft, ud.body, ud.subject, ud.poster_time, ud.id_reply, ud.to_list
			FROM {db_prefix}user_drafts AS ud
			WHERE ud.id_member = {int:current_member}
				AND type = {int:draft_type}' . (!empty($modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : '') . '
			ORDER BY ud.id_draft ' . ($reverse ? 'ASC' : 'DESC') . '
			LIMIT {int:start}, {int:max}',
			[
				'current_member' => $user_info['id'],
				'draft_type' => $draft_type,
				'time' => (!empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0),
				'start' => $start,
				'max' => $maxIndex,
			]
		);

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Censor....
			if (empty($row['body']))
				$row['body'] = '';

			$row['subject'] = StringLibrary::htmltrim($row['subject']);
			if (empty($row['subject']))
				$row['subject'] = $txt['no_subject'];

			censorText($row['body']);
			censorText($row['subject']);

			// BBC-ilize the message.
			$row['body'] = Parser::parse_bbc($row['body'], true, 'draft' . $row['id_draft']);

			// Have they provide who this will go to?
			$recipients = [
				'to' => [],
				'bcc' => [],
			];
			$recipient_ids = (!empty($row['to_list'])) ? sbb_json_decode($row['to_list'], true) : [];

			// @todo ... this is a bit ugly since it runs an extra query for every message, do we want this?
			// at least its only for draft PM's and only the user can see them ... so not heavily used .. still
			if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc']))
			{
				$recipient_ids['to'] = array_map('intval', $recipient_ids['to']);
				$recipient_ids['bcc'] = array_map('intval', $recipient_ids['bcc']);
				$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

				$request_2 = $smcFunc['db']->query('', '
					SELECT id_member, real_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:member_list})',
					[
						'member_list' => $allRecipients,
					]
				);
				while ($result = $smcFunc['db']->fetch_assoc($request_2))
				{
					$recipientType = in_array($result['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
					$recipients[$recipientType][] = $result['real_name'];
				}
				$smcFunc['db']->free_result($request_2);
			}

			// Add the items to the array for template use
			$context['drafts'][$counter += $reverse ? -1 : 1] = [
				'body' => $row['body'],
				'counter' => $counter,
				'subject' => $row['subject'],
				'time' => timeformat($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'id_draft' => $row['id_draft'],
				'recipients' => $recipients,
				'age' => floor((time() - $row['poster_time']) / 86400),
				'remaining' => (!empty($modSettings['drafts_keep_days']) ? floor($modSettings['drafts_keep_days'] - ((time() - $row['poster_time']) / 86400)) : 0),
				'days_ago_string' => numeric_context('days_ago', floor((time() - $row['poster_time']) / 86400)),
			];
		}
		$smcFunc['db']->free_result($request);

		// if the drafts were retrieved in reverse order, then put them in the right order again.
		if ($reverse)
		{
			$context['drafts'] = array_reverse($context['drafts'], true);
		}

		// off to the template we go
		$context['page_title'] = $txt['drafts'];
		$context['sub_template'] = 'personal_message_drafts';
		$context['linktree'][] = [
			'url' => $scripturl . '?action=pm;sa=drafts',
			'name' => $txt['drafts'],
		];
	}
}
