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

class ManageLabels extends AbstractPMController
{
	public function display_action()
	{
		global $txt, $context, $user_info, $scripturl, $smcFunc;

		// Build the link tree elements...
		$context['linktree'][] = [
			'url' => $scripturl . '?action=pm;sa=manage_labels',
			'name' => $txt['pm_manage_labels']
		];

		$context['page_title'] = $txt['pm_manage_labels'];
		$context['sub_template'] = 'personal_message_labels';
	}

	public function post_action()
	{
		global $txt, $context, $user_info, $scripturl, $smcFunc;

		$the_labels = [];
		$labels_to_add = [];
		$labels_to_remove = [];
		$label_updates = [];

		// Add all existing labels to the array to save, slashing them as necessary...
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] != -1)
				$the_labels[$label['id']] = $label['name'];
		}

		// This will be for updating messages.
		$message_changes = [];
		$rule_changes = [];

		// Will most likely need this.
		LoadRules();

		// Adding a new label?
		if (isset($_POST['add']))
		{
			$_POST['label'] = strtr(StringLibrary::escape(trim($_POST['label'])), [',' => '&#044;']);

			if (StringLibrary::strlen($_POST['label']) > 30)
				$_POST['label'] = StringLibrary::substr($_POST['label'], 0, 30);
			if ($_POST['label'] != '')
			{
				$the_labels[] = $_POST['label'];
				$labels_to_add[] = $_POST['label'];
			}
		}
		// Deleting an existing label?
		elseif (isset($_POST['delete'], $_POST['delete_label']))
		{
			foreach ($_POST['delete_label'] AS $label => $dummy)
			{
				unset($the_labels[$label]);
				$labels_to_remove[] = $label;
			}
		}
		// The hardest one to deal with... changes.
		elseif (isset($_POST['save']) && !empty($_POST['label_name']))
		{
			foreach ($the_labels as $id => $name)
			{
				if ($id == -1)
					continue;
				elseif (isset($_POST['label_name'][$id]))
				{
					$_POST['label_name'][$id] = trim(strtr(StringLibrary::escape($_POST['label_name'][$id]), [',' => '&#044;']));

					if (StringLibrary::strlen($_POST['label_name'][$id]) > 30)
						$_POST['label_name'][$id] = StringLibrary::substr($_POST['label_name'][$id], 0, 30);
					if ($_POST['label_name'][$id] != '')
					{
						// Changing the name of this label?
						if ($the_labels[$id] != $_POST['label_name'][$id])
							$label_updates[$id] = $_POST['label_name'][$id];

						$the_labels[(int) $id] = $_POST['label_name'][$id];

					}
					else
					{
						unset($the_labels[(int) $id]);
						$labels_to_remove[] = $id;
						$message_changes[(int) $id] = true;
					}
				}
			}
		}

		// Save any new labels
		if (!empty($labels_to_add))
		{
			$inserts = [];
			foreach ($labels_to_add AS $label)
				$inserts[] = [$user_info['id'], $label];

			$smcFunc['db']->insert('', '{db_prefix}pm_labels', ['id_member' => 'int', 'name' => 'string-30'], $inserts, []);
		}

		// Update existing labels as needed
		if (!empty($label_updates))
		{
			foreach ($label_updates AS $id => $name)
			{
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}pm_labels
					SET name = {string:name}
					WHERE id_label = {int:id_label}',
					[
						'name' => $name,
						'id_label' => $id,
					]
				);
			}
		}

		// Now the fun part... Deleting labels.
		if (!empty($labels_to_remove))
		{
			// First delete the labels
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}pm_labels
				WHERE id_label IN ({array_int:labels_to_delete})',
				[
					'labels_to_delete' => $labels_to_remove,
				]
			);

			// Now remove the now-deleted labels from any PMs...
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}pm_labeled_messages
				WHERE id_label IN ({array_int:labels_to_delete})',
				[
					'labels_to_delete' => $labels_to_remove,
				]
			);

			// Get any PMs with no labels which aren't in the inbox
			$get_stranded_pms = $smcFunc['db']->query('', '
				SELECT pmr.id_pm
				FROM {db_prefix}pm_recipients AS pmr
					LEFT JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)
				WHERE pml.id_label IS NULL
					AND pmr.in_inbox = {int:not_in_inbox}
					AND pmr.deleted = {int:not_deleted}
					AND pmr.id_member = {int:current_member}',
				[
					'not_in_inbox' => 0,
					'not_deleted' => 0,
					'current_member' => $user_info['id'],
				]
			);

			$stranded_messages = [];
			while ($row = $smcFunc['db']->fetch_assoc($get_stranded_pms))
			{
				$stranded_messages[] = $row['id_pm'];
			}

			$smcFunc['db']->free_result($get_stranded_pms);

			// Move these back to the inbox if necessary
			if (!empty($stranded_messages))
			{
				// We now have more messages in the inbox
				$context['labels'][-1]['messages'] += count($stranded_messages);
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}pm_recipients
					SET in_inbox = {int:in_inbox}
					WHERE id_pm IN ({array_int:stranded_messages})
						AND id_member = {int:current_member}',
					[
						'stranded_messages' => $stranded_messages,
						'in_inbox' => 1,
					]
				);
			}

			// Now do the same the rules - check through each rule.
			foreach ($context['rules'] as $k => $rule)
			{
				// Each action...
				foreach ($rule['actions'] as $k2 => $action)
				{
					if ($action['t'] != 'lab' || !in_array($action['v'], $labels_to_remove))
						continue;

					$rule_changes[] = $rule['id'];

					// Can't apply this label anymore if it doesn't exist
					unset($context['rules'][$k]['actions'][$k2]);
				}
			}
		}

		// If we have rules to change do so now.
		if (!empty($rule_changes))
		{
			$rule_changes = array_unique($rule_changes);
			// Update/delete as appropriate.
			foreach ($rule_changes as $k => $id)
				if (!empty($context['rules'][$id]['actions']))
				{
					$smcFunc['db']->query('', '
						UPDATE {db_prefix}pm_rules
						SET actions = {string:actions}
						WHERE id_rule = {int:id_rule}
							AND id_member = {int:current_member}',
						[
							'current_member' => $user_info['id'],
							'id_rule' => $id,
							'actions' => json_encode($context['rules'][$id]['actions']),
						]
					);
					unset($rule_changes[$k]);
				}

			// Anything left here means it's lost all actions...
			if (!empty($rule_changes))
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}pm_rules
					WHERE id_rule IN ({array_int:rule_list})
							AND id_member = {int:current_member}',
					[
						'current_member' => $user_info['id'],
						'rule_list' => $rule_changes,
					]
				);
		}

		// Make sure we're not caching this!
		cache_put_data('labelCounts:' . $user_info['id'], null, 720);

		// To make the changes appear right away, redirect.
		redirectexit('action=pm;sa=manage_labels');
	}
}
