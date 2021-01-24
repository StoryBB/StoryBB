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

class Report extends AbstractPMController
{
	public function display_action()
	{
		global $txt, $context, $scripturl;
		global $user_info, $language, $modSettings, $smcFunc;

		$pmsg = isset($_REQUEST['pmsg']) ? (int) $_REQUEST['pmsg'] : 0;

		if (empty($pmsg) || !isAccessiblePM($pmsg, 'inbox'))
			fatal_lang_error('no_access', false);

		$context['pm_id'] = $pmsg;
		$context['page_title'] = $txt['pm_report_title'];

		$context['sub_template'] = 'personal_message_report';

		// @todo I don't like being able to pick who to send it to.  Favoritism, etc. sucks.
		// Now, get all the administrators.
		$request = $smcFunc['db']->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
			ORDER BY real_name',
			[
				'admin_group' => 1,
			]
		);
		$context['admins'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$context['admins'][$row['id_member']] = $row['real_name'];
		$smcFunc['db']->free_result($request);

		// How many admins in total?
		$context['admin_count'] = count($context['admins']);

		$this->navigation->set_visible_menu_item(['f' => 'inbox']);
		$context['navigation'] = $this->navigation->export(['action' => 'pm']);
	}

	public function post_action()
	{
		global $txt, $context, $scripturl;
		global $user_info, $language, $modSettings, $smcFunc;

		// If nothing submitted, back to the form.
		if (empty($_POST['report']))
		{
			return $this->display_action();
		}

		$pmsg = isset($_REQUEST['pmsg']) ? (int) $_REQUEST['pmsg'] : 0;

		if (empty($pmsg) || !isAccessiblePM($pmsg, 'inbox'))
			fatal_lang_error('no_access', false);

		$context['pm_id'] = $pmsg;

		// First, pull out the message contents, and verify it actually went to them!
		$request = $smcFunc['db']->query('', '
			SELECT pm.subject, pm.body, pm.msgtime, pm.id_member_from, COALESCE(m.real_name, pm.from_name) AS sender_name
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			LIMIT 1',
			[
				'current_member' => $user_info['id'],
				'id_pm' => $context['pm_id'],
				'not_deleted' => 0,
			]
		);
		// Can only be a hacker here!
		if ($smcFunc['db']->num_rows($request) == 0)
			fatal_lang_error('no_access', false);
		list ($subject, $body, $time, $memberFromID, $memberFromName) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		// Remove the line breaks...
		$body = preg_replace('~<br ?/?' . '>~i', "\n", $body);

		// Get any other recipients of the email.
		$request = $smcFunc['db']->query('', '
			SELECT mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm = {int:id_pm}
				AND pmr.id_member != {int:current_member}',
			[
				'current_member' => $user_info['id'],
				'id_pm' => $context['pm_id'],
			]
		);
		$recipients = [];
		$hidden_recipients = 0;
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// If it's hidden still don't reveal their names - privacy after all ;)
			if ($row['bcc'])
				$hidden_recipients++;
			else
				$recipients[] = '[url=' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . ']' . $row['to_name'] . '[/url]';
		}
		$smcFunc['db']->free_result($request);

		if ($hidden_recipients)
			$recipients[] = sprintf($txt['pm_report_pm_hidden'], $hidden_recipients);

		// Now let's get out and loop through the admins.
		$request = $smcFunc['db']->query('', '
			SELECT id_member, real_name, lngfile
			FROM {db_prefix}members
			WHERE (id_group = {int:admin_id} OR FIND_IN_SET({int:admin_id}, additional_groups) != 0)
				' . (empty($_POST['id_admin']) ? '' : 'AND id_member = {int:specific_admin}') . '
			ORDER BY lngfile',
			[
				'admin_id' => 1,
				'specific_admin' => isset($_POST['id_admin']) ? (int) $_POST['id_admin'] : 0,
			]
		);

		// Maybe we shouldn't advertise this?
		if ($smcFunc['db']->num_rows($request) == 0)
			fatal_lang_error('no_access', false);

		$memberFromName = un_htmlspecialchars($memberFromName);

		// Prepare the message storage array.
		$messagesToSend = [];
		// Loop through each admin, and add them to the right language pile...
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Need to send in the correct language!
			$cur_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

			if (!isset($messagesToSend[$cur_language]))
			{
				loadLanguage('PersonalMessage', $cur_language, false);

				// Make the body.
				$report_body = str_replace(['{REPORTER}', '{SENDER}'], [un_htmlspecialchars($user_info['name']), $memberFromName], $txt['pm_report_pm_user_sent']);
				$report_body .= "\n" . '[b]' . $_POST['reason'] . '[/b]' . "\n\n";
				if (!empty($recipients))
					$report_body .= $txt['pm_report_pm_other_recipients'] . ' ' . implode(', ', $recipients) . "\n\n";
				$report_body .= $txt['pm_report_pm_unedited_below'] . "\n" . '[quote author=' . (empty($memberFromID) ? '&quot;' . $memberFromName . '&quot;' : $memberFromName . ' link=action=profile;u=' . $memberFromID . ' date=' . $time) . ']' . "\n" . un_htmlspecialchars($body) . '[/quote]';

				// Plonk it in the array ;)
				$messagesToSend[$cur_language] = [
					'subject' => (StringLibrary::strpos($subject, $txt['pm_report_pm_subject']) === false ? $txt['pm_report_pm_subject'] : '') . un_htmlspecialchars($subject),
					'body' => $report_body,
					'recipients' => [
						'to' => [],
						'bcc' => []
					],
				];
			}

			// Add them to the list.
			$messagesToSend[$cur_language]['recipients']['to'][$row['id_member']] = $row['id_member'];
		}
		$smcFunc['db']->free_result($request);

		// Send a different email for each language.
		foreach ($messagesToSend as $lang => $message)
			sendpm($message['recipients'], $message['subject'], $message['body']);

		// Give the user their own language back!
		if (!empty($modSettings['userLanguage']))
			loadLanguage('PersonalMessage', '', false);

		// Leave them with a template.
		session_flash('success', $txt['pm_report_done']);
		redirectexit('action=pm');
	}
}
