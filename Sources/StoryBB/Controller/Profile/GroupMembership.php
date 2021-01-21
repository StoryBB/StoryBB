<?php

/**
 * Displays the issue-warning page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\Group;
use StoryBB\Task;

class GroupMembership extends AbstractProfileController
{
	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-gm%u');
	}

	public function display_action()
	{
		global $txt, $user_profile, $context, $smcFunc;

		$memID = $this->params['u'];
		$curMember = $user_profile[$memID];
		$context['primary_group'] = $curMember['id_group'];

		createToken($this->get_token_name(), 'post');
		$context['token_check'] = $this->get_token_name();

		// Can they manage groups?
		$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
		$context['can_manage_protected'] = allowedTo('admin_forum');
		$context['can_edit_primary'] = $context['can_manage_protected'];

		// Get all the groups this user is a member of.
		$groups = explode(',', $curMember['additional_groups']);
		$groups[] = $curMember['id_group'];

		// Ensure the query doesn't croak!
		$groups = array_filter(array_map('intval', $groups));
		if (empty($groups))
			$groups = [0];

		// Get all the membergroups they can join.
		$request = $smcFunc['db']->query('', '
			SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
				COALESCE(lgr.id_member, 0) AS pending
			FROM {db_prefix}membergroups AS mg
				LEFT JOIN {db_prefix}log_group_requests AS lgr ON (lgr.id_member = {int:selected_member} AND lgr.id_group = mg.id_group AND lgr.status = {int:status_open})
			WHERE (mg.id_group IN ({array_int:group_list})
				OR mg.group_type > {int:nonjoin_group_id})
				AND mg.id_group != {int:moderator_group}
			ORDER BY group_name',
			[
				'group_list' => $groups,
				'selected_member' => $memID,
				'status_open' => 0,
				'nonjoin_group_id' => 1,
				'moderator_group' => 3,
			]
		);
		// This beast will be our group holder.
		$context['groups'] = [
			'member' => [],
			'available' => []
		];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Can they edit their primary group?
			$group_requestable_joinable = in_array($row['group_type'], [Group::TYPE_REQUESTABLE, Group::TYPE_JOINABLE]);
			$group_visible = $row['hidden'] != Group::VISIBILITY_INVISIBLE;
			if (($row['id_group'] == $context['primary_group'] && $group_requestable_joinable) || ($group_visible && $context['primary_group'] == 0 && in_array($row['id_group'], $groups)))
				$context['can_edit_primary'] = true;

			// If they can't manage (protected) groups, and it's not publically joinable or already assigned, they can't see it.
			$group_private_or_protected = in_array($row['group_type'], [Group::TYPE_PRIVATE, Group::TYPE_PROTECTED]);
			if ((!$context['can_manage_protected'] && $group_private_or_protected) && $row['id_group'] != $context['primary_group'])
				continue;

			$context['groups'][in_array($row['id_group'], $groups) ? 'member' : 'available'][$row['id_group']] = [
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'desc' => $row['description'],
				'color' => $row['online_color'],
				'type' => $row['group_type'],
				'pending' => (bool) $row['pending'],
				'is_primary' => $row['id_group'] == $context['primary_group'],
				'can_be_primary' => $group_visible,
				// Anything more than this needs to be done through account settings for security.
				'can_leave' => $row['id_group'] != Group::ADMINISTRATOR && $group_requestable_joinable ? true : false,
			];
		}
		$smcFunc['db']->free_result($request);

		// Add registered members on the end.
		$context['groups']['member'][0] = [
			'id' => 0,
			'name' => $txt['regular_members'],
			'desc' => $txt['regular_members_desc'],
			'type' => 0,
			'is_primary' => $context['primary_group'] == 0 ? true : false,
			'can_be_primary' => true,
			'can_leave' => 0,
		];

		// No changing primary one unless you have enough groups!
		if (count($context['groups']['member']) < 2)
		{
			$context['can_edit_primary'] = false;
		}

		// In the special case that someone is requesting membership of a group, setup some special context vars.
		if (isset($_REQUEST['request']) && isset($context['groups']['available'][(int) $_REQUEST['request']]) && $context['groups']['available'][(int) $_REQUEST['request']]['type'] == Group::TYPE_REQUESTABLE)
		{
			$context['group_request'] = $context['groups']['available'][(int) $_REQUEST['request']];
		}

		$context['highlight_primary'] = isset($context['groups']['member'][$context['primary_group']]);
		$context['sub_template'] = 'profile_group_request';
	}

	public function post_action()
	{
		global $user_info, $context, $user_profile, $modSettings, $smcFunc, $txt;

		$memID = $this->params['u'];

		// Let's be extra cautious...
		if (!$context['user']['is_owner'] || empty($modSettings['show_group_membership']))
			isAllowedTo('manage_membergroups');
		if (!isset($_POST['primary']) && !isset($_POST['leave']) && !isset($_POST['gid']))
			fatal_lang_error('no_access', false);

		validateToken($this->get_token_name());

		$old_profile = &$user_profile[$memID];
		$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
		$context['can_manage_protected'] = allowedTo('admin_forum');

		// By default the new primary is the old one.
		$newPrimary = $old_profile['id_group'];
		$addGroups = array_flip(explode(',', $old_profile['additional_groups']));
		$canChangePrimary = $old_profile['id_group'] == 0 ? 1 : 0;
		$changeType = isset($_POST['primary']) ? 'primary' : (isset($_POST['req']) ? 'request' : 'free');

		// One way or another, we have a target group in mind...
		$group_id = isset($_REQUEST['gid']) ? (int) $_REQUEST['gid'] : (int) $_POST['primary'];

		if (isset($_POST['leave']) && is_array($_POST['leave']))
		{
			$changeType = 'free';
			$group_id = (int) array_keys($_POST['leave'])[0];
		}

		$foundTarget = $changeType == 'primary' && $group_id == 0 ? true : false;

		// Sanity check!!
		if ($group_id == 1)
			isAllowedTo('admin_forum');
		// Protected groups too!
		else
		{
			$request = $smcFunc['db']->query('', '
				SELECT group_type
				FROM {db_prefix}membergroups
				WHERE id_group = {int:current_group}
				LIMIT {int:limit}',
				[
					'current_group' => $group_id,
					'limit' => 1,
				]
			);
			list ($is_protected) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			if ($is_protected == 1)
				isAllowedTo('admin_forum');
		}

		// What ever we are doing, we need to determine if changing primary is possible!
		$request = $smcFunc['db']->query('', '
			SELECT id_group, group_type, hidden, group_name
			FROM {db_prefix}membergroups
			WHERE id_group IN ({int:group_list}, {int:current_group})',
			[
				'group_list' => $group_id,
				'current_group' => $old_profile['id_group'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Is this the new group?
			if ($row['id_group'] == $group_id)
			{
				$foundTarget = true;
				$group_name = $row['group_name'];

				// Does the group type match what we're doing - are we trying to request a non-requestable group?
				if ($changeType == 'request' && $row['group_type'] != Group::TYPE_REQUESTABLE)
					fatal_lang_error('no_access', false);
				// What about leaving a requestable group we are not a member of?
				elseif ($changeType == 'free' && $row['group_type'] == Group::TYPE_REQUESTABLE && $old_profile['id_group'] != $row['id_group'] && !isset($addGroups[$row['id_group']]))
					fatal_lang_error('no_access', false);
				elseif ($changeType == 'free' && $row['group_type'] != Group::TYPE_JOINABLE && $row['group_type'] != Group::TYPE_REQUESTABLE)
					fatal_lang_error('no_access', false);

				// We can't change the primary group if this is hidden!
				if ($row['hidden'] == Group::VISIBILITY_INVISIBLE)
					$canChangePrimary = false;
			}

			// If this is their old primary, can we change it?
			if ($row['id_group'] == $old_profile['id_group'] && ($row['group_type'] > 1 || $context['can_manage_membergroups']) && $canChangePrimary !== false)
				$canChangePrimary = 1;

			// If we are not doing a force primary move, don't do it automatically if current primary is not 0.
			if ($changeType != 'primary' && $old_profile['id_group'] != 0)
				$canChangePrimary = false;

			// If this is the one we are acting on, can we even act?
			if ((!$context['can_manage_protected'] && $row['group_type'] == Group::TYPE_PROTECTED) || (!$context['can_manage_membergroups'] && $row['group_type'] == Group::TYPE_PRIVATE))
				$canChangePrimary = false;
		}
		$smcFunc['db']->free_result($request);

		// Didn't find the target?
		if (!$foundTarget)
			fatal_lang_error('no_access', false);

		// Final security check, don't allow users to promote themselves to admin.
		if ($context['can_manage_membergroups'] && !allowedTo('admin_forum'))
		{
			$request = $smcFunc['db']->query('', '
				SELECT COUNT(permission)
				FROM {db_prefix}permissions
				WHERE id_group = {int:selected_group}
					AND permission = {string:admin_forum}
					AND add_deny = {int:not_denied}',
				[
					'selected_group' => $group_id,
					'not_denied' => 1,
					'admin_forum' => 'admin_forum',
				]
			);
			list ($disallow) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			if ($disallow)
				isAllowedTo('admin_forum');
		}

		// If we're requesting, add the note then return.
		if ($changeType == 'request')
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_member
				FROM {db_prefix}log_group_requests
				WHERE id_member = {int:selected_member}
					AND id_group = {int:selected_group}
					AND status = {int:status_open}',
				[
					'selected_member' => $memID,
					'selected_group' => $group_id,
					'status_open' => 0,
				]
			);
			if ($smcFunc['db']->num_rows($request) != 0)
				fatal_lang_error('profile_error_already_requested_group');
			$smcFunc['db']->free_result($request);

			// Log the request.
			$smcFunc['db']->insert('',
				'{db_prefix}log_group_requests',
				[
					'id_member' => 'int', 'id_group' => 'int', 'time_applied' => 'int', 'reason' => 'string-65534',
					'status' => 'int', 'id_member_acted' => 'int', 'member_name_acted' => 'string', 'time_acted' => 'int', 'act_reason' => 'string',
				],
				[
					$memID, $group_id, time(), $_POST['reason'],
					0, 0, '', 0, '',
				],
				['id_request']
			);

			// Add a background task to handle notifying people of this request
			Task::queue_adhoc('StoryBB\\Task\\Adhoc\\GroupReqNotify', [
				'id_member' => $memID,
				'member_name' => $user_info['name'],
				'id_group' => $group_id,
				'group_name' => $group_name,
				'reason' => $_POST['reason'],
				'time' => time(),
			]);

			session_flash('success', $txt['group_membership_msg_' . $changeType]);
			redirectexit('action=profile;area=group_membership;u=' . $memID);
		}
		// Otherwise we are leaving/joining a group.
		elseif ($changeType == 'free')
		{
			// Are we leaving?
			if ($old_profile['id_group'] == $group_id || isset($addGroups[$group_id]))
			{
				if ($old_profile['id_group'] == $group_id)
					$newPrimary = 0;
				else
					unset($addGroups[$group_id]);
			}
			// ... if not, must be joining.
			else
			{
				// Can we change the primary, and do we want to?
				if ($canChangePrimary)
				{
					if ($old_profile['id_group'] != 0)
						$addGroups[$old_profile['id_group']] = -1;
					$newPrimary = $group_id;
				}
				// Otherwise it's an additional group...
				else
					$addGroups[$group_id] = -1;
			}
		}
		// Finally, we must be setting the primary.
		elseif ($canChangePrimary)
		{
			if ($old_profile['id_group'] != 0)
				$addGroups[$old_profile['id_group']] = -1;
			if (isset($addGroups[$group_id]))
				unset($addGroups[$group_id]);
			$newPrimary = $group_id;
		}

		// Finally, we can make the changes!
		foreach (array_keys($addGroups) as $id)
			if (empty($id))
				unset($addGroups[$id]);
		$addGroups = implode(',', array_flip($addGroups));

		// Ensure that we don't cache permissions if the group is changing.
		if ($context['user']['is_owner'])
			$_SESSION['mc']['time'] = 0;
		else
			updateSettings(['settings_updated' => time()]);

		updateMemberData($memID, ['id_group' => $newPrimary, 'additional_groups' => $addGroups]);

		session_flash('success', $txt['group_membership_msg_' . $changeType]);
		redirectexit('action=profile;area=group_membership;u=' . $memID);
	}
}
