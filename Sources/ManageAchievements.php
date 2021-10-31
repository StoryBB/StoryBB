<?php

/**
 * Manages achievements.
 * @todo refactor as controller-model
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\ClassManager;
use StoryBB\Model\Achievement;

function ManageAchievements()
{
	global $txt, $context;

	loadLanguage('ManageAchievements');

	isAllowedTo('admin_forum');

	$subActions = [
		'list_achievements' => 'ListAchievements',
		'add_achieve' => 'AddAchievement',
		'save_achieve' => 'SaveAchievement',
	];

	$context['sub_action'] = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'list_achievements';
	$subActions[$context['sub_action']]();
}

function ListAchievements()
{
	global $context, $smcFunc, $txt;

	$context['achievements'] = [
		'account' => [],
		'character' => [],
	];

	$request = $smcFunc['db']->query('', '
		SELECT id_achieve, achievement_name, achievement_type
		FROM {db_prefix}achieve
		ORDER BY achievement_name');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$type = $row['achievement_type'] == Achievement::ACHIEVEMENT_TYPE_ACCOUNT ? 'account' : 'character';
		$context['achievements'][$type][] = $row;
	}
	$smcFunc['db']->free_result($request);

	$context['page_title'] = $txt['achievements'];
	$context['sub_template'] = 'admin_achievement_list';
}

function AddAchievement()
{
	global $txt, $context;

	$context['achievement_type'] = (($_GET['type'] ?? 'account') == 'character') ? 'character' : 'account';

	$context['achievement'] = [
		'id' => 0,
		'name' => '',
		'desc' => '',
		'manually_awardable' => true,
		'active' => true,
		'hidden' => false,
	];

	$context['achievement_configuration'] = [
		'criteria' => [],
		'unlock_criteria' => [],
		'outcomes' => [],
	];

	// Get the criteria types we can have.
	$base = $context['achievement_type'] == 'account' ? 'StoryBB\\Achievement\\AccountAchievement' : 'StoryBB\\Achievement\\CharacterAchievement';
	foreach (ClassManager::get_classes_implementing($base) as $class)
	{
		$classname = substr(strrchr($class, '\\'), 1);
		$context['achievement_configuration']['criteria'][$classname] = [
			'name' => $class::get_label(),
			'partial' => $class::get_template_partial(),
		];

		if (is_subclass_of($class, 'StoryBB\\Achievement\\UnlockableAchievement'))
		{
			$context['achievement_configuration']['unlock_criteria'][$classname] = [
				'name' => $class::get_label(),
				'partial' => $class::get_template_partial(),
			];
		}
	}

	// We always want account outcomes.
	foreach (ClassManager::get_classes_implementing('StoryBB\\Achievement\\AccountOutcome') as $class)
	{
		$classname = substr(strrchr($class, '\\'), 1);
		$context['achievement_configuration']['outcomes'][$classname] = [
			'name' => $class::get_label(),
		];
	}
	if ($context['achievement_type'] == 'character')
	{
		foreach (ClassManager::get_classes_implementing('StoryBB\\Achievement\\CharacterOutcome') as $class)
		{
			$basename = substr(strrchr($class, '\\'), 1);
			$context['achievement_configuration']['outcomes'][$basename] = [
				'name' => $class::get_label(),
			];
		}
	}

	$context['metadata']['achievements'] = load_metadata_achievements();
	$context['metadata']['boards'] = load_metadata_boards();

	$context['page_title'] = $txt['add_achievement'];
	$context['sub_template'] = 'admin_achievement_edit';
}

function SaveAchievement()
{

}

function load_metadata_achievements(): array
{
	global $smcFunc;

	$return = [];

	$request = $smcFunc['db']->query('', '
		SELECT id_achieve, achievement_name, achievement_type
		FROM {db_prefix}achieve
		ORDER BY achievement_name');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$type = $row['achievement_type'] == Achievement::ACHIEVEMENT_TYPE_ACCOUNT ? 'account' : 'character';
		$return[$type][$row['id_achieve']] = $row;
	}
	$smcFunc['db']->free_result($request);

	return $return;
}

function load_metadata_boards(): array
{
	global $smcFunc;

	$return = [];

	$request = $smcFunc['db']->query('', '
		SELECT c.name AS category_name, b.id_board, b.id_cat, b.name AS board_name, b.child_level, b.in_character
		FROM {db_prefix}boards AS b
		JOIN {db_prefix}categories AS c ON (b.id_cat = c.id_cat)
		ORDER BY b.board_order');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$row['in_character'] = !empty($row['in_character']);

		$return[$row['id_cat']]['name'] = $row['category_name'];
		$return[$row['id_cat']]['boards'][$row['id_board']] = $row;
	}
	$smcFunc['db']->free_result($request);

	return $return;
}
