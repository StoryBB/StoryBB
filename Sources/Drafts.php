<?php

/**
 * This file contains all the functions that allow for the saving,
 * retrieving, deleting and settings for the drafts function.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

// @todo fix this
loadLanguage('Drafts');

/**
 * Saves a post draft in the user_drafts table
 * The core draft feature must be enabled, as well as the post draft option
 * Determines if this is a new or an existing draft
 * Returns errors in $post_errors for display in the template
 *
 * @param string[] $post_errors Any errors encountered trying to save this draft
 * @return boolean Always returns true
 */
function SaveDraft(&$post_errors)
{
	global $context, $user_info, $smcFunc, $modSettings, $board;

	// can you be, should you be ... here?
	if (empty($modSettings['drafts_post_enabled']) || !allowedTo('post_draft') || !isset($_POST['save_draft']) || !isset($_POST['id_draft']))
		return false;

	// read in what they sent us, if anything
	$id_draft = (int) $_POST['id_draft'];
	$draft_info = ReadDraft($id_draft);

	// A draft has been saved less than 5 seconds ago, let's not do the autosave again
	if (isset($_REQUEST['xml']) && !empty($draft_info['poster_time']) && time() < $draft_info['poster_time'] + 5)
	{
		$context['draft_saved_on'] = $draft_info['poster_time'];

		// since we were called from the autosave function, send something back
		if (!empty($id_draft))
			XmlDraft($id_draft);

		return true;
	}

	if (!isset($_POST['message']))
		$_POST['message'] = isset($_POST['quickReply']) ? $_POST['quickReply'] : '';

	// prepare any data from the form
	$topic_id = empty($_REQUEST['topic']) ? 0 : (int) $_REQUEST['topic'];
	$draft['smileys_enabled'] = isset($_POST['ns']) ? (int) $_POST['ns'] : 0;
	$draft['locked'] = isset($_POST['lock']) ? (int) $_POST['lock'] : 0;
	$draft['sticky'] = isset($_POST['sticky']) ? (int) $_POST['sticky'] : 0;
	$draft['subject'] = strtr(StringLibrary::escape($_POST['subject']), ["\r" => '', "\n" => '', "\t" => '']);
	$draft['body'] = StringLibrary::escape($_POST['message'], ENT_QUOTES);

	// message and subject still need a bit more work
	preparsecode($draft['body']);
	if (StringLibrary::strlen($draft['subject']) > 100)
		$draft['subject'] = StringLibrary::substr($draft['subject'], 0, 100);

	// Modifying an existing draft, like hitting the save draft button or autosave enabled?
	if (!empty($id_draft) && !empty($draft_info))
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}user_drafts
			SET
				id_topic = {int:id_topic},
				id_board = {int:id_board},
				poster_time = {int:poster_time},
				subject = {string:subject},
				smileys_enabled = {int:smileys_enabled},
				body = {string:body},
				locked = {int:locked},
				is_sticky = {int:is_sticky}
			WHERE id_draft = {int:id_draft}',
			[
				'id_topic' => $topic_id,
				'id_board' => $board,
				'poster_time' => time(),
				'subject' => $draft['subject'],
				'smileys_enabled' => (int) $draft['smileys_enabled'],
				'body' => $draft['body'],
				'locked' => $draft['locked'],
				'is_sticky' => $draft['sticky'],
				'id_draft' => $id_draft,
			]
		);

		// some items to return to the form
		$context['draft_saved'] = true;
		$context['id_draft'] = $id_draft;

		// cleanup
		unset($_POST['save_draft']);
	}
	// otherwise creating a new draft
	else
	{
		$id_draft = $smcFunc['db']->insert('',
			'{db_prefix}user_drafts',
			[
				'id_topic' => 'int',
				'id_board' => 'int',
				'type' => 'int',
				'poster_time' => 'int',
				'id_member' => 'int',
				'subject' => 'string-255',
				'smileys_enabled' => 'int',
				'body' => (!empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] > 65534 ? 'string-' . $modSettings['max_messageLength'] : 'string-65534'),
				'locked' => 'int',
				'is_sticky' => 'int'
			],
			[
				$topic_id,
				$board,
				0,
				time(),
				$user_info['id'],
				$draft['subject'],
				$draft['smileys_enabled'],
				$draft['body'],
				$draft['locked'],
				$draft['sticky']
			],
			[
				'id_draft'
			],
			1
		);

		// everything go as expected?
		if (!empty($id_draft))
		{
			$context['draft_saved'] = true;
			$context['id_draft'] = $id_draft;
		}
		else
			$post_errors[] = 'draft_not_saved';

		// cleanup
		unset($_POST['save_draft']);
	}

	// if we were called from the autosave function, send something back
	if (!empty($id_draft) && isset($_REQUEST['xml']) && (!in_array('session_timeout', $post_errors)))
	{
		$context['draft_saved_on'] = time();
		XmlDraft($id_draft);
	}

	return true;
}

