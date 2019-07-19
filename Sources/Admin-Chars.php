<?php
/**
 * Provides functions for managing several character-focused features in the administration area.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * Front end controller for the character sheet templates section in the admin area.
 */
function CharacterTemplates()
{
	$subactions = [
		'index' => 'char_template_list',
		'add' => 'char_template_add',
		'edit' => 'char_template_edit',
		'reorder' => 'char_template_reorder',
		'save' => 'char_template_save',
	];

	$sa = isset($_GET['sa'], $subactions[$_GET['sa']]) ? $subactions[$_GET['sa']] : $subactions['index'];
	$sa();
}

/**
 * Front end controller for the character sheet template list in the admin panel.
 */
function char_template_list()
{
	global $smcFunc, $context, $txt;

	$context['char_templates'] = [];
	$request = $smcFunc['db_query']('', '
		SELECT id_template, template_name, position
		FROM {db_prefix}character_sheet_templates
		ORDER BY position ASC');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['char_templates'][$row['id_template']] = $row;
	}
	$smcFunc['db_free_result']($request);

	$context['page_title'] = $txt['char_templates'];
	$context['sub_template'] = 'admin_character_template_list';
	loadJavascriptFile('jquery-ui-1.12.1-sortable.min.js', ['default_theme' => true]);
	addInlineJavascript('
	$(\'.sortable\').sortable({handle: ".handle"});', true);
}

/**
 * Front end controller for the character sheet template reordering tool in the admin panel.
 */
function char_template_reorder()
{
	global $smcFunc;
	if (isset($_POST['template']) && is_array($_POST['template']))
	{
		checkSession();
		$order = 1;
		foreach ($_POST['template'] as $template) {
			$template = (int) $template;
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}character_sheet_templates
				SET position = {int:order}
				WHERE id_template = {int:template}',
				[
					'order' => $order,
					'template' => $template,
				]
			);
			$order++;
		}
	}
	redirectexit('action=admin;area=templates');
}

/**
 * Front end controller for handling adding a new character sheet template in the admin panel.
 */
function char_template_add()
{
	global $context, $txt, $sourcedir;
	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	// Now create the editor.
	$editorOptions = [
		'id' => 'message',
		'value' => '',
		'labels' => [
			'post_button' => $txt['save'],
		],
		// add height and width for the editor
		'height' => '500px',
		'width' => '100%',
		'preview_type' => 0,
		'required' => true,
	];
	create_control_richedit($editorOptions);
	$context['template_name'] = '';
	$context['template_id'] = 0;

	$context['page_title'] = $txt['char_templates_add'];
	$context['sub_template'] = 'admin_character_template_edit';
}

/**
 * Front end controller for editing a character sheet template in the admin panel.
 */
function char_template_edit()
{
	global $context, $txt, $sourcedir, $smcFunc;
	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	$template_id = isset($_GET['template_id']) ? (int) $_GET['template_id'] : 0;
	$request = $smcFunc['db_query']('', '
		SELECT id_template, template_name, template
		FROM {db_prefix}character_sheet_templates
		WHERE id_template = {int:template}',
		[
			'template' => $template_id,
		]
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	if (empty($row))
	{
		redirectexit('action=admin;area=templates');
	}
	$context['template_id'] = $template_id;
	$context['template_name'] = $row['template_name'];

	// Now create the editor.
	$editorOptions = [
		'id' => 'message',
		'value' => un_preparsecode($row['template']),
		'labels' => [
			'post_button' => $txt['save'],
		],
		// add height and width for the editor
		'height' => '500px',
		'width' => '100%',
		'preview_type' => 0,
		'required' => true,
	];
	create_control_richedit($editorOptions);

	$context['page_title'] = $txt['char_templates_edit'];
	$context['sub_template'] = 'admin_character_template_edit';
}

/**
 * Front end controller for saving a character sheet template in the admin panel.
 */
function char_template_save()
{
	global $context, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	checkSession();
	if (empty($_POST['template_name']) || empty($_POST['message']))
		redirectexit('action=admin;area=templates');

	$template_name = $smcFunc['htmlspecialchars'](trim($_POST['template_name']), ENT_QUOTES);
	$template = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
	preparsecode($template);

	$template_id = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;

	if (empty($template_id)) {
		// New insertion
		$smcFunc['db_insert']('',
			'{db_prefix}character_sheet_templates',
			['template_name' => 'string', 'template' => 'string', 'position' => 'int'],
			[$template_name, $template, 0],
			['id_template']
		);
	} else {
		// Updating an existing one
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}character_sheet_templates
			SET template_name = {string:template_name},
				template = {string:template}
			WHERE id_template = {int:template_id}',
			[
				'template_id' => $template_id,
				'template_name' => $template_name,
				'template' => $template,
			]
		);
	}

	redirectexit('action=admin;area=templates');
}

/**
 * Front end controller for showing the character sheet approval queue.
 */
