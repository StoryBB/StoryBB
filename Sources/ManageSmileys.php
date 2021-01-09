<?php

/**
 * This file takes care of all administration of smileys.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Container;
use StoryBB\StringLibrary;

/**
 * This is the dispatcher of smileys administration.
 */
function ManageSmileys()
{
	global $context, $txt;

	isAllowedTo('manage_smileys');

	loadLanguage('ManageSmileys');

	$subActions = [
		'addsmiley' => 'AddSmiley',
		'editsmileys' => 'EditSmileys',
		'modifysmiley' => 'EditSmileys',
		'setorder' => 'EditSmileyOrder',
	];

	// Default the sub-action to 'edit smiley settings'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'editsmileys';

	$context['page_title'] = $txt['smileys_manage'];
	$context['sub_action'] = $_REQUEST['sa'];
	$context['sub_template'] = $context['sub_action'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['smileys_manage'],
		'help' => '',
		'tabs' => [
			'addsmiley' => [
				'description' => $txt['smiley_addsmiley_explain'],
			],
			'editsmileys' => [
				'description' => $txt['smiley_editsmileys_explain'],
			],
			'setorder' => [
				'description' => $txt['smiley_setorder_explain'],
			],
		],
	];

	routing_integration_hook('integrate_manage_smileys', [&$subActions]);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Add a smiley, that's right.
 */
function AddSmiley()
{
	global $context;

	$container = Container::instance();
	$smiley_helper = $container->get('smileys');

	$context['sub_template'] = 'admin_smiley_add';

	// Submitting a form?
	if (isset($_POST[$context['session_var']], $_POST['smiley_code']))
	{
		checkSession();

		// Some useful arrays... types we allow - and ports we don't!
		$allowedTypes = ['jpeg', 'jpg', 'gif', 'png', 'bmp'];
		$disabledFiles = ['con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php'];

		$_POST['smiley_code'] = htmltrim__recursive($_POST['smiley_code']);
		$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];

		// Make sure some code was entered.
		if (empty($_POST['smiley_code']))
			fatal_lang_error('smiley_has_no_code');

		// Check whether the new code has duplicates. It should be unique.
		if (!$smiley_helper->is_unique_code($_POST['smiley_code']))
		{
			fatal_lang_error('smiley_not_unique');
		}

		// If we are uploading - check the smiley folder is writable!
		$writeErrors = [];
		// @todo Check the upload folder...

		if (!empty($writeErrors))
			fatal_lang_error('smileys_upload_error_notwritable', true, [implode(', ', $writeErrors)]);

		// Uploading just one smiley for all of them?
		if (isset($_FILES['uploadSmiley']['name']) && $_FILES['uploadSmiley']['name'] != '')
		{
			if (!is_uploaded_file($_FILES['uploadSmiley']['tmp_name']))
			{
				fatal_lang_error('smileys_upload_error');
			}

			// Sorry, no spaces, dots, or anything else but letters allowed.
			$destName = preg_replace(['/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'], ['_', '.', ''], $_FILES['uploadSmiley']['name']);
			$destName = basename($destName);

			// We only allow image files - it's THAT simple - no messing around here...
			if (!in_array(strtolower(substr(strrchr($destName, '.'), 1)), $allowedTypes))
			{
				fatal_lang_error('smileys_upload_error_types', false, [implode(', ', $allowedTypes)]);
			}

			// Make sure they aren't trying to upload a nasty file - for their own good here!
			if (in_array(strtolower($destName), $disabledFiles))
			{
				fatal_lang_error('smileys_upload_error_illegal');
			}
		}

		// Also make sure a filename was given.
		if (empty($destName))
			fatal_lang_error('smiley_has_no_filename');

		$smiley_helper->upload_smiley([$_POST['smiley_code']], $destName, $_POST['smiley_description'], $_POST['smiley_location'], $_FILES['uploadSmiley']['tmp_name']);

		cache_put_data('parsing_smileys', null, 480);
		cache_put_data('posting_smileys', null, 480);

		// No errors? Out of here!
		redirectexit('action=admin;area=smileys;sa=editsmileys');
	}
}