/**
 * Saves a PM draft in the user_drafts table
 * The core draft feature must be enabled, as well as the pm draft option
 * Determines if this is a new or and update to an existing pm draft
 *
 * @param string $post_errors A string of info about errors encountered trying to save this draft
 * @param array $recipientList An array of data about who this PM is being sent to
 * @return boolean false if you can't save the draft, true if we're doing this via XML more than 5 seconds after the last save, nothing otherwise
 */
function SavePMDraft(&$post_errors, $recipientList)
{
	global $context, $user_info, $smcFunc, $modSettings;

	// PM survey says ... can you stay or must you go
	if (empty($modSettings['drafts_pm_enabled']) || !allowedTo('pm_draft') || !isset($_POST['save_draft']))
		return false;

	// read in what you sent us
	$id_pm_draft = (int) $_POST['id_pm_draft'];
	$draft_info = ReadDraft($id_pm_draft, 1);

	// 5 seconds is the same limit we have for posting
	if (isset($_REQUEST['xml']) && !empty($draft_info['poster_time']) && time() < $draft_info['poster_time'] + 5)
	{
		$context['draft_saved_on'] = $draft_info['poster_time'];

		// Send something back to the javascript caller
		if (!empty($id_draft))
			XmlDraft($id_draft);

		return true;
	}

	// determine who this is being sent to
	if (isset($_REQUEST['xml']))
	{
		$recipientList['to'] = isset($_POST['recipient_to']) ? explode(',', $_POST['recipient_to']) : [];
		$recipientList['bcc'] = isset($_POST['recipient_bcc']) ? explode(',', $_POST['recipient_bcc']) : [];
	}
	elseif (!empty($draft_info['to_list']) && empty($recipientList))
		$recipientList = sbb_json_decode($draft_info['to_list'], true);

	// prepare the data we got from the form
	$reply_id = empty($_POST['replied_to']) ? 0 : (int) $_POST['replied_to'];
	$draft['body'] = StringLibrary::escape($_POST['message'], ENT_QUOTES);
	$draft['subject'] = strtr(StringLibrary::escape($_POST['subject']), ["\r" => '', "\n" => '', "\t" => '']);

	// message and subject always need a bit more work
	preparsecode($draft['body']);
	if (StringLibrary::strlen($draft['subject']) > 100)
		$draft['subject'] = StringLibrary::substr($draft['subject'], 0, 100);

	// Modifying an existing PM draft?
	if (!empty($id_pm_draft) && !empty($draft_info))
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}user_drafts
			SET id_reply = {int:id_reply},
				type = {int:type},
				poster_time = {int:poster_time},
				subject = {string:subject},
				body = {string:body},
				to_list = {string:to_list}
			WHERE id_draft = {int:id_pm_draft}
			LIMIT 1',
			[
				'id_reply' => $reply_id,
				'type' => 1,
				'poster_time' => time(),
				'subject' => $draft['subject'],
				'body' => $draft['body'],
				'id_pm_draft' => $id_pm_draft,
				'to_list' => json_encode($recipientList),
			]
		);

		// some items to return to the form
		$context['draft_saved'] = true;
		$context['id_pm_draft'] = $id_pm_draft;
	}
	// otherwise creating a new PM draft.
	else
	{
		$id_pm_draft = $smcFunc['db']->insert('',
			'{db_prefix}user_drafts',
			[
				'id_reply' => 'int',
				'type' => 'int',
				'poster_time' => 'int',
				'id_member' => 'int',
				'subject' => 'string-255',
				'body' => 'string-65534',
				'to_list' => 'string-255',
			],
			[
				$reply_id,
				1,
				time(),
				$user_info['id'],
				$draft['subject'],
				$draft['body'],
				json_encode($recipientList),
			],
			[
				'id_draft'
			],
			1
		);

		// everything go as expected, if not toss back an error
		if (!empty($id_pm_draft))
		{
			$context['draft_saved'] = true;
			$context['id_pm_draft'] = $id_pm_draft;
		}
		else
			$post_errors[] = 'draft_not_saved';
	}

	// if we were called from the autosave function, send something back
	if (!empty($id_pm_draft) && isset($_REQUEST['xml']) && !in_array('session_timeout', $post_errors))
	{
		$context['draft_saved_on'] = time();
		XmlDraft($id_pm_draft);
	}
}

