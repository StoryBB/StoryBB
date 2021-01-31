<?php

/**
 * Manages pages.
 * @todo refactor as controller-model
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;
use StoryBB\Helper\Parser;

function ManagePages()
{
	global $txt, $context;

	isAllowedTo('admin_forum');

	$subActions = [
		'list_pages' => 'ListPages',
		'add_page' => 'AddPage',
		'edit_page' => 'EditPage',
	];

	$context['sub_action'] = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'list_pages';
	$subActions[$context['sub_action']]();
}

function ListPages()
{
	global $context, $smcFunc, $txt, $sourcedir, $scripturl, $modSettings;
	require_once($sourcedir . '/Subs-List.php');

	$listOptions = [
		'id' => 'page_list',
		'title' => $txt['admin_page_list'],
		'base_href' => $scripturl . '?action=admin;area=pages',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'default_sort_col' => 'page_title',
		'no_items_label' => $txt['no_pages_yet'],
		'get_items' => [
			'function' => function($start, $items_per_page, $sort) use ($scripturl, $smcFunc)
			{
				$rows = [];
				$request = $smcFunc['db']->query('', '
					SELECT p.id_page, p.page_name, p.page_title, p.show_help, p.show_custom_field, p.custom_field_filter, cf.field_name, cf.in_character
					FROM {db_prefix}page AS p
						LEFT JOIN {db_prefix}custom_fields AS cf ON (p.show_custom_field = cf.id_field)
					ORDER BY {raw:sort}
					LIMIT {int:start}, {int:items_per_page}',
					[
						'start' => $start,
						'items_per_page' => $items_per_page,
						'sort' => $sort,
					]
				);
				while ($row = $smcFunc['db']->fetch_assoc($request))
				{
					$row['link'] = $scripturl . '?action=pages;page=' . $row['page_name'];
					$rows[$row['id_page']] = $row;
				}
				$smcFunc['db']->free_result($request);

				return $rows;
			},
			'params' => [],
		],
		'get_count' => [
			'function' => function() use ($smcFunc)
			{
				return $smcFunc['db']->count('id_page', '{db_prefix}page');
			},
		],
		'columns' => [
			'page_title' => [
				'header' => [
					'value' => $txt['page_title'],
				],
				'data' => [
					'function' => function ($rowData) use ($scripturl)
					{
						return '<a href="' . $scripturl . '?action=pages;page=' . $rowData['page_name'] . '">' . $rowData['page_title'] . '</a>';
					},
				],
				'sort' => [
					'default' => 'p.page_title',
					'reverse' => 'p.page_title DESC',
				],
			],
			'custom_field' => [
				'header' => [
					'value' => $txt['page_display_custom_field'],
				],
				'data' => [
					'function' => function ($rowData) use ($txt)
					{
						if (empty($rowData['field_name']))
						{
							return $txt['page_custom_field_none'];
						}

						return $rowData['field_name'] . ' (' . ($rowData['in_character'] ? $txt['page_custom_field_ic'] : $txt['page_custom_field_ooc']) . ')';
					}
				],
			],
			'actions' => [
				'header' => [
					'value' => '',
				],
				'data' => [
					'function' => function ($rowData) use ($scripturl, $txt)
					{
						return '<a href="' . $scripturl . '?action=admin;area=pages;sa=edit_page;pid=' . $rowData['id_page'] . '" class="button">' . $txt['modify'] . '</a>';
					},
					'class' => 'centercol',
				],
			],
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value' => '<div class="buttonlist righttext"><a href="' . $scripturl . '?action=admin;area=pages;sa=add_page" class="button active">' . $txt['add_page'] . '</a></div>',
			],
		],
	];

	createList($listOptions);

	$context['page_title'] = $txt['admin_page_list'];
	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'page_list';
}

function AddPage()
{
	global $context, $txt, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	$context['page_title'] = $txt['add_page'];
	$context['sub_template'] = 'admin_page_edit';

	$context['page'] = [
		'id_page' => 0,
		'page_name' => '',
		'page_title' => '',
		'page_content' => '',
		'show_help' => 0,
		'show_custom_field' => 0,
		'custom_field_filter' => 0,
	];

	load_page_access();
	load_page_fields();

	// Saving?
	if (isset($_POST['save']))
	{
		check_page();

		if (empty($context['page_errors']))
		{
			// Save the page.
			$context['page']['id_page'] = $smcFunc['db']->insert('insert',
				'{db_prefix}page',
				[
					'page_name' => 'string', 'page_title' => 'string', 'page_content' => 'string',
					'show_help' => 'int', 'show_custom_field' => 'int', 'custom_field_filter' => 'int'
				],
				[
					$context['page']['page_name'], $context['page']['page_title'], $context['page']['page_content'],
					$context['page']['show_help'], $context['page']['show_custom_field'], $context['page']['custom_field_filter'],
				],
				['id_page'],
				1
			);

			// Save the group access.
			save_page_access();

			// Nice message then redirect.
			session_flash('success', $txt['settings_saved']);
			redirectexit('action=admin;area=pages');
		}
	}
	elseif (isset($_POST['preview']))
	{
		check_page();

		$context['page_preview'] = Parser::parse_bbc($context['page']['page_content'], true);
	}

	// Now create the editor.
	$editorOptions = [
		'id' => 'message',
		'value' => un_preparsecode($context['page']['page_content'] ?? ''),
		'labels' => [
			'post_button' => $txt['save'],
		],
		// add height and width for the editor
		'height' => '500px',
		'width' => '100%',
		'preview_type' => 0,
		'required' => false,
	];
	create_control_richedit($editorOptions);
}

function EditPage()
{
	global $context, $txt, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	$context['page_title'] = $txt['edit_page'];
	$context['sub_template'] = 'admin_page_edit';

	$page = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 0;

	$request = $smcFunc['db']->query('', '
		SELECT id_page, page_name, page_title, page_content, show_help, show_custom_field, custom_field_filter
		FROM {db_prefix}page
		WHERE id_page = {int:page}',
		[
			'page' => $page,
		]
	);
	$context['page'] = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	if (empty($context['page']))
	{
		redirectexit('action=admin;area=pages');
	}

	load_page_access();
	load_page_fields();

	if (isset($_POST['delete']))
	{
		check_session();
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}page_access
			WHERE id_page = {int:id_page}',
			[
				'id_page' => $context['page']['id_page'],
			]
		);
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}page
			WHERE id_page = {int:id_page}',
			[
				'id_page' => $context['page']['id_page'],
			]
		);
		redirectexit('action=admin;area=pages');
	}

	// Saving?
	if (isset($_POST['save']))
	{
		check_page();

		if (empty($context['page_errors']))
		{
			// Save the page.
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}page
				SET
					page_name = {string:page_name},
					page_title = {string:page_title},
					page_content = {string:page_content},
					show_help = {int:show_help},
					show_custom_field = {int:show_custom_field},
					custom_field_filter = {int:custom_field_filter}
				WHERE id_page = {int:id_page}',
				$context['page']
			);

			// Save the group access.
			save_page_access();

			// Nice message then redirect.
			session_flash('success', $txt['settings_saved']);
			redirectexit('action=admin;area=pages');
		}
	}
	elseif (isset($_POST['preview']))
	{
		check_page();

		$context['page_preview'] = Parser::parse_bbc($context['page']['page_content'], true);
	}

	// Now create the editor.
	$editorOptions = [
		'id' => 'message',
		'value' => un_preparsecode($context['page']['page_content']),
		'labels' => [
			'post_button' => $txt['save'],
		],
		// add height and width for the editor
		'height' => '500px',
		'width' => '100%',
		'preview_type' => 0,
		'required' => false,
	];
	create_control_richedit($editorOptions);
}

function load_page_access()
{
	global $smcFunc, $context, $txt;

	loadLanguage('ManagePermissions');

	// First we need to load all the groups that we could be doing this with.
	$context['page']['groups']['account'] = [
		-1 => [
			'name' => $txt['membergroups_guests'],
			'access' => 'x',
		],
		0 => [
			'name' => $txt['membergroups_members'],
			'access' => 'x',
		],
	];

	// Now let's load all the groups.
	$request = $smcFunc['db']->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE is_character = {int:not_in_character}
			AND id_group != {int:moderator_group}
		ORDER BY id_group',
		[
			'not_in_character' => 0,
			'moderator_group' => 3,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$context['page']['groups']['account'][$row['id_group']] = [
			'name' => $row['group_name'],
			'access' => 'x',
		];
	}
	$smcFunc['db']->free_result($request);

	// Now load the access if applicable.
	if (!empty($context['page']['id_page']))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_group, allow_deny
			FROM {db_prefix}page_access
			WHERE id_page = {int:page}',
			[
				'page' => $context['page']['id_page'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if ($row['allow_deny'])
			{
				// If it's denied, it's denied, and nothing can override that.
				$context['page']['groups']['account'][$row['id_group']]['access'] = 'd';
			}
			elseif ($context['page']['groups']['account'][$row['id_group']]['access'] != 'd')
			{
				// If we have an entry this means we're allowing it, as long as it's not already denied.
				$context['page']['groups']['account'][$row['id_group']]['access'] = 'a';
			}
		}
		$smcFunc['db']->free_result($request);
	}

	$context['page']['groups']['account'][1]['access'] = 'a';
	$context['page']['groups']['account'][1]['frozen'] = true;
}

function update_page_access_from_post()
{
	global $context;

	foreach ($context['page']['groups']['account'] as $id_group => $group)
	{
		if (!empty($group['frozen']))
		{
			continue;
		}

		if (isset($_POST['access']) && is_array($_POST['access']) && isset($_POST['access'][$id_group]))
		{
			$new_access = $_POST['access'][$id_group];
			if (in_array($new_access, ['a', 'x', 'd']))
			{
				$context['page']['groups']['account'][$id_group]['access'] = $new_access;
			}
		}
	}
}

function save_page_access()
{
	global $context, $smcFunc;

	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}page_access
		WHERE id_page = {int:page}',
		[
			'page' => $context['page']['id_page'],
		]
	);

	$insert = [];

	foreach ($context['page']['groups']['account'] as $id_group => $group)
	{
		if ($id_group == 1)
		{
			continue;
		}
		// If the access is 'x', it means we don't need to save it as there's no other rule to override it.
		if ($group['access'] == 'x')
		{
			continue;
		}

		$insert[] = [$context['page']['id_page'], $id_group, $group['access'] == 'd' ? 1 : 0];
	}

	if (!empty($insert))
	{
		$smcFunc['db']->insert('insert',
			'{db_prefix}page_access',
			['id_page' => 'int', 'id_group' => 'int', 'allow_deny' => 'int'],
			$insert,
			['id_page', 'id_group']
		);
	}
}

function load_page_fields()
{
	global $context, $smcFunc;

	$context['page']['fields'] = [
		'account' => [],
		'character' => [],
	];

	$request = $smcFunc['db']->query('', '
		SELECT id_field, in_character, field_name
		FROM {db_prefix}custom_fields
		WHERE active = 1
		ORDER BY field_order');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$context['page']['fields'][$row['in_character'] ? 'character' : 'account'][$row['id_field']] = $row['field_name'];
	}
	$smcFunc['db']->free_result($request);
}

function check_page()
{
	global $context, $txt, $smcFunc;

	checkSession();

	$context['page_errors'] = [];

	$context['page']['page_name'] = StringLibrary::escape($_POST['page_name'] ?? '');
	if (!preg_match('~^[a-z0-9_-]+$~i', $context['page']['page_name']) || strlen($context['page']['page_name']) > 64)
	{
		$context['page_errors'][] = $txt['invalid_page_name'];
	}

	$context['page']['page_title'] = StringLibrary::htmltrim(StringLibrary::escape($_POST['page_title'] ?? '', ENT_QUOTES));
	if (empty($context['page']['page_title']))
	{
		$context['page_errors'][] = $txt['empty_page_title'];
	}
	else
	{
		// Check for duplicates.
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(id_page)
			FROM {db_prefix}page
			WHERE page_name = {string:page_name}
				AND id_page != {int:id_page}',
			[
				'id_page' => $context['page']['id_page'],
				'page_name' => $context['page']['page_name'],
			]
		);
		[$count] = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		if ($count)
		{
			$context['page_errors'][] = $txt['duplicate_page_name'];
		}
	}

	$context['page']['page_content'] = StringLibrary::escape($_POST['message'] ?? '', ENT_QUOTES);
	preparsecode($context['page']['page_content']);
	// We don't test against empty because it's OK for the content to be empty - it might just be a custom field page.
	// And if admins make a page that's empty, that's up to them...

	$context['page']['show_help'] = !empty($_POST['show_help']) ? 1 : 0;

	if (isset($context['page']['fields']['account'][$_POST['show_custom_field']]) || isset($context['page']['fields']['character'][$_POST['show_custom_field']]))
	{
		$context['page']['show_custom_field'] = (int) $_POST['show_custom_field'];
	}
	else
	{
		$context['page']['show_custom_field'] = 0;
	}

	$_POST['custom_field_filter'] = isset($_POST['custom_field_filter']) ? (int) $_POST['custom_field_filter'] : 0;
	$context['page']['custom_field_filter'] = min(max($_POST['custom_field_filter'], 0), 4); // Clamp the value to between 0 and 4.

	update_page_access_from_post();
}