/**
 * Add, remove, edit smileys.
 */
function EditSmileys()
{
	global $context, $txt;
	global $smcFunc, $scripturl, $sourcedir;

	$container = Container::instance();
	$smiley_helper = $container->get('smileys');

	// Force the correct tab to be displayed.
	$context[$context['admin_menu_name']]['current_subsection'] = 'editsmileys';

	// Submitting a form?
	if (isset($_POST['smiley_save']) || isset($_POST['smiley_action']) || isset($_POST['deletesmiley']))
	{
		checkSession();

		$smileys_helper = $container->get('smileys');

		// Changing the selected smileys?
		if (isset($_POST['smiley_action']) && !empty($_POST['checked_smileys']))
		{
			foreach ($_POST['checked_smileys'] as $id => $smiley_id)
			{
				$_POST['checked_smileys'][$id] = (int) $smiley_id;
			}

			if ($_POST['smiley_action'] == 'delete')
			{
				foreach ($_POST['checked_smileys'] as $smiley_id)
				{
					if ($smiley_id > 0)
					{
						$smileys_helper->delete_smiley($smiley_id);
					}
				}
			}
			// Changing the status of the smiley?
			else
			{
				// Check it's a valid type.
				$displayTypes = [
					'post' => 0,
					'hidden' => 1,
					'popup' => 2
				];
				if (isset($displayTypes[$_POST['smiley_action']]))
					$smcFunc['db']->query('', '
						UPDATE {db_prefix}smileys
						SET hidden = {int:display_type}
						WHERE id_smiley IN ({array_int:checked_smileys})',
						[
							'checked_smileys' => $_POST['checked_smileys'],
							'display_type' => $displayTypes[$_POST['smiley_action']],
						]
					);
			}
		}
		// Create/modify a smiley.
		elseif (isset($_POST['smiley']))
		{
			// Is it a delete?
			if (!empty($_POST['deletesmiley']))
			{
				$smileys_helper->delete_smiley((int) $_POST['smiley']);
			}
			// Otherwise an edit.
			else
			{
				$_POST['smiley'] = (int) $_POST['smiley'];
				$_POST['smiley_code'] = htmltrim__recursive($_POST['smiley_code']);
				$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];

				$_POST['smiley_code'] = explode("\n", $_POST['smiley_code']);
				$_POST['smiley_code'] = array_map('trim', $_POST['smiley_code']);
				$_POST['smiley_code'] = array_filter($_POST['smiley_code'], function($code) {
					return !empty($code);
				});
				$_POST['smiley_code'] = implode("\n", $_POST['smiley_code']);
				var_dump($_POST['smiley_code']);

				// Make sure some code was entered.
				if (empty($_POST['smiley_code']))
					fatal_lang_error('smiley_has_no_code');

				// Check whether the new code has duplicates. It should be unique.
				if (!$smiley_helper->is_unique_code($_POST['smiley_code']))
				{
					fatal_lang_error('smiley_not_unique');
				}

				$smcFunc['db']->query('', '
					UPDATE {db_prefix}smileys
					SET
						code = {string:smiley_code},
						description = {string:smiley_description},
						hidden = {int:smiley_location}
					WHERE id_smiley = {int:current_smiley}',
					[
						'smiley_location' => $_POST['smiley_location'],
						'current_smiley' => $_POST['smiley'],
						'smiley_code' => $_POST['smiley_code'],
						'smiley_description' => $_POST['smiley_description'],
					]
				);
			}
		}

		cache_put_data('parsing_smileys', null, 480);
		cache_put_data('posting_smileys', null, 480);

		session_flash('success', $txt['smiley_change_saved']);

		redirectexit('action=admin;area=smileys;sa=editsmileys');
	}

	// Prepare overview of all (custom) smileys.
	if ($context['sub_action'] == 'editsmileys')
	{
		$listOptions = [
			'id' => 'smiley_list',
			'title' => $txt['smileys_edit'],
			'items_per_page' => 40,
			'base_href' => $scripturl . '?action=admin;area=smileys;sa=editsmileys',
			'default_sort_col' => 'filename',
			'get_items' => [
				'function' => 'list_getSmileys',
			],
			'get_count' => [
				'function' => 'list_getNumSmileys',
			],
			'no_items_label' => $txt['smileys_no_entries'],
			'columns' => [
				'picture' => [
					'data' => [
						'function' => function ($rowData) use ($txt, $scripturl)
						{
							$btn = '<img src="' . $rowData['url'] . '" alt="' . $rowData['description'] . '" style="padding: 2px;" id="smiley' . $rowData['id_smiley'] . '">';
							$btn .= '<input type="hidden" name="smileys' . $rowData['id_smiley'] . '][filename]" value="%2$s">';

							$btn = '<a href="' . $scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=' . $rowData['id_smiley'] . '">' . $btn . '</a>';
							return $btn;
						},
						'class' => 'centercol',
					],
				],
				'code' => [
					'header' => [
						'value' => $txt['smileys_code'],
					],
					'data' => [
						'db_htmlsafe' => 'code',
					],
					'sort' => [
						'default' => 'code',
						'reverse' => 'code DESC',
					],
				],
				'filename' => [
					'header' => [
						'value' => $txt['smileys_filename'],
					],
					'data' => [
						'db_htmlsafe' => 'filename',
					],
					'sort' => [
						'default' => 'filename',
						'reverse' => 'filename DESC',
					],
				],
				'location' => [
					'header' => [
						'value' => $txt['smileys_location'],
					],
					'data' => [
						'function' => function ($rowData) use ($txt)
						{
							if (empty($rowData['hidden']))
								return $txt['smileys_location_form'];
							elseif ($rowData['hidden'] == 1)
								return $txt['smileys_location_hidden'];
							else
								return $txt['smileys_location_popup'];
						},
					],
					'sort' => [
						'default' => 'hidden',
						'reverse' => 'hidden DESC',
					],
				],
				'tooltip' => [
					'header' => [
						'value' => $txt['smileys_description'],
					],
					'data' => [
						'function' => function ($rowData) use ($smcFunc)
						{
							return StringLibrary::escape($rowData['description']);
						},
					],
					'sort' => [
						'default' => 'description',
						'reverse' => 'description DESC',
					],
				],
				'modify' => [
					'header' => [
						'value' => $txt['smileys_modify'],
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=%1$d">' . $txt['smileys_modify'] . '</a>',
							'params' => [
								'id_smiley' => false,
							],
						],
						'class' => 'centercol',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="checked_smileys[]" value="%1$d">',
							'params' => [
								'id_smiley' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => $scripturl . '?action=admin;area=smileys;sa=editsmileys',
				'name' => 'smileyForm',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '
						<select name="smiley_action" onchange="makeChanges(this.value);">
							<option value="-1">' . $txt['smileys_with_selected'] . ':</option>
							<option value="-1" disabled>--------------</option>
							<option value="hidden">' . $txt['smileys_make_hidden'] . '</option>
							<option value="post">' . $txt['smileys_show_on_post'] . '</option>
							<option value="popup">' . $txt['smileys_show_on_popup'] . '</option>
							<option value="delete">' . $txt['smileys_remove'] . '</option>
						</select>
						<noscript>
							<input type="submit" name="perform_action" value="' . $txt['go'] . '">
						</noscript>',
					'class' => 'righttext',
				],
			],
			'javascript' => '
				function makeChanges(action)
				{
					if (action == \'-1\')
						return false;
					else if (action == \'delete\')
					{
						if (confirm(\'' . $txt['smileys_confirm'] . '\'))
							document.forms.smileyForm.submit();
					}
					else
						document.forms.smileyForm.submit();
					return true;
				}',
		];

		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		// The list is the only thing to show, so make it the main template.
		$context['default_list'] = 'smiley_list';
		$context['sub_template'] = 'generic_list_page';
	}
	// Modifying smileys.
	elseif ($context['sub_action'] == 'modifysmiley')
	{
		$context['sub_template'] = 'admin_smiley_edit';

		$smileys = $smiley_helper->get_smileys();
		if (!isset($smileys[$_REQUEST['smiley']]))
		{
			fatal_lang_error('smiley_not_found');
		}

		$context['current_smiley'] = $smileys[$_REQUEST['smiley']];

		$context['current_smiley']['code'] = StringLibrary::escape($context['current_smiley']['code']);
		$context['current_smiley']['filename'] = StringLibrary::escape($context['current_smiley']['filename']);
		$context['current_smiley']['description'] = StringLibrary::escape($context['current_smiley']['description']);
	}
}

/**
 * Callback function for createList().
 *
 * @param int $start The item to start with (not used here)
 * @param int $items_per_page The number of items to show per page (not used here)
 * @param string $sort A string indicating how to sort the results
 * @return array An array of info about the smileys
 */
function list_getSmileys($start, $items_per_page, $sort)
{
	$container = Container::instance();
	$smileys_helper = $container->get('smileys');

	$smileys = $smileys_helper->get_smileys();

	$ascending = true;
	if (substr($sort, -4) == 'DESC')
	{
		$sort = substr($sort, 0, -5);
		$ascending = false;
	}
	uasort($smileys, function ($a, $b) use ($sort) {
		return $a[$sort] <=> $b[$sort];
	});
	if (!$ascending)
	{
		$smileys = array_reverse($smileys, true);
	}

	$smileys = array_slice($smileys, $start, $items_per_page, true);
	return $smileys;
}

/**
 * Callback function for createList().
 * @return int The number of smileys
 */
function list_getNumSmileys()
{
	$container = Container::instance();
	$smileys_helper = $container->get('smileys');

	$smileys = $smileys_helper->get_smileys();
	return count($smileys);
}

/**
 * Allows to edit smileys order.
 */
function EditSmileyOrder()
{
	global $context, $txt, $smcFunc;

	$context['sub_template'] = 'admin_smiley_reorder';

	$container = Container::instance();
	$smiley_helper = $container->get('smileys');

	// Move smileys to another position.
	if (isset($_REQUEST['reorder']))
	{
		checkSession('get');

		$_GET['location'] = empty($_GET['location']) || $_GET['location'] != 'popup' ? 0 : 2;
		$_GET['source'] = empty($_GET['source']) ? 0 : (int) $_GET['source'];

		if (empty($_GET['source']))
			fatal_lang_error('smiley_not_found');

		if (!empty($_GET['after']))
		{
			$_GET['after'] = (int) $_GET['after'];

			$request = $smcFunc['db']->query('', '
				SELECT smiley_row, smiley_order, hidden
				FROM {db_prefix}smileys
				WHERE hidden = {int:location}
					AND id_smiley = {int:after_smiley}',
				[
					'location' => $_GET['location'],
					'after_smiley' => $_GET['after'],
				]
			);
			if ($smcFunc['db']->num_rows($request) != 1)
				fatal_lang_error('smiley_not_found');
			list ($smiley_row, $smiley_order, $smileyLocation) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);
		}
		else
		{
			$smiley_row = (int) $_GET['row'];
			$smiley_order = -1;
			$smileyLocation = (int) $_GET['location'];
		}

		$smcFunc['db']->query('', '
			UPDATE {db_prefix}smileys
			SET smiley_order = smiley_order + 1
			WHERE hidden = {int:new_location}
				AND smiley_row = {int:smiley_row}
				AND smiley_order > {int:smiley_order}',
			[
				'new_location' => $_GET['location'],
				'smiley_row' => $smiley_row,
				'smiley_order' => $smiley_order,
			]
		);

		$smcFunc['db']->query('', '
			UPDATE {db_prefix}smileys
			SET
				smiley_order = {int:smiley_order} + 1,
				smiley_row = {int:smiley_row},
				hidden = {int:new_location}
			WHERE id_smiley = {int:current_smiley}',
			[
				'smiley_order' => $smiley_order,
				'smiley_row' => $smiley_row,
				'new_location' => $smileyLocation,
				'current_smiley' => $_GET['source'],
			]
		);

		cache_put_data('parsing_smileys', null, 480);
		cache_put_data('posting_smileys', null, 480);

		session_flash('success', 'smiley_change_saved');
		redirectexit('action=admin;area=smileys;sa=setorder');
	}

	$context['smileys'] = [
		'postform' => [
			'rows' => [],
		],
		'popup' => [
			'rows' => [],
		],
	];

	$smiley_helper = $container->get('smileys');
	$smileys = $smiley_helper->get_smileys();
	uasort($smileys, function($a, $b) {
		return $a['smiley_order'] <=> $b['smiley_order'];
	});

	foreach ($smileys as $smiley)
	{
		if ($smiley['hidden'] == $smiley_helper::POSITION_HIDDEN)
		{
			continue;
		}

		$location = $smiley['hidden'] == $smiley_helper::POSITION_POPUP ? 'popup' : 'postform';
		$context['smileys'][$location]['rows'][$smiley['smiley_row']][] = [
			'id' => $smiley['id_smiley'],
			'code' => StringLibrary::escape($smiley['code']),
			'filename' => StringLibrary::escape($smiley['filename']),
			'description' => StringLibrary::escape($smiley['description']),
			'row' => $smiley['smiley_row'],
			'order' => $smiley['smiley_order'],
			'selected' => !empty($_REQUEST['move']) && $_REQUEST['move'] == $smiley['id_smiley'],
			'url' => $smiley['url'],
		];
	}

	$context['move_smiley'] = empty($_REQUEST['move']) ? 0 : (int) $_REQUEST['move'];

	// Make sure all rows are sequential.
	foreach (array_keys($context['smileys']) as $location)
		$context['smileys'][$location] = [
			'id' => $location,
			'title' => $location == 'postform' ? $txt['smileys_location_form'] : $txt['smileys_location_popup'],
			'description' => $location == 'postform' ? $txt['smileys_location_form_description'] : $txt['smileys_location_popup_description'],
			'last_row' => count($context['smileys'][$location]['rows']),
			'rows' => array_values($context['smileys'][$location]['rows']),
		];

	// Check & fix smileys that are not ordered properly in the database.
	foreach (array_keys($context['smileys']) as $location)
	{
		foreach ($context['smileys'][$location]['rows'] as $id => $smiley_row)
		{
			// Fix empty rows if any.
			if ($id != $smiley_row[0]['row'])
			{
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}smileys
					SET smiley_row = {int:new_row}
					WHERE smiley_row = {int:current_row}
						AND hidden = {int:location}',
					[
						'new_row' => $id,
						'current_row' => $smiley_row[0]['row'],
						'location' => $location == 'postform' ? '0' : '2',
					]
				);
				// Only change the first row value of the first smiley (we don't need the others :P).
				$context['smileys'][$location]['rows'][$id][0]['row'] = $id;
			}
			// Make sure the smiley order is always sequential.
			foreach ($smiley_row as $order_id => $smiley)
				if ($order_id != $smiley['order'])
					$smcFunc['db']->query('', '
						UPDATE {db_prefix}smileys
						SET smiley_order = {int:new_order}
						WHERE id_smiley = {int:current_smiley}',
						[
							'new_order' => $order_id,
							'current_smiley' => $smiley['id'],
						]
					);
		}
	}

	cache_put_data('parsing_smileys', null, 480);
	cache_put_data('posting_smileys', null, 480);
}
