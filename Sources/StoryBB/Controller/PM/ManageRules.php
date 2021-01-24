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

class ManageRules extends AbstractPMController
{
	protected function initialisation()
	{
		global $txt, $context, $user_info, $scripturl, $smcFunc;

		// The link tree - gotta have this :o
		$context['linktree'][] = [
			'url' => $scripturl . '?action=pm;sa=manage_rules',
			'name' => $txt['pm_manage_rules']
		];

		$context['page_title'] = $txt['pm_manage_rules'];
		$context['sub_template'] = 'personal_message_rules';

		// Load them... load them!!
		LoadRules();

		// Likely to need all the groups!
		$request = $smcFunc['db']->query('', '
			SELECT mg.id_group, mg.group_name, COALESCE(gm.id_member, 0) AS can_moderate, mg.hidden
			FROM {db_prefix}membergroups AS mg
				LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
			WHERE mg.id_group != {int:moderator_group}
				AND mg.hidden = {int:not_hidden}
			ORDER BY mg.group_name',
			[
				'current_member' => $user_info['id'],
				'moderator_group' => 3,
				'not_hidden' => 0,
			]
		);
		$context['groups'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Hide hidden groups!
			if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
				continue;

			$context['groups'][$row['id_group']] = $row['group_name'];
		}
		$smcFunc['db']->free_result($request);
	}

	public function display_action()
	{
		global $context, $smcFunc;

		$this->initialisation();

		// Applying all rules?
		if (isset($_GET['apply']))
		{
			checkSession('get');

			ApplyRules(true);
			redirectexit('action=pm;sa=manage_rules');
		}

		// Editing a specific one?
		if (isset($_GET['add']))
		{
			$context['rid'] = isset($_GET['rid']) && isset($context['rules'][$_GET['rid']]) ? (int) $_GET['rid'] : 0;
			$context['sub_template'] = 'personal_message_rules_add';
			$context['criteriaTypes'] = ['mid', 'gid', 'sub', 'msg', 'bud'];

			// Current rule information...
			if ($context['rid'])
			{
				$context['rule'] = $context['rules'][$context['rid']];
				// Add a dummy criteria to allow expansion for none js users.
				$context['rule']['criteria'][] = ['t' => '', 'v' => ''];
				// As with criteria - add a dummy action for "expansion".
				$context['rule']['actions'][] = ['t' => '', 'v' => ''];
				
				$members = [];
				// Need to get member names!
				foreach ($context['rule']['criteria'] as $k => $criteria)
					if ($criteria['t'] == 'mid' && !empty($criteria['v']))
						$members[(int) $criteria['v']] = $k;

				if (!empty($members))
				{
					$request = $smcFunc['db']->query('', '
						SELECT id_member, member_name
						FROM {db_prefix}members
						WHERE id_member IN ({array_int:member_list})',
						[
							'member_list' => array_keys($members),
						]
					);
					while ($row = $smcFunc['db']->fetch_assoc($request))
						$context['rule']['criteria'][$members[$row['id_member']]]['v'] = $row['member_name'];
					$smcFunc['db']->free_result($request);
				}
			}
			else
				$context['rule'] = [
					'id' => '',
					'name' => '',
					'criteria' => [],
					'actions' => [],
					'logic' => 'and',
				];
		}
	}

