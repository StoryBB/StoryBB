<?php

/**
 * Displays the character skills page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\App;

class CharacterSkills extends AbstractProfileController
{
	use CharacterTrait;

	public function display_action()
	{
		global $txt, $user_info, $scripturl, $modSettings;
		global $context, $smcFunc, $board;

		$this->init_character();

		$context['sub_template'] = 'profile_character_skills';

		$context['character']['skills'] = [];
		$request = $smcFunc['db']->query('', '
			SELECT ss.id_skillset, ss.skillset_name, sb.id_branch, sb.skill_branch_name, s.id_skill, s.skill_name, COALESCE(cs.id_character_skill, 0) AS has_skill
			FROM {db_prefix}skillsets AS ss
				INNER JOIN {db_prefix}skill_branches AS sb ON (sb.id_skillset = ss.id_skillset)
				INNER JOIN {db_prefix}skills AS s ON (s.id_branch = sb.id_branch)
				LEFT JOIN {db_prefix}character_skills AS cs ON (cs.id_character = {int:character} AND cs.id_skill = s.id_skill)
			WHERE ss.active = 1
				AND sb.active = 1
			ORDER BY ss.id_skillset, sb.branch_order, s.skill_order',
			[
				'character' => $context['character']['id_character'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['character']['skills'][$row['id_skillset']]['title'] = $row['skillset_name'];
			$context['character']['skills'][$row['id_skillset']]['skills'][$row['id_branch']]['title'] = $row['skill_branch_name'];
			if (!isset($context['character']['skills'][$row['id_skillset']]['skills'][$row['id_branch']]['opened']))
			{
				$context['character']['skills'][$row['id_skillset']]['skills'][$row['id_branch']]['opened'] = false;
			}
			if (!empty($row['has_skill']))
			{
				$context['character']['skills'][$row['id_skillset']]['skills'][$row['id_branch']]['opened'] = true;
			}
			$context['character']['skills'][$row['id_skillset']]['skills'][$row['id_branch']]['skills'][$row['id_skill']] = ['has' => !empty($row['has_skill']), 'name' => $row['skill_name']];
		}
		$smcFunc['db']->free_result($request);

		// Just in case there's inactive skills too, we should keep those.
		$context['character']['inactive_skills'] = [];
		$request = $smcFunc['db']->query('', '
			SELECT s.id_skill, COALESCE(cs.id_character_skill, 0) AS has_skill
			FROM {db_prefix}skillsets AS ss
				INNER JOIN {db_prefix}skill_branches AS sb ON (sb.id_skillset = ss.id_skillset)
				INNER JOIN {db_prefix}skills AS s ON (s.id_branch = sb.id_branch)
				LEFT JOIN {db_prefix}character_skills AS cs ON (cs.id_character = {int:character} AND cs.id_skill = s.id_skill)
			WHERE ss.active = 0
				OR sb.active = 0',
			[
				'character' => $context['character']['id_character'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!empty($row['has_skill']))
			{
				$context['character']['inactive_skills'][] = $row['id_skiil'];
			}
		}
		$smcFunc['db']->free_result($request);
	}

	public function post_action()
	{
		global $txt, $user_info, $scripturl, $modSettings;
		global $context, $smcFunc, $board;

		$this->init_character();

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}character_skills
			WHERE id_character = {int:character}',
			[
				'character' => $context['character']['id_character'],
			]
		);

		$skills = (array) ($_POST['skill'] ?? []);
		$insert = [];
		$request = $smcFunc['db']->query('', '
			SELECT s.id_skill
			FROM {db_prefix}skills AS s'
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (isset($skills[$row['id_skill']])) {
				$insert[] = [$context['character']['id_character'], $row['id_skill']];
			}
		}
		$smcFunc['db']->free_result($request);

		if (!empty($insert))
		{
			$smcFunc['db']->insert('insert',
				'{db_prefix}character_skills',
				['id_character' => 'int', 'id_skill' => 'int'],
				$insert,
				['id_character', 'id_skill']
			);
		}

		redirectexit('action=profile;u=' . $this->params['u'] . ';area=character_skills;char=' . $context['character']['id_character']);
	}
}
