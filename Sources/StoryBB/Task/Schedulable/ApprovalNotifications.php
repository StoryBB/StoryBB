<?php
/**
 * Send out notifications of things that need approving.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Send out notifications of things that need approving.
 */
class ApprovalNotifications extends \StoryBB\Task\Schedulable
{
	/**
	 * Send out notifications of things that need approving.
	 *
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $scripturl, $txt, $sourcedir, $smcFunc;

		// Grab all the items awaiting approval and sort type then board - clear up any things that are no longer relevant.
		$request = $smcFunc['db_query']('', '
			SELECT aq.id_msg, aq.id_attach, m.id_topic, m.id_board, m.subject, t.id_first_msg,
				b.id_profile
			FROM {db_prefix}approval_queue AS aq
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = aq.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)',
			array(
			)
		);
		$notices = [];
		$profiles = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If this is no longer around we'll ignore it.
			if (empty($row['id_topic']))
				continue;

			// What type is it?
			if ($row['id_first_msg'] && $row['id_first_msg'] == $row['id_msg'])
				$type = 'topic';
			elseif ($row['id_attach'])
				$type = 'attach';
			else
				$type = 'msg';

			// Add it to the array otherwise.
			$notices[$row['id_board']][$type][] = array(
				'subject' => $row['subject'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			);

			// Store the profile for a bit later.
			$profiles[$row['id_board']] = $row['id_profile'];
		}
		$smcFunc['db_free_result']($request);

		// Delete it all!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}approval_queue',
			array(
			)
		);

		// If nothing quit now.
		if (empty($notices))
			return true;

		// Now we need to think about finding out *who* can approve - this is hard!

		// First off, get all the groups with this permission and sort by board.
		$request = $smcFunc['db_query']('', '
			SELECT id_group, id_profile, add_deny
			FROM {db_prefix}board_permissions
			WHERE permission = {literal:approve_posts}
				AND id_profile IN ({array_int:profile_list})',
			array(
				'profile_list' => $profiles,
			)
		);
		$perms = [];
		$addGroups = array(1);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Sorry guys, but we have to ignore guests AND members - it would be too many otherwise.
			if ($row['id_group'] < 2)
				continue;

			$perms[$row['id_profile']][$row['add_deny'] ? 'add' : 'deny'][] = $row['id_group'];

			// Anyone who can access has to be considered.
			if ($row['add_deny'])
				$addGroups[] = $row['id_group'];
		}
		$smcFunc['db_free_result']($request);

		// Grab the moderators if they have permission!
		$mods = [];
		$members = [];
		if (in_array(2, $addGroups))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member, id_board
				FROM {db_prefix}moderators',
				array(
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$mods[$row['id_member']][$row['id_board']] = true;
				// Make sure they get included in the big loop.
				$members[] = $row['id_member'];
			}
			$smcFunc['db_free_result']($request);
		}

		// @todo People who are in groups who are group moderators of boards?

		// Come along one and all... until we reject you ;)
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name, email_address, lngfile, id_group, additional_groups
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:additional_group_list})
				OR FIND_IN_SET({raw:additional_group_list_implode}, additional_groups) != 0' . (empty($members) ? '' : '
				OR id_member IN ({array_int:member_list})') . '
			ORDER BY lngfile',
			array(
				'additional_group_list' => $addGroups,
				'member_list' => $members,
				'additional_group_list_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $addGroups),
			)
		);
		$members = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$members[$row['id_member']] = array(
				'id' => $row['id_member'],
				'groups' => array_merge(explode(',', $row['additional_groups']), array($row['id_group'])),
				'language' => $row['lngfile'],
				'email' => $row['email_address'],
				'name' => $row['real_name'],
			);
		}
		$smcFunc['db_free_result']($request);

		// Now filter out the people who don't want it.
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs(array_keys($members), 'approval_notify', true);
		foreach ($members as $memberid => $member)
		{
			if (!isset($prefs[$memberid]['approval_notify']) || $prefs[$memberid]['approval_notify'] & 2 == 0)
			{
				unset ($members[$memberid]);
			}
		}

		// Get the mailing stuff.
		require_once($sourcedir . '/Subs-Post.php');
		// Need the below for loadLanguage to work!
		loadEssentialThemeData();

		$current_language = '';
		// Finally, loop through each member, work out what they can do, and send it.
		foreach ($members as $id => $member)
		{
			$emailbody = '';

			// Load the language file as required.
			if (empty($current_language) || $current_language != $member['language'])
				$current_language = loadLanguage('EmailTemplates', $member['language'], false);

			// Loop through each notice...
			foreach ($notices as $board => $notice)
			{
				$access = false;

				// Can they mod in this board?
				if (isset($mods[$id][$board]))
					$access = true;

				// Do the group check...
				if (!$access && isset($perms[$profiles[$board]]['add']))
				{
					// They can access?!
					if (array_intersect($perms[$profiles[$board]]['add'], $member['groups']))
						$access = true;

					// If they have deny rights don't consider them!
					if (isset($perms[$profiles[$board]]['deny']))
						if (array_intersect($perms[$profiles[$board]]['deny'], $member['groups']))
							$access = false;
				}

				// Finally, fix it for admins!
				if (in_array(1, $member['groups']))
					$access = true;

				// If they can't access it then give it a break!
				if (!$access)
					continue;

				foreach ($notice as $type => $items)
				{
					// Build up the top of this section.
					$emailbody .= $txt['scheduled_approval_email_' . $type] . "\n" .
						'------------------------------------------------------' . "\n";

					foreach ($items as $item)
						$emailbody .= $item['subject'] . ' - ' . $item['href'] . "\n";

					$emailbody .= "\n";
				}
			}

			if ($emailbody == '')
				continue;

			$replacements = array(
				'REALNAME' => $member['name'],
				'BODY' => $emailbody,
			);

			$emaildata = loadEmailTemplate('scheduled_approval', $replacements, $current_language);

			// Send the actual email.
			StoryBB\Helper\Mail::send($member['email'], $emaildata['subject'], $emaildata['body'], null, 'schedapp', $emaildata['is_html'], 2);
		}

		// All went well!
		return true;
	}
}