function CharacterSheets()
{
	global $context, $smcFunc, $txt, $sourcedir, $scripturl;
	require_once($sourcedir . '/Subs-List.php');
	loadLanguage('Profile');

	$listOptions = [
		'id' => 'approval_queue',
		'title' => $txt['char_sheet_admin'],
		'base_href' => $scripturl . '?action=admin;area=sheets',
		'default_sort_col' => 'updated',
		'no_items_label' => $txt['no_pending_sheets'],
		'get_items' => [
			'function' => function($start, $items_per_page, $sort)
			{
				global $smcFunc;
				$rows = [];
				$request = $smcFunc['db_query']('', '
					SELECT csv.id_character, MAX(csv.created_time) AS latest_version,
						MAX(csv.approved_time) AS last_approval, MAX(csv.approval_state) AS approval_state
					FROM {db_prefix}character_sheet_versions AS csv
					GROUP BY csv.id_character
					ORDER BY latest_version ASC',
					[
						'sort' => $sort,
					]
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					// If it's not actually pending approval (strict mode makes this complicated), skip it.
					if (empty($row['approval_state']))
						continue;

					$rows[$row['id_character']] = $row;
				}
				$smcFunc['db_free_result']($request);

				// Having fetched whichever versions are relevant, we now need to fetch the rest of the data.
				if (!empty($rows))
				{
					$request = $smcFunc['db_query']('', '
						SELECT mem.id_member, mem.real_name, chars.id_character, chars.character_name
						FROM {db_prefix}characters AS chars
						INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
						WHERE chars.id_character IN ({array_int:ids})',
						[
							'ids' => array_keys($rows),
						]
					);
					while ($row = $smcFunc['db_fetch_assoc']($request))
					{
						$rows[$row['id_character']] = array_merge($rows[$row['id_character']], $row);
					}
					$smcFunc['db_free_result']($request);
				}

				// And make sure any stray entries are cleaned.
				foreach ($rows as $id_char => $row)
				{
					if (empty($row['character_name']))
						unset($rows[$id_char]);
				}

				// Owing to the fact that we've split the query in such a way we can't order by it... reorder.
				$ascending = 1;
				if (substr($sort, -5) == ' DESC')
				{
					$ascending = -1;
					$sort = substr($sort, 0, -5);
				}

				$fields = [
					'chars.character_name' => 'character_name',
					'mem.real_name' => 'real_name',
					'latest_version' => 'latest_version',
				];
				$sort = isset($fields[$sort]) ? $fields[$sort] : 'character_name';

				uasort($rows, function($a, $b) use ($sort, $ascending) {
					return $a[$sort] == $b[$sort] ? 0 : ($a[$sort] < $b[$sort] ? $ascending : -$ascending);
				});

				return $rows;
			},
			'params' => ['regular'],
		],
		'columns' => [
			'name' => [
				'header' => [
					'value' => $txt['name'],
				],
				'data' => [
					'function' => function ($rowData) use ($scripturl)
					{
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . '" target="_blank" rel="noopener">' . $rowData['real_name'] . '</a>';
					}
				],
				'sort' => [
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				],
			],
			'char_name' => [
				'header' => [
					'value' => str_replace(':', '', $txt['char_name']),
				],
				'data' => [
					'function' => function ($rowData) use ($scripturl)
					{
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . ';area=characters;char=' . $rowData['id_character'] . '" target="_blank" rel="noopener">' . $rowData['character_name'] . '</a>';
					}
				],
				'sort' => [
					'default' => 'chars.character_name',
					'reverse' => 'chars.character_name DESC',
				],
			],
			'char_sheet' => [
				'header' => [
					'value' => '',
				],
				'data' => [
					'function' => function ($rowData) use ($txt, $scripturl)
					{
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . ';area=characters;char=' . $rowData['id_character'] . ';sa=sheet" target="_blank" rel="noopener">' . $txt['char_sheet'] . '</a>';
					},
					'class' => 'centercol',
				],
			],
			'updated' => [
				'header' => [
					'value' => $txt['last_updated'],
				],
				'data' => [
					'db' => 'latest_version',
					'timeformat' => true,
				],
				'sort' => [
					'default' => 'latest_version',
					'reverse' => 'latest_version DESC',
				],
			],
			'approved' => [
				'header' => [
					'value' => $txt['previously_approved'],
				],
				'data' => [
					'function' => function ($rowData) use ($txt)
					{
						return $rowData['last_approval'] ? '<span class="main_icons approve_button" title="' . $txt['yes'] . '"></span>' : '<span class="main_icons unapprove_button" title="' . $txt['no'] . '"></span>';
					},
					'class' => 'centercol',
				],
			],
		],
	];

	createList($listOptions);

	$context['page_title'] = $txt['char_sheet_admin'];
	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'approval_queue';
}

/**
 * Displaying/saving the character immersion settings page.
 *
 * @param bool $return_config If true, return the configuration rather than processing the page.
 * @return array Returns the configuration definition if $return_config is set to true, otherwise does a page render/redirect
 */
function CharacterImmersion($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings, $smcFunc, $language, $sourcedir;

	loadLanguage('Help');
	loadLanguage('ManageSettings');
	require_once($sourcedir . '/ManageServer.php');

	$config_vars = [
			['select', 'characters_ic_may_post', [
				'ic' => $txt['ic_boards_only'],
				'icooc' => $txt['ic_and_ooc_boards'],
			]],
			['select', 'characters_ooc_may_post', [
				'ooc' => $txt['ooc_boards_only'],
				'icooc' => $txt['ic_and_ooc_boards'],
			]],
		'',
			['select', 'enable_immersive_mode', [
				'user_on' => $txt['enable_immersive_mode_user_on'],
				'user_off' => $txt['enable_immersive_mode_user_off'],
				'off' => $txt['enable_immersive_mode_off'],
				'on' => $txt['enable_immersive_mode_on'],
			]],
	];

	call_integration_hook('integrate_immersion_settings', [&$config_vars]);

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_immersion_settings');

		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);

		writeLog();
		redirectexit('action=admin;area=immersion');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=immersion;save';
	$context['settings_title'] = $txt['immersion'];
	$context['page_title'] = $txt['immersion'];

	prepareDBSettingContext($config_vars);
}
