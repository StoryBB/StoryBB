<?php

/**
 * Displays the character sheet page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\StringLibrary;
use StoryBB\Model\Character;

class CharacterCreate extends AbstractProfileController
{
	use CharacterTrait;

	public function do_standard_session_check()
	{
		return false;
	}

	public function display_action()
	{
		global $context, $smcFunc, $txt, $sourcedir, $modSettings;

		require_once($sourcedir . '/Drafts.php');

		loadLanguage('Admin');

		$context['sub_template'] = 'profile_character_create';

		if (!isset($context['character']))
		{
			$context['character'] = [
				'character_name' => '',
			];
		}

		if (!isset($context['form_errors']))
		{
			$context['form_errors'] = [];
		}

		// Make an editor box
		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Subs-Editor.php');

		$this->load_custom_fields(true);
		foreach ($context['character']['custom_fields'] as $key => $value)
		{
			if ($value['show_profile'] != 'char')
			{
				unset ($context['character']['custom_fields'][$key]);
			}
		}
	}

	public function post_action()
	{
		global $context, $smcFunc, $txt, $sourcedir, $modSettings;

		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Profile-Modify.php');
		require_once($sourcedir . '/Drafts.php');

		$context['character']['character_name'] = !empty($_POST['char_name']) ? StringLibrary::escape(trim($_POST['char_name']), ENT_QUOTES) : '';

		$context['form_errors'] = [];

		$session = checkSession('post', '', false);
		if ($session !== '')
		{
			$context['form_errors'][] = $txt['character_create_session_timeout'];
		}

		if ($context['character']['character_name'] == '')
		{
			$context['form_errors'][] = $txt['char_error_character_must_have_name'];
		}
		else
		{
			// Check if the name already exists.
			$result = $smcFunc['db']->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}characters
				WHERE character_name LIKE {string:new_name}',
				[
					'new_name' => $context['character']['character_name'],
				]
			);
			list ($matching_names) = $smcFunc['db']->fetch_row($result);
			$smcFunc['db']->free_result($result);

			if ($matching_names)
				$context['form_errors'][] = $txt['char_error_duplicate_character_name'];
		}

		if (!empty($context['form_errors']))
		{
			return $this->display_action();
		}

		// So no errors, we can save this new character, yay!
		$smcFunc['db']->insert('insert',
			'{db_prefix}characters',
			['id_member' => 'int', 'character_name' => 'string', 'avatar' => 'string',
				'signature' => 'string', 'id_theme' => 'int', 'posts' => 'int',
				'date_created' => 'int', 'last_active' => 'int',
				'is_main' => 'int', 'main_char_group' => 'int', 'char_groups' => 'string',
				'char_sheet' => 'int', 'retired' => 'int'],
			[$context['id_member'], $context['character']['character_name'], '',
				'', 0, 0,
				time(), time(),
				0, 0, '',
				0, 0],
			['id_character']
		);
		$context['character']['id_character'] = $smcFunc['db']->inserted_id();
		trackStats(['chars' => '+']);

		makeCustomFieldChanges($context['id_member'], $context['character']['id_character'], 'char');

		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}
}