/**
 * Reads a draft in from the user_drafts table
 * Validates that the draft is the user''s draft
 * Optionally loads the draft in to context or superglobal for loading in to the form
 *
 * @param int $id_draft ID of the draft to load
 * @param int $type Type of draft - 0 for post or 1 for PM
 * @param boolean $check Validate that this draft belongs to the current user
 * @param boolean $load Whether or not to load the data into variables for use on a form
 * @return boolean|array False if the data couldn't be loaded, true if it's a PM draft or an array of info about the draft if it's a post draft
 */
function ReadDraft($id_draft, $type = 0, $check = true, $load = false)
{
	global $context, $user_info, $smcFunc, $modSettings;

	// like purell always clean to be sure
	$id_draft = (int) $id_draft;
	$type = (int) $type;

	// nothing to read, nothing to do
	if (empty($id_draft))
		return false;

	// load in this draft from the DB
	$request = $smcFunc['db']->query('', '
		SELECT is_sticky, locked, smileys_enabled, body , subject,
			id_board, id_draft, id_reply, to_list
		FROM {db_prefix}user_drafts
		WHERE id_draft = {int:id_draft}' . ($check ? '
			AND id_member = {int:id_member}' : '') . '
			AND type = {int:type}' . (!empty($modSettings['drafts_keep_days']) ? '
			AND poster_time > {int:time}' : '') . '
		LIMIT 1',
		[
			'id_member' => $user_info['id'],
			'id_draft' => $id_draft,
			'type' => $type,
			'time' => (!empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0),
		]
	);

	// no results?
	if (!$smcFunc['db']->num_rows($request))
		return false;

	// load up the data
	$draft_info = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	// Load it up for the templates as well
	if (!empty($load))
	{
		if ($type === 0)
		{
			// a standard post draft?
			$context['sticky'] = !empty($draft_info['is_sticky']) ? $draft_info['is_sticky'] : '';
			$context['locked'] = !empty($draft_info['locked']) ? $draft_info['locked'] : '';
			$context['use_smileys'] = !empty($draft_info['smileys_enabled']) ? true : false;
			$context['message'] = !empty($draft_info['body']) ? str_replace('<br>', "\n", un_htmlspecialchars(stripslashes($draft_info['body']))) : '';
			$context['subject'] = !empty($draft_info['subject']) ? stripslashes($draft_info['subject']) : '';
			$context['board'] = !empty($draft_info['id_board']) ? $draft_info['id_board'] : '';
			$context['id_draft'] = !empty($draft_info['id_draft']) ? $draft_info['id_draft'] : 0;
		}
		elseif ($type === 1)
		{
			// one of those pm drafts? then set it up like we have an error
			$_REQUEST['subject'] = !empty($draft_info['subject']) ? stripslashes($draft_info['subject']) : '';
			$_REQUEST['message'] = !empty($draft_info['body']) ? str_replace('<br>', "\n", un_htmlspecialchars(stripslashes($draft_info['body']))) : '';
			$_REQUEST['replied_to'] = !empty($draft_info['id_reply']) ? $draft_info['id_reply'] : 0;
			$context['id_pm_draft'] = !empty($draft_info['id_draft']) ? $draft_info['id_draft'] : 0;
			$recipients = sbb_json_decode($draft_info['to_list'], true);

			// make sure we only have integers in this array
			$recipients['to'] = array_map('intval', $recipients['to']);
			$recipients['bcc'] = array_map('intval', $recipients['bcc']);

			// pretend we messed up to populate the pm message form
			messagePostError([], [], $recipients);
			return true;
		}
	}

	return $draft_info;
}