	public function post_action()
	{
		global $context, $smcFunc, $user_info;

		$this->initialisation();

		// Saving?
		if (isset($_GET['save']))
		{
			$context['rid'] = isset($_GET['rid']) && isset($context['rules'][$_GET['rid']]) ? (int) $_GET['rid'] : 0;

			// Name is easy!
			$ruleName = StringLibrary::escape(trim($_POST['rule_name']));
			if (empty($ruleName))
			{
				fatal_lang_error('pm_rule_no_name', false);
			}

			// Sanity check...
			if (empty($_POST['ruletype']) || empty($_POST['acttype']))
			{
				fatal_lang_error('pm_rule_no_criteria', false);
			}

			// Let's do the criteria first - it's also hardest!
			$criteria = [];
			foreach ($_POST['ruletype'] as $ind => $type)
			{
				// Check everything is here...
				if ($type == 'gid' && (!isset($_POST['ruledefgroup'][$ind]) || !isset($context['groups'][$_POST['ruledefgroup'][$ind]])))
				{
					continue;
				}
				elseif ($type != 'bud' && !isset($_POST['ruledef'][$ind]))
				{
					continue;
				}

				// Members need to be found.
				if ($type == 'mid')
				{
					$name = trim($_POST['ruledef'][$ind]);
					$request = $smcFunc['db']->query('', '
						SELECT id_member
						FROM {db_prefix}members
						WHERE real_name = {string:member_name}
							OR member_name = {string:member_name}',
						[
							'member_name' => $name,
						]
					);
					if ($smcFunc['db']->num_rows($request) == 0)
					{
						loadLanguage('Errors');
						fatal_lang_error('invalid_username', false);
					}
					list ($memID) = $smcFunc['db']->fetch_row($request);
					$smcFunc['db']->free_result($request);

					$criteria[] = ['t' => 'mid', 'v' => $memID];
				}
				elseif ($type == 'bud')
					$criteria[] = ['t' => 'bud', 'v' => 1];
				elseif ($type == 'gid')
					$criteria[] = ['t' => 'gid', 'v' => (int) $_POST['ruledefgroup'][$ind]];
				elseif (in_array($type, ['sub', 'msg']) && trim($_POST['ruledef'][$ind]) != '')
					$criteria[] = ['t' => $type, 'v' => StringLibrary::escape(trim($_POST['ruledef'][$ind]))];
			}

			// Also do the actions!
			$actions = [];
			$doDelete = 0;
			$isOr = $_POST['rule_logic'] == 'or' ? 1 : 0;
			foreach ($_POST['acttype'] as $ind => $type)
			{
				// Picking a valid label?
				if ($type == 'lab' && (!isset($_POST['labdef'][$ind]) || !isset($context['labels'][$_POST['labdef'][$ind]])))
					continue;

				// Record what we're doing.
				if ($type == 'del')
					$doDelete = 1;
				elseif ($type == 'lab')
					$actions[] = ['t' => 'lab', 'v' => (int) $_POST['labdef'][$ind]];
			}

			if (empty($criteria) || (empty($actions) && !$doDelete))
			{
				fatal_lang_error('pm_rule_no_criteria', false);
			}

			// What are we storing?
			$criteria = json_encode($criteria);
			$actions = json_encode($actions);

			// Create the rule?
			if (empty($context['rid']))
			{
				$smcFunc['db']->insert('',
					'{db_prefix}pm_rules',
					[
						'id_member' => 'int', 'rule_name' => 'string', 'criteria' => 'string', 'actions' => 'string',
						'delete_pm' => 'int', 'is_or' => 'int',
					],
					[
						$user_info['id'], $ruleName, $criteria, $actions, $doDelete, $isOr,
					],
					['id_rule']
				);
			}
			else
			{
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}pm_rules
					SET rule_name = {string:rule_name}, criteria = {string:criteria}, actions = {string:actions},
						delete_pm = {int:delete_pm}, is_or = {int:is_or}
					WHERE id_rule = {int:id_rule}
						AND id_member = {int:current_member}',
					[
						'current_member' => $user_info['id'],
						'delete_pm' => $doDelete,
						'is_or' => $isOr,
						'id_rule' => $context['rid'],
						'rule_name' => $ruleName,
						'criteria' => $criteria,
						'actions' => $actions,
					]
				);
			}
		}
		elseif (isset($_POST['delselected']) && !empty($_POST['delrule']))
		{
			// Deleting?
			checkSession();
			$toDelete = [];
			foreach ($_POST['delrule'] as $k => $v)
			{
				$toDelete[] = (int) $k;
			}

			if (!empty($toDelete))
			{
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}pm_rules
					WHERE id_rule IN ({array_int:delete_list})
						AND id_member = {int:current_member}',
					[
						'current_member' => $user_info['id'],
						'delete_list' => $toDelete,
					]
				);
			}
		}

		redirectexit('action=pm;sa=manage_rules');
	}
}
