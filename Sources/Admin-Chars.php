<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

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

function CharacterSheets()
{
	global $context, $smcFunc, $txt, $sourcedir, $scripturl;
	require_once($sourcedir . '/Subs-List.php');

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
					SELECT mem.id_member, mem.real_name, chars.id_character,
						chars.character_name, MAX(csv.created_time) AS latest_version,
						MAX(csv.approved_time) AS last_approval, MAX(csv.approval_state) AS approval_state
					FROM {db_prefix}character_sheet_versions AS csv
					INNER JOIN {db_prefix}characters AS chars ON (csv.id_character = chars.id_character)
					INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
					GROUP BY csv.id_character
					HAVING approval_state = 1
					ORDER BY {raw:sort}',
					[
						'sort' => $sort,
					]
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$rows[] = $row;
				}
				$smcFunc['db_free_result']($request);
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
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . '" target="_blank">' . $rowData['real_name'] . '</a>';
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
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . ';area=characters;char=' . $rowData['id_character'] . '" target="_blank">' . $rowData['character_name'] . '</a>';
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
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . ';area=characters;char=' . $rowData['id_character'] . ';sa=sheet" target="_blank">' . $txt['char_sheet'] . '</a>';
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
						return $rowData['last_approval'] ? '<span class="generic_icons approve_button" title="' . $txt['yes'] . '"></span>' : '<span class="generic_icons unapprove_button" title="' . $txt['no'] . '"></span>';
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

function CharacterImmersion($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings, $smcFunc, $language, $sourcedir;

	loadLanguage('Help');
	loadLanguage('ManageSettings');
	require_once($sourcedir . '/ManageServer.php');

	$config_vars = array(
			array('check', 'character_selector_post'),
		'',
			array('select', 'characters_ic_may_post', array(
				'ic' => $txt['ic_boards_only'],
				'icooc' => $txt['ic_and_ooc_boards'],
			)),
			array('select', 'characters_ooc_may_post', array(
				'ooc' => $txt['ooc_boards_only'],
				'icooc' => $txt['ic_and_ooc_boards'],
			)),
	);

	call_integration_hook('integrate_immersion_settings', array(&$config_vars));

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

?>