/**
 * Deletes one or many drafts from the DB
 * Validates the drafts are from the user
 * is supplied an array of drafts will attempt to remove all of them
 *
 * @param int $id_draft The ID of the draft to delete
 * @param boolean $check Whether or not to check that the draft belongs to the current user
 * @return boolean False if it couldn't be deleted (doesn't return anything otherwise)
 */
function DeleteDraft($id_draft, $check = true)
{
	global $user_info, $smcFunc;

	// Only a single draft.
	if (is_numeric($id_draft))
		$id_draft = [$id_draft];

	// can't delete nothing
	if (empty($id_draft) || ($check && empty($user_info['id'])))
		return false;

	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}user_drafts
		WHERE id_draft IN ({array_int:id_draft})' . ($check ? '
			AND  id_member = {int:id_member}' : ''),
		[
			'id_draft' => $id_draft,
			'id_member' => empty($user_info['id']) ? -1 : $user_info['id'],
		]
	);
}

/**
 * Loads in a group of drafts for the user of a given type (0/posts, 1/pm's)
 * loads a specific draft for forum use if selected.
 * Used in the posting screens to allow draft selection
 * Will load a draft if selected is supplied via post
 *
 * @param int $member_id ID of the member to show drafts for
 * @param boolean|integer If $type is 1, this can be set to only load drafts for posts in the specific topic
 * @param int $draft_type The type of drafts to show - 0 for post drafts, 1 for PM drafts
 * @return boolean False if the drafts couldn't be loaded, nothing otherwise
 */
