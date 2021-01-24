<?php

/**
 * Doer of bulk actions.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\PM;

use StoryBB\Template;

class Actions extends AbstractPMController
{
	public function display_action()
	{
		$this->post_action();
	}

	public function post_action()
	{
		global $context, $user_info, $options, $smcFunc;

		$context['folder'] = isset($_REQUEST['f']) && $_REQUEST['f'] == 'sent' ? 'sent' : 'inbox';
		if (isset($_REQUEST['returnto']) && in_array($_REQUEST['returnto'], ['inbox', 'sent']))
		{
			$context['folder'] = $_REQUEST['returnto'];
		}
		$context['current_label_redirect'] = 'action=pm;f=' . $context['folder'] . (isset($_GET['start']) ? ';start=' . $_GET['start'] : '') . (isset($_REQUEST['l']) ? ';l=' . ((int) $_REQUEST['l']) : '');

		checkSession('request');

		if (isset($_REQUEST['del_selected']))
			$_REQUEST['pm_action'] = 'delete';

		if (isset($_REQUEST['pm_action']) && $_REQUEST['pm_action'] != '' && !empty($_REQUEST['pms']) && is_array($_REQUEST['pms']))
		{
			foreach ($_REQUEST['pms'] as $pm)
				$_REQUEST['pm_actions'][(int) $pm] = $_REQUEST['pm_action'];
		}

		if (empty($_REQUEST['pm_actions']))
			redirectexit($context['current_label_redirect']);

		// If we are in conversation, we may need to apply this to every message in the conversation.
		if (isset($_REQUEST['conversation']))
		{
			$id_pms = [];
			foreach ($_REQUEST['pm_actions'] as $pm => $dummy)
				$id_pms[] = (int) $pm;

			$request = $smcFunc['db']->query('', '
				SELECT id_pm_head, id_pm
				FROM {db_prefix}personal_messages
				WHERE id_pm IN ({array_int:id_pms})',
				[
					'id_pms' => $id_pms,
				]
			);
			$pm_heads = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$pm_heads[$row['id_pm_head']] = $row['id_pm'];
			$smcFunc['db']->free_result($request);

			$request = $smcFunc['db']->query('', '
				SELECT id_pm, id_pm_head
				FROM {db_prefix}personal_messages
				WHERE id_pm_head IN ({array_int:pm_heads})',
				[
					'pm_heads' => array_keys($pm_heads),
				]
			);
			// Copy the action from the single to PM to the others.
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				if (isset($pm_heads[$row['id_pm_head']]) && isset($_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]]))
					$_REQUEST['pm_actions'][$row['id_pm']] = $_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]];
			}
			$smcFunc['db']->free_result($request);
		}

		$to_delete = [];
		$to_label = [];
		$label_type = [];
		$labels = [];
		foreach ($_REQUEST['pm_actions'] as $pm => $action)
		{
			if ($action === 'delete')
				$to_delete[] = (int) $pm;
			else
			{
				if (substr($action, 0, 4) == 'add_')
				{
					$type = 'add';
					$action = substr($action, 4);
				}
				elseif (substr($action, 0, 4) == 'rem_')
				{
					$type = 'rem';
					$action = substr($action, 4);
				}
				else
					$type = 'unk';

				if ($action == '-1' || (int) $action > 0)
				{
					$to_label[(int) $pm] = (int) $action;
					$label_type[(int) $pm] = $type;
				}
			}
		}

		// Deleting, it looks like?
		if (!empty($to_delete))
			deleteMessages($to_delete, null);

		// Are we labeling anything?
		if (!empty($to_label) && $context['folder'] == 'inbox')
		{
			// Get all the messages in each conversation
			$get_pms = $smcFunc['db']->query('', '
				SELECT pm.id_pm_head, pm.id_pm
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				WHERE pm.id_pm_head IN ({array_int:head_pms})
					AND pm.id_pm NOT IN ({array_int:head_pms})
					AND pmr.id_member = {int:current_member}',
				[
					'head_pms' => array_keys($to_label),
					'current_member' => $user_info['id'],
				]
			);

			while ($other_pms = $smcFunc['db']->fetch_assoc($get_pms))
			{
				$to_label[$other_pms['id_pm']] = $to_label[$other_pms['id_pm_head']];
			}

			$smcFunc['db']->free_result($get_pms);

			// Get information about each message...
			$request = $smcFunc['db']->query('', '
				SELECT id_pm, in_inbox
				FROM {db_prefix}pm_recipients
				WHERE id_member = {int:current_member}
					AND id_pm IN ({array_int:to_label})
				LIMIT ' . count($to_label),
				[
					'current_member' => $user_info['id'],
					'to_label' => array_keys($to_label),
				]
			);

			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				// Get the labels as well, but only if we're not dealing with the inbox
				if ($to_label[$row['id_pm']] != '-1')
				{
					// The JOIN here ensures we only get labels that this user has applied to this PM
					$request2 = $smcFunc['db']->query('', '
						SELECT l.id_label, pml.id_pm
						FROM {db_prefix}pm_labels AS l
							INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
						WHERE l.id_member = {int:current_member}
							AND pml.id_pm = {int:current_pm}',
						[
							'current_member' => $user_info['id'],
							'current_pm' => $row['id_pm'],
						]
					);

					while ($row2 = $smcFunc['db']->fetch_assoc($request2))
					{
						$labels[$row2['id_label']] = $row2['id_label'];
					}

					$smcFunc['db']->free_result($request2);
				}
				elseif ($type == 'rem')
				{
					// If we're removing from the inbox, see if we have at least one other label.
					// This query is faster than the one above
					$request2 = $smcFunc['db']->query('', '
						SELECT COUNT(l.id_label)
						FROM {db_prefix}pm_labels AS l
							INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
						WHERE l.id_member = {int:current_member}
							AND pml.id_pm = {int:current_pm}',
						[
							'current_member' => $user_info['id'],
							'current_pm' => $row['id_pm'],
						]
					);

					// How many labels do you have?
					list ($num_labels) = $smcFunc['db']->fetch_assoc($request2);

					if ($num_labels > 0);
						$context['can_remove_inbox'] = true;

					$smcFunc['db']->free_result($request2);
				}

				// Use this to determine what to do later on...
				$original_labels = $labels;

				// Ignore inbox for now - we'll deal with it later
				if ($to_label[$row['id_pm']] != '-1')
				{
					// If this label is in the list and we're not adding it, remove it
					if (array_key_exists($to_label[$row['id_pm']], $labels) && $type !== 'add')
						unset($labels[$to_label[$row['id_pm']]]);
					elseif ($type !== 'rem')
						$labels[$to_label[$row['id_pm']]] = $to_label[$row['id_pm']];
				}

				// Removing all labels or just removing the inbox label
				if ($type == 'rem' && empty($labels))
					$in_inbox = (empty($context['can_remove_inbox']) ? 1 : 0);
				// Adding new labels, but removing inbox and applying new ones
				elseif ($type == 'add' && !empty($options['pm_remove_inbox_label']) && !empty($labels))
					$in_inbox = 0;
				// Just adding it to the inbox
				else
					$in_inbox = 1;

				// Are we adding it to or removing it from the inbox?
				if ($in_inbox != $row['in_inbox'])
				{
					$smcFunc['db']->query('', '
						UPDATE {db_prefix}pm_recipients
						SET in_inbox = {int:in_inbox}
						WHERE id_pm = {int:id_pm}
							AND id_member = {int:current_member}',
						[
							'current_member' => $user_info['id'],
							'id_pm' => $row['id_pm'],
							'in_inbox' => $in_inbox,
						]
					);
				}

				// Which labels do we not want now?
				$labels_to_remove = array_diff($original_labels, $labels);

				// Don't apply it if it's already applied
				$labels_to_apply = array_diff($labels, $original_labels);

				// Remove labels
				if (!empty($labels_to_remove))
				{
					$smcFunc['db']->query('', '
						DELETE FROM {db_prefix}pm_labeled_messages
						WHERE id_pm = {int:current_pm}
							AND id_label IN ({array_int:labels_to_remove})',
						[
							'current_pm' => $row['id_pm'],
							'labels_to_remove' => $labels_to_remove,
						]
					);
				}

				// Add new ones
				if (!empty($labels_to_apply))
				{
					$inserts = [];
					foreach ($labels_to_apply as $label)
						$inserts[] = [$row['id_pm'], $label];

					$smcFunc['db']->insert('',
						'{db_prefix}pm_labeled_messages',
						['id_pm' => 'int', 'id_label' => 'int'],
						$inserts,
						[]
					);
				}
			}
			$smcFunc['db']->free_result($request);
		}

		// Back to the folder.
		$_SESSION['pm_selected'] = array_keys($to_label);
		redirectexit($context['current_label_redirect']);
	}
}
