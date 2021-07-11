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

use StoryBB\Helper\Autocomplete;
use StoryBB\StringLibrary;
use StoryBB\Helper\Parser;
use StoryBB\Helper\Verification;

class Compose extends AbstractPMController
{
	public function display_action()
	{
		global $txt, $sourcedir, $scripturl, $modSettings;
		global $context, $smcFunc, $language, $user_info;

		isAllowedTo('pm_send');

		loadLanguage('PersonalMessage');

		$context['sub_template'] = 'personal_message_send';

		// Extract out the spam settings - cause it's neat.
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// Set the title...
		$context['page_title'] = $txt['send_message'];

		$context['reply'] = isset($_REQUEST['pmsg']) || isset($_REQUEST['quote']);

		// Check whether we've gone over the limit of messages we can send per hour.
		if (!empty($modSettings['pm_posts_per_hour']) && !allowedTo(['admin_forum', 'moderate_forum', 'send_mail']) && $user_info['mod_cache']['bq'] == '0=1' && $user_info['mod_cache']['gq'] == '0=1')
		{
			// How many messages have they sent this last hour?
			$request = $smcFunc['db']->query('', '
				SELECT COUNT(pr.id_pm) AS post_count
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
				WHERE pm.id_member_from = {int:current_member}
					AND pm.msgtime > {int:msgtime}',
				[
					'current_member' => $user_info['id'],
					'msgtime' => time() - 3600,
				]
			);
			list ($postCount) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			if (!empty($postCount) && $postCount >= $modSettings['pm_posts_per_hour'])
				fatal_lang_error('pm_too_many_per_hour', true, [$modSettings['pm_posts_per_hour']]);
		}

		// Quoting/Replying to a message?
		if (!empty($_REQUEST['pmsg']))
		{
			$pmsg = (int) $_REQUEST['pmsg'];

			// Make sure this is yours.
			if (!isAccessiblePM($pmsg))
				fatal_lang_error('no_access', false);

			// Work out whether this is one you've received?
			$request = $smcFunc['db']->query('', '
				SELECT
					id_pm
				FROM {db_prefix}pm_recipients
				WHERE id_pm = {int:id_pm}
					AND id_member = {int:current_member}
				LIMIT 1',
				[
					'current_member' => $user_info['id'],
					'id_pm' => $pmsg,
				]
			);
			$isReceived = $smcFunc['db']->num_rows($request) != 0;
			$smcFunc['db']->free_result($request);

			// Get the quoted message (and make sure you're allowed to see this quote!).
			$request = $smcFunc['db']->query('', '
				SELECT
					pm.id_pm, CASE WHEN pm.id_pm_head = {int:id_pm_head_empty} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
					pm.body, pm.subject, pm.msgtime, mem.member_name, COALESCE(mem.id_member, 0) AS id_member,
					COALESCE(mem.real_name, pm.from_name) AS real_name
				FROM {db_prefix}personal_messages AS pm' . (!$isReceived ? '' : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:id_pm})') . '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_pm = {int:id_pm}' . (!$isReceived ? '
					AND pm.id_member_from = {int:current_member}' : '
					AND pmr.id_member = {int:current_member}') . '
				LIMIT 1',
				[
					'current_member' => $user_info['id'],
					'id_pm_head_empty' => 0,
					'id_pm' => $pmsg,
				]
			);
			if ($smcFunc['db']->num_rows($request) == 0)
				fatal_lang_error('pm_not_yours', false);
			$row_quoted = $smcFunc['db']->fetch_assoc($request);
			$smcFunc['db']->free_result($request);

			// Censor the message.
			censorText($row_quoted['subject']);
			censorText($row_quoted['body']);

			// Add 'Re: ' to it....
			if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
			{
				if ($language === $user_info['language'])
					$context['response_prefix'] = $txt['response_prefix'];
				else
				{
					loadLanguage('General', $language, false);
					$context['response_prefix'] = $txt['response_prefix'];
					loadLanguage('General');
				}
				cache_put_data('response_prefix', $context['response_prefix'], 600);
			}
			$form_subject = $row_quoted['subject'];
			if ($context['reply'] && trim($context['response_prefix']) != '' && StringLibrary::strpos($form_subject, trim($context['response_prefix'])) !== 0)
				$form_subject = $context['response_prefix'] . $form_subject;

			if (isset($_REQUEST['quote']))
			{
				// Remove any nested quotes and <br>...
				$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $row_quoted['body']);
				if (!empty($modSettings['removeNestedQuotes']))
					$form_message = preg_replace(['~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'], '', $form_message);
				if (empty($row_quoted['id_member']))
					$form_message = '[quote author=&quot;' . $row_quoted['real_name'] . '&quot;]' . "\n" . $form_message . "\n" . '[/quote]';
				else
					$form_message = '[quote author=' . $row_quoted['real_name'] . ' link=action=profile;u=' . $row_quoted['id_member'] . ' date=' . $row_quoted['msgtime'] . ']' . "\n" . $form_message . "\n" . '[/quote]';
			}
			else
				$form_message = '';

			// Do the BBC thang on the message.
			$row_quoted['body'] = Parser::parse_bbc($row_quoted['body'], true, 'pm' . $row_quoted['id_pm']);

			// Set up the quoted message array.
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
				'body' => $row_quoted['body']
			];
		}
		else
		{
			$context['quoted_message'] = false;
			$form_subject = '';
			$form_message = '';
		}

		$context['recipients'] = [
			'to' => [],
		];

		// Sending by ID?  Replying to all?  Fetch the real_name(s).
		if (isset($_REQUEST['u']))
		{
			// If the user is replying to all, get all the other members this was sent to..
			if ($_REQUEST['u'] == 'all' && isset($row_quoted))
			{
				// Firstly, to reply to all we clearly already have $row_quoted - so have the original member from.
				if ($row_quoted['id_member'] != $user_info['id'])
					$context['recipients']['to'][] = [
						'id' => $row_quoted['id_member'],
						'name' => StringLibrary::escape($row_quoted['real_name']),
					];

				// Now to get the others.
				$request = $smcFunc['db']->query('', '
					SELECT mem.id_member, mem.real_name
					FROM {db_prefix}pm_recipients AS pmr
						INNER JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
					WHERE pmr.id_pm = {int:id_pm}
						AND pmr.id_member != {int:current_member}',
					[
						'current_member' => $user_info['id'],
						'id_pm' => $pmsg,
					]
				);
				while ($row = $smcFunc['db']->fetch_assoc($request))
					$context['recipients']['to'][] = [
						'id' => $row['id_member'],
						'name' => $row['real_name'],
					];
				$smcFunc['db']->free_result($request);
			}
			else
			{
				$_REQUEST['u'] = explode(',', $_REQUEST['u']);
				foreach ($_REQUEST['u'] as $key => $uID)
					$_REQUEST['u'][$key] = (int) $uID;

				$_REQUEST['u'] = array_unique($_REQUEST['u']);

				$request = $smcFunc['db']->query('', '
					SELECT id_member, real_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:member_list})
					LIMIT {int:limit}',
					[
						'member_list' => $_REQUEST['u'],
						'limit' => count($_REQUEST['u']),
					]
				);
				while ($row = $smcFunc['db']->fetch_assoc($request))
					$context['recipients']['to'][] = [
						'id' => $row['id_member'],
						'name' => $row['real_name'],
					];
				$smcFunc['db']->free_result($request);
			}

			// Get a literal name list in case the user has JavaScript disabled.
			$names = [];
			foreach ($context['recipients']['to'] as $to)
				$names[] = $to['name'];
			$context['to_value'] = empty($names) ? '' : '&quot;' . implode('&quot;, &quot;', $names) . '&quot;';
		}
		else
			$context['to_value'] = '';

		// Set the defaults...
		$context['subject'] = $form_subject;
		$context['message'] = str_replace(['"', '<', '>', '&nbsp;'], ['&quot;', '&lt;', '&gt;', ' '], $form_message);
		$context['post_error'] = [];

		// And build the link tree.
		$context['linktree'][] = [
			'url' => $scripturl . '?action=pm;sa=send',
			'name' => $txt['new_message']
		];

		$modSettings['disable_wysiwyg'] = !empty($modSettings['disable_wysiwyg']) || empty($modSettings['enableBBC']);

		// Generate a list of drafts that they can load in to the editor
		if (!empty($context['drafts_pm_save']))
		{
			require_once($sourcedir . '/Drafts.php');
			$pm_seed = isset($_REQUEST['pmsg']) ? $_REQUEST['pmsg'] : (isset($_REQUEST['quote']) ? $_REQUEST['quote'] : 0);
			ShowDrafts($user_info['id'], $pm_seed, 1);
		}

		$recipient_ids = array_map(function($x) {
			return $x['id'];
		}, $context['recipients']['to']);
		Autocomplete::init('memberchar', '#to', $modSettings['max_pm_recipients'], $recipient_ids);

		// Needed for the WYSIWYG editor.
		require_once($sourcedir . '/Subs-Editor.php');

		// Now create the editor.
		$editorOptions = [
			'id' => 'message',
			'value' => $context['message'],
			'height' => '250px',
			'width' => '100%',
			'labels' => [
				'post_button' => $txt['send_message'],
			],
			'preview_type' => 2,
			'required' => true,
		];
		create_control_richedit($editorOptions);

		// Store the ID for old compatibility.
		$context['post_box_name'] = $editorOptions['id'];

		$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
		if ($context['require_verification'])
		{
			$context['visual_verification'] = Verification::get('pm')->id();
		}

		call_integration_hook('integrate_pm_post');

		// Register this form and get a sequence number in $context.
		checkSubmitOnce('register');
	}

	public function post_action()
	{
		global $txt, $context, $sourcedir;
		global $user_info, $modSettings, $smcFunc;

		isAllowedTo('pm_send');
		require_once($sourcedir . '/Subs-Auth.php');

		// PM Drafts enabled and needed?
		if ($context['drafts_pm_save'] && (isset($_POST['save_draft']) || isset($_POST['id_pm_draft'])))
			require_once($sourcedir . '/Drafts.php');

		loadLanguage('PersonalMessage', '', false);

		// Extract out the spam settings - it saves database space!
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// Initialize the errors we're about to make.
		$post_errors = [];

		// Check whether we've gone over the limit of messages we can send per hour - fatal error if fails!
		if (!empty($modSettings['pm_posts_per_hour']) && !allowedTo(['admin_forum', 'moderate_forum', 'send_mail']) && $user_info['mod_cache']['bq'] == '0=1' && $user_info['mod_cache']['gq'] == '0=1')
		{
			// How many have they sent this last hour?
			$request = $smcFunc['db']->query('', '
				SELECT COUNT(pr.id_pm) AS post_count
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
				WHERE pm.id_member_from = {int:current_member}
					AND pm.msgtime > {int:msgtime}',
				[
					'current_member' => $user_info['id'],
					'msgtime' => time() - 3600,
				]
			);
			list ($postCount) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			if (!empty($postCount) && $postCount >= $modSettings['pm_posts_per_hour'])
			{
				if (!isset($_REQUEST['xml']))
					fatal_lang_error('pm_too_many_per_hour', true, [$modSettings['pm_posts_per_hour']]);
				else
					$post_errors[] = 'pm_too_many_per_hour';
			}
		}

		// If your session timed out, show an error, but do allow to re-submit.
		if (!isset($_REQUEST['xml']) && checkSession('post', '', false) != '')
			$post_errors[] = 'session_timeout';

		$_REQUEST['subject'] = isset($_REQUEST['subject']) ? trim($_REQUEST['subject']) : '';
		$_REQUEST['to'] = empty($_POST['to']) ? (empty($_GET['to']) ? '' : $_GET['to']) : $_POST['to'];

		// Route the input from the 'u' parameter to the 'to'-list.
		if (!empty($_POST['u']))
			$_POST['recipient_to'] = explode(',', $_POST['u']);

		// Construct the list of recipients.
		$recipientList = [];
		$namedRecipientList = [];
		$namesNotFound = [];
		foreach (['to'] as $recipientType)
		{
			// First, let's see if there's user ID's given.
			$recipientList[$recipientType] = [];
			if (!empty($_POST['recipient_' . $recipientType]) && is_array($_POST['recipient_' . $recipientType]))
			{
				foreach ($_POST['recipient_' . $recipientType] as $recipient)
					$recipientList[$recipientType][] = (int) $recipient;
			}

			// Are there also literal names set?
			if (!empty($_REQUEST[$recipientType]))
			{
				// We're going to take out the "s anyway ;).
				$recipientString = strtr($_REQUEST[$recipientType], ['\\"' => '"']);

				preg_match_all('~"([^"]+)"~', $recipientString, $matches);
				$namedRecipientList[$recipientType] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $recipientString))));

				foreach ($namedRecipientList[$recipientType] as $index => $recipient)
				{
					if (strlen(trim($recipient)) > 0)
						$namedRecipientList[$recipientType][$index] = StringLibrary::escape(StringLibrary::toLower(trim($recipient)));
					else
						unset($namedRecipientList[$recipientType][$index]);
				}

				if (!empty($namedRecipientList[$recipientType]))
				{
					$foundMembers = findMembers($namedRecipientList[$recipientType]);

					// Assume all are not found, until proven otherwise.
					$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

					foreach ($foundMembers as $member)
					{
						$testNames = [
							StringLibrary::toLower($member['username']),
							StringLibrary::toLower($member['name']),
							StringLibrary::toLower($member['email']),
						];

						if (count(array_intersect($testNames, $namedRecipientList[$recipientType])) !== 0)
						{
							$recipientList[$recipientType][] = $member['id'];

							// Get rid of this username, since we found it.
							$namesNotFound[$recipientType] = array_diff($namesNotFound[$recipientType], $testNames);
						}
					}
				}
			}

			// Selected a recipient to be deleted? Remove them now.
			if (!empty($_POST['delete_recipient']))
				$recipientList[$recipientType] = array_diff($recipientList[$recipientType], [(int) $_POST['delete_recipient']]);

			// Make sure we don't include the same name twice
			$recipientList[$recipientType] = array_unique($recipientList[$recipientType]);
		}

		// Are we changing the recipients some how?
		$is_recipient_change = !empty($_POST['delete_recipient']) || !empty($_POST['to_submit']);

		// Check if there's at least one recipient.
		if (empty($recipientList['to']))
			$post_errors[] = 'no_to';

		// Make sure that we remove the members who did get it from the screen.
		if (!$is_recipient_change)
		{
			foreach ($recipientList as $recipientType => $dummy)
			{
				if (!empty($namesNotFound[$recipientType]))
				{
					$post_errors[] = 'bad_' . $recipientType;

					// Since we already have a post error, remove the previous one.
					$post_errors = array_diff($post_errors, ['no_to']);

					foreach ($namesNotFound[$recipientType] as $name)
						$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
				}
			}
		}

		// Did they make any mistakes?
		if ($_REQUEST['subject'] == '')
			$post_errors[] = 'no_subject';
		if (!isset($_REQUEST['message']) || $_REQUEST['message'] == '')
			$post_errors[] = 'no_message';
		elseif (!empty($modSettings['max_messageLength']) && StringLibrary::strlen($_REQUEST['message']) > $modSettings['max_messageLength'])
			$post_errors[] = 'long_message';
		else
		{
			// Preparse the message.
			$message = $_REQUEST['message'];
			preparsecode($message);

			// Make sure there's still some content left without the tags.
			if (StringLibrary::htmltrim(strip_tags(Parser::parse_bbc(StringLibrary::escape($message, ENT_QUOTES), false), '<img>')) === '' && (!allowedTo('admin_forum') || strpos($message, '[html]') === false))
				$post_errors[] = 'no_message';
		}

		// Wrong verification code?
		if (!$user_info['is_admin'] && !isset($_REQUEST['xml']) && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'])
		{
			$context['require_verification'] = Verification::get('pm')->verify();

			if (!empty($context['require_verification']))
			{
				$post_errors = array_merge($post_errors, $context['require_verification']);
			}
		}

		// If they did, give a chance to make ammends.
		if (!empty($post_errors) && !$is_recipient_change && !isset($_REQUEST['preview']) && !isset($_REQUEST['xml']))
			return messagePostError($post_errors, $namedRecipientList, $recipientList);

		// Want to take a second glance before you send?
		if (isset($_REQUEST['preview']))
		{
			// Set everything up to be displayed.
			$context['preview_subject'] = StringLibrary::escape($_REQUEST['subject']);
			$context['preview_message'] = StringLibrary::escape($_REQUEST['message'], ENT_QUOTES);
			preparsecode($context['preview_message'], true);

			// Parse out the BBC if it is enabled.
			$context['preview_message'] = Parser::parse_bbc($context['preview_message']);

			// Censor, as always.
			censorText($context['preview_subject']);
			censorText($context['preview_message']);

			// Set a descriptive title.
			$context['page_title'] = $txt['preview'] . ' - ' . $context['preview_subject'];

			// Pretend they messed up but don't ignore if they really did :P.
			return messagePostError($post_errors, $namedRecipientList, $recipientList);
		}

		// Adding a recipient cause javascript ain't working?
		elseif ($is_recipient_change)
		{
			// Maybe we couldn't find one?
			foreach ($namesNotFound as $recipientType => $names)
			{
				$post_errors[] = 'bad_' . $recipientType;
				foreach ($names as $name)
					$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
			}

			return messagePostError([], $namedRecipientList, $recipientList);
		}

		// Want to save this as a draft and think about it some more?
		if ($context['drafts_pm_save'] && isset($_POST['save_draft']))
		{
			SavePMDraft($post_errors, $recipientList);
			return messagePostError($post_errors, $namedRecipientList, $recipientList);
		}

		// Before we send the PM, let's make sure we don't have an abuse of numbers.
		elseif (!empty($modSettings['max_pm_recipients']) && count($recipientList['to']) > $modSettings['max_pm_recipients'] && !allowedTo(['moderate_forum', 'send_mail', 'admin_forum']))
		{
			$context['send_log'] = [
				'sent' => [],
				'failed' => [sprintf($txt['pm_too_many_recipients'], $modSettings['max_pm_recipients'])],
			];
			return messagePostError($post_errors, $namedRecipientList, $recipientList);
		}

		// Protect from message spamming.
		spamProtection('pm');

		// Prevent double submission of this form.
		checkSubmitOnce('check');

		// Do the actual sending of the PM.
		if (!empty($recipientList['to']))
			$context['send_log'] = sendpm($recipientList, $_REQUEST['subject'], $_REQUEST['message'], true, null, !empty($_REQUEST['pm_head']) ? (int) $_REQUEST['pm_head'] : 0);
		else
			$context['send_log'] = [
				'sent' => [],
				'failed' => []
			];

		// Mark the message as "replied to".
		if (!empty($context['send_log']['sent']) && !empty($_REQUEST['replied_to']) && isset($_REQUEST['f']) && $_REQUEST['f'] == 'inbox')
		{
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}pm_recipients
				SET is_read = is_read | 2
				WHERE id_pm = {int:replied_to}
					AND id_member = {int:current_member}',
				[
					'current_member' => $user_info['id'],
					'replied_to' => (int) $_REQUEST['replied_to'],
				]
			);
		}

		// If one or more of the recipient were invalid, go back to the post screen with the failed usernames.
		if (!empty($context['send_log']['failed']))
			return messagePostError($post_errors, $namesNotFound, [
				'to' => array_intersect($recipientList['to'], $context['send_log']['failed']),
			]);

		$context['current_label_redirect'] = 'action=pm;f=inbox';

		// Message sent successfully?
		if (!empty($context['send_log']) && empty($context['send_log']['failed']))
		{
			$context['current_label_redirect'] = $context['current_label_redirect'];
			session_flash('success', $txt['pm_sent']);

			// If we had a PM draft for this one, then its time to remove it since it was just sent
			if ($context['drafts_pm_save'] && !empty($_POST['id_pm_draft']))
				DeleteDraft($_POST['id_pm_draft']);
		}

		// Go back to the where they sent from, if possible...
		redirectexit($context['current_label_redirect']);
	}
}