function ShowDrafts($member_id, $topic = false, $draft_type = 0)
{
	global $smcFunc, $scripturl, $context, $txt, $modSettings;

	// Permissions
	if (($draft_type === 0 && empty($context['drafts_save'])) || ($draft_type === 1 && empty($context['drafts_pm_save'])) || empty($member_id))
		return false;

	$context['drafts'] = [];

	// has a specific draft has been selected?  Load it up if there is not a message already in the editor
	if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message']))
		ReadDraft((int) $_REQUEST['id_draft'], $draft_type, true, true);

	// load the drafts this user has available
	$request = $smcFunc['db']->query('', '
		SELECT subject, poster_time, id_board, id_topic, id_draft
		FROM {db_prefix}user_drafts
		WHERE id_member = {int:id_member}' . ((!empty($topic) && empty($draft_type)) ? '
			AND id_topic = {int:id_topic}' : (!empty($topic) ? '
			AND id_reply = {int:id_topic}' : '')) . '
			AND type = {int:draft_type}' . (!empty($modSettings['drafts_keep_days']) ? '
			AND poster_time > {int:time}' : '') . '
		ORDER BY poster_time DESC',
		[
			'id_member' => $member_id,
			'id_topic' => (int) $topic,
			'draft_type' => $draft_type,
			'time' => (!empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0),
		]
	);

	// add them to the draft array for display
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (empty($row['subject']))
			$row['subject'] = $txt['no_subject'];

		// Post drafts
		if ($draft_type === 0)
		{
			$tmp_subject = shorten_subject(stripslashes($row['subject']), 24); 
			$context['drafts'][] = [
				'subject' => censorText($tmp_subject),
				'poster_time' => timeformat($row['poster_time']),
				'link' => '<a href="' . $scripturl . '?action=post;board=' . $row['id_board'] . ';' . (!empty($row['id_topic']) ? 'topic=' . $row['id_topic'] . '.0;' : '') . 'id_draft=' . $row['id_draft'] . '">' . $row['subject'] . '</a>',
			];
		}
		// PM drafts
		elseif ($draft_type === 1)
		{
			$tmp_subject = shorten_subject(stripslashes($row['subject']), 24);
			$context['drafts'][] = [
				'subject' => censorText($tmp_subject),
				'poster_time' => timeformat($row['poster_time']),
				'link' => '<a href="' . $scripturl . '?action=pm;sa=send;id_draft=' . $row['id_draft'] . '">' . (!empty($row['subject']) ? $row['subject'] : $txt['drafts_none']) . '</a>',
			];
		}
	}
	$smcFunc['db']->free_result($request);
}

/**
 * Returns an xml response to an autosave ajax request
 * provides the id of the draft saved and the time it was saved
 *
 * @param int $id_draft The draft's ID to return to the user interface
 */
function XmlDraft($id_draft)
{
	global $txt, $context;

	header('Content-Type: text/xml; charset=UTF-8');

	echo '<?xml version="1.0" encoding="UTF-8"?>
	<drafts>
		<draft id="', $id_draft, '"><![CDATA[', $txt['draft_saved_on'], ': ', timeformat($context['draft_saved_on']), ']]></draft>
	</drafts>';

	obExit(false);
}

/**
 * Show all PM drafts of the current user
 * Uses the showpmdraft template
 * Allows for the deleting and loading/editing of drafts
 *
 * @param int $memID The member whose drafts should be loaded
 */
function showPMDrafts($memID = -1)
{
	global $txt, $user_info, $scripturl, $modSettings, $context, $smcFunc, $options;

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
				'id_member' => $memID,
				'draft_type' => $draft_type,
			]
		);

		// now redirect back to the list
		redirectexit('action=pm;sa=showpmdrafts;start=' . $start);
	}

	// perhaps a draft was selected for editing? if so pass this off
	if (!empty($_REQUEST['id_draft']) && !empty($context['drafts_pm_save']) && $memID == $user_info['id'])
	{
		checkSession('get');
		$id_draft = (int) $_REQUEST['id_draft'];
		redirectexit('action=pm;sa=send;id_draft=' . $id_draft);
	}

	// Default to 10.
	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = 10;

	// Get the count of applicable drafts
	$request = $smcFunc['db']->query('', '
		SELECT COUNT(id_draft)
		FROM {db_prefix}user_drafts
		WHERE id_member = {int:id_member}
			AND type={int:draft_type}' . (!empty($modSettings['drafts_keep_days']) ? '
			AND poster_time > {int:time}' : ''),
		[
			'id_member' => $memID,
			'draft_type' => $draft_type,
			'time' => (!empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0),
		]
	);
	list ($msgCount) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
	$maxIndex = $maxPerPage;

	// Make sure the starting place makes sense and construct our friend the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=showpmdrafts', $context['start'], $msgCount, $maxIndex);
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
			'current_member' => $memID,
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
		$context['drafts'] = array_reverse($context['drafts'], true);

	// off to the template we go
	$context['page_title'] = $txt['drafts'];
	$context['sub_template'] = 'personal_message_drafts';
	$context['linktree'][] = [
		'url' => $scripturl . '?action=pm;sa=showpmdrafts',
		'name' => $txt['drafts'],
	];
}
