<?php

/**
 * Manages topic prefixes.
 * @todo refactor as controller-model
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;
use StoryBB\Model\TopicPrefix;

function ManagePrefixes()
{
	global $context;

	isAllowedTo('admin_forum');

	$subActions = [
		'list_prefixes' => 'ListPrefixes',
		'add_prefix' => 'AddPrefix',
		'edit_prefix' => 'EditPrefix',
		'save_prefix' => 'SavePrefix',
		'update_order' => 'SaveOrder',
	];

	$context['sub_action'] = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'list_prefixes';
	$subActions[$context['sub_action']]();
}

function ListPrefixes()
{
	global $txt, $context, $modSettings, $smcFunc;

	loadLanguage('ManageSettings');

	$context['prefixes'] = TopicPrefix::get_prefixes();

	$context['page_title'] = $txt['topic_prefixes'];

	$context['sub_template'] = 'admin_prefixes_list';

	loadJavascriptFile('jquery-ui-1.12.1-sortable.min.js', ['default_theme' => true]);
	addInlineJavascript('
	$(\'.sortable\').sortable({
		handle: ".draggable-handle",
		update: function (event, ui) {
			console.log($(this));
			$(this).closest("form").find("button.hiddenelement").removeClass("hiddenelement");
		}
	});', true);
}

function AddPrefix()
{
	global $txt, $context;

	$context['page_title'] = $txt['add_prefix'];

	$context['sub_template'] = 'admin_prefixes_edit';

	$context['prefix'] = [
		'id_prefix' => 0,
		'name' => $context['prefix_name_escaped'] ?? '',
		'css_class' => $context['css_class'] ?? '',
		'custom' => false,
		'selectable' => $context['selectable'] ?? true,
	];

	$context['prefix_styles'] = get_standard_prefixes();

	load_prefix_access();
	load_prefix_boards();
}

function EditPrefix()
{
	global $txt, $context;

	$context['page_title'] = $txt['edit_prefix'];

	$context['sub_template'] = 'admin_prefixes_edit';

	$prefix = isset($_REQUEST['prefix']) ? (int) $_REQUEST['prefix'] : 0;
	$prefixes = TopicPrefix::get_prefixes(['prefixes' => [$prefix]]);

	if (empty($prefixes))
	{
		redirectexit('action=admin;area=topicprefixes');
	}

	$prefixrow = current($prefixes);
	$context['prefix'] = [
		'id_prefix' => $prefixrow['id_prefix'],
		'name' => $context['prefix_name_escaped'] ?? $prefixrow['name'],
		'css_class' => $context['css_class'] ?? $prefixrow['css_class'],
		'custom' => false,
		'selectable' => $context['selectable'] ?? !empty($prefixrow['selectable']),
	];

	$context['prefix_styles'] = get_standard_prefixes();

	if (!isset($context['prefix']['groups']))
	{
		load_prefix_access();
	}
	if (!isset($context['prefix']['board_categories']))
	{
		load_prefix_boards();
	}
}

function SavePrefix()
{
	global $txt, $context;

	checkSession();

	$prefix = isset($_REQUEST['prefix']) ? (int) $_REQUEST['prefix'] : 0;
	if (!empty($prefix))
	{
		$prefixes = TopicPrefix::get_prefixes(['prefixes' => [$prefix]]);
	}

	$context['errors'] = [];

	$context['prefix_name_escaped'] = StringLibrary::escape($_POST['prefix_name'] ?? '', ENT_QUOTES);
	if (empty($context['prefix_name_escaped']))
	{
		$context['errors'][] = $txt['no_prefix_name'];
	}
	$context['css_class'] = trim($_POST['prefix_style'] ?? '');
	if (empty($context['css_class']) || !preg_match('/^[a-z0-9\-_ ]+$/i', $context['css_class']))
	{
		$context['css_class'] = StringLibrary::escape($context['css_class'], ENT_QUOTES); // Escape it before returning it to the form.
		$context['errors'][] = $txt['invalid_prefix_style'];
	}

	$context['selectable'] = !empty($_POST['selectable']);

	load_prefix_access();
	load_prefix_boards();

	// Add the prefix and return.
	$_POST['board'] = isset($_POST['board']) && is_array($_POST['board']) ? $_POST['board'] : [];
	$prefix_boards = [];
	foreach ($context['prefix']['board_categories'] as $id_cat => $category)
	{
		foreach ($category['boards'] as $board)
		{
			$active = isset($_POST['board'][$board['id_board']]);

			$context['prefix']['board_categories'][$id_cat]['boards'][$board['id_board']]['active'] = $active; // Reset in case of error.
			if ($active)
			{
				$prefix_boards[] = $board['id_board'];
			}
		}
	}

	$allow = [];
	$deny = [];

	$_POST['access'] = isset($_POST['access']) && is_array($_POST['access']) ? $_POST['access'] : [];
	foreach ($context['prefix']['groups'] as $type => $group_list)
	{
		foreach ($group_list as $id_group => $group)
		{
			if (!empty($group['frozen']))
			{
				continue;
			}

			if (!isset($_POST['access'][$id_group]) || !in_array($_POST['access'][$id_group], ['a', 'x', 'd']))
			{
				continue;
			}

			$context['prefix']['groups'][$type][$id_group]['access'] = $_POST['access'][$id_group];
			if ($_POST['access'][$id_group] == 'd')
			{
				$deny[] = $id_group;
			}
			elseif ($_POST['access'][$id_group] == 'a')
			{
				$allow[] = $id_group;
			}
		}
	}

	if (!empty($context['errors']))
	{
		return !empty($prefixes) ? EditPrefix() : AddPrefix();
	}

	if (!empty($prefixes))
	{
		TopicPrefix::update_prefix($prefix, [
			'name' => $context['prefix_name_escaped'],
			'css_class' => $context['css_class'],
			'selectable' => $context['selectable'],
		]);
	}
	else
	{
		$prefix = TopicPrefix::create_prefix($context['prefix_name_escaped'], $context['css_class'], $context['selectable']);
	}

	TopicPrefix::set_prefix_groups($prefix, $allow, $deny);
	TopicPrefix::set_prefix_boards($prefix, $prefix_boards);

	redirectexit('action=admin;area=topicprefixes');
}

function SaveOrder()
{
	global $smcFunc;

	checkSession();

	if (empty($_POST['prefix']) || !is_array($_POST['prefix']))
	{
		redirectexit('action=admin;area=topicprefixes');
	}

	$prefixes = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_prefix
		FROM {db_prefix}topic_prefixes
		ORDER BY sort_order'
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$prefixes[$row['id_prefix']] = $row['id_prefix'];
	}
	$smcFunc['db']->free_result($request);

	$new_prefixes = [];

	// Step through whatever we were given.
	foreach ($_POST['prefix'] as $prefix)
	{
		if (!isset($prefixes[$prefix]))
		{
			continue;
		}

		$new_prefixes[] = $prefix;
		unset ($prefixes[$prefix]);
	}

	// In case we were given bad data, also backfill with everything else.
	foreach ($prefixes as $prefix)
	{
		$new_prefixes[] = $prefix;
	}

	foreach ($new_prefixes as $position => $prefix)
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}topic_prefixes
			SET sort_order = {int:new_pos}
			WHERE id_prefix = {int:prefix}',
			[
				'new_pos' => $position + 1, // Because arrays are zero based.
				'prefix' => $prefix,
			]
		);
	}
	redirectexit('action=admin;area=topicprefixes');
}

function get_standard_prefixes(): array
{
	return [
		'prefix prefix-plain',
		'prefix prefix-primary',
		'prefix prefix-secondary',
		'prefix prefix-tertiary',
		'prefix prefix-gold',
		'prefix prefix-darkorange',
		'prefix prefix-orangered',
		'prefix prefix-firebrick',
		'prefix prefix-darkred',
		'prefix prefix-deeppink',
		'prefix prefix-mediumvioletred',
		'prefix prefix-mediumorchid',
		'prefix prefix-mediumslateblue',
		'prefix prefix-indigo',
		'prefix prefix-navy',
		'prefix prefix-royalblue',
		'prefix prefix-lightskyblue',
		'prefix prefix-aquamarine',
		'prefix prefix-mediumspringgreen',
		'prefix prefix-seagreen',
		'prefix prefix-limegreen',
		'prefix prefix-goldenrod',
		'prefix prefix-darkgoldenrod',
		'prefix prefix-saddlebrown',
	];
}

function load_prefix_access()
{
	global $smcFunc, $context, $txt;

	loadLanguage('ManagePermissions');

	// First we need to load all the groups that we could be doing this with.
	$context['prefix']['groups']['account'] = [
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
		$context['prefix']['groups']['account'][$row['id_group']] = [
			'name' => $row['group_name'],
			'access' => 'x',
		];
	}
	$smcFunc['db']->free_result($request);

	// Now load the access if applicable.
	if (!empty($context['prefix']['id_prefix']))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_group, allow_deny
			FROM {db_prefix}topic_prefix_groups
			WHERE id_prefix = {int:prefix}',
			[
				'prefix' => $context['prefix']['id_prefix'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if ($row['allow_deny'])
			{
				// If it's denied, it's denied, and nothing can override that.
				$context['prefix']['groups']['account'][$row['id_group']]['access'] = 'd';
			}
			elseif ($context['prefix']['groups']['account'][$row['id_group']]['access'] != 'd')
			{
				// If we have an entry this means we're allowing it, as long as it's not already denied.
				$context['prefix']['groups']['account'][$row['id_group']]['access'] = 'a';
			}
		}
		$smcFunc['db']->free_result($request);
	}

	$context['prefix']['groups']['account'][1]['access'] = 'a';
	$context['prefix']['groups']['account'][1]['frozen'] = true;
}

function load_prefix_boards()
{
	global $smcFunc, $context, $txt;

	$context['prefix']['board_categories'] = [];

	$request = $smcFunc['db']->query('', '
		SELECT b.id_board, b.name AS board_name, b.id_cat, c.name AS cat_name, b.child_level, COALESCE(tpb.id_prefixboard, 0) AS active
		FROM {db_prefix}boards AS b
		INNER JOIN {db_prefix}categories AS c ON (b.id_cat = c.id_cat)
		LEFT JOIN {db_prefix}topic_prefix_boards AS tpb ON (tpb.id_prefix = {int:prefix} AND tpb.id_board = b.id_board)
		ORDER BY board_order',
		[
			'prefix' => $context['prefix']['id_prefix'] ?? 0,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($context['prefix']['board_categories'][$row['id_cat']]))
		{
			$context['prefix']['board_categories'][$row['id_cat']] = [
				'id_cat' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => [],
			];
		}

		$context['prefix']['board_categories'][$row['id_cat']]['boards'][$row['id_board']] = [
			'id_board' => $row['id_board'],
			'name' => $row['board_name'],
			'child_level' => $row['child_level'],
			'active' => !empty($row['active']),
		];
	}
	$smcFunc['db']->free_result($request);
}
