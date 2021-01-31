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

	public function display_action()
	{
		global $context, $smcFunc, $txt, $sourcedir;

		loadLanguage('Admin');

		$context['sub_template'] = 'profile_character_create';

		if (!isset($context['character']))
		{
			$context['character'] = [
				'character_name' => '',
				'sheet' => '',
			];
		}

		if (!isset($context['form_errors']))
		{
			$context['form_errors'] = [];
		}

		// Make an editor box
		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Subs-Editor.php');

		// Now create the editor.
		$editorOptions = [
			'id' => 'message',
			'value' => un_preparsecode($context['character']['sheet']),
			'labels' => [
				'post_button' => $txt['save'],
			],
			// add height and width for the editor
			'height' => '500px',
			'width' => '80%',
			'preview_type' => 0,
			'required' => true,
		];
		create_control_richedit($editorOptions);

		Character::load_sheet_templates();

		addInlineJavascript('
		var sheet_templates = ' . json_encode($context['sheet_templates']) . ';
		$("#insert_char_template").on("click", function (e) {
			e.preventDefault();
			var tmpl = $("#char_sheet_template").val();
			if (sheet_templates.hasOwnProperty(tmpl))
				$("#message").data("sceditor").InsertText(sheet_templates[tmpl].body);
		});', true);

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
		global $context, $smcFunc, $txt, $sourcedir;

		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Profile-Modify.php');

		$context['character']['character_name'] = !empty($_POST['char_name']) ? StringLibrary::escape(trim($_POST['char_name']), ENT_QUOTES) : '';
		$message = StringLibrary::escape($_POST['message'], ENT_QUOTES);
		preparsecode($message);
		$context['character']['sheet'] = $message;

		if ($context['character']['character_name'] == '')
			$context['form_errors'][] = $txt['char_error_character_must_have_name'];
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

		if (empty($context['form_errors']))
		{
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

			if (!empty($context['character']['sheet']))
			{
				// Also gotta insert this.
				$smcFunc['db']->insert('insert',
					'{db_prefix}character_sheet_versions',
					['sheet_text' => 'string', 'id_character' => 'int', 'id_member' => 'int',
						'created_time' => 'int', 'id_approver' => 'int', 'approved_time' => 'int', 'approval_state' => 'int'],
					[$context['character']['sheet'], $context['character']['id_character'], $context['id_member'],
						time(), 0, 0, 0],
					['id_version']
				);
			}
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}

		return $this->display_action();
	}
}
