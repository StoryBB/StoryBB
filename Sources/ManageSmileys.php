<?php

/**
 * This file takes care of all administration of smileys.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;

/**
 * This is the dispatcher of smileys administration.
 */
function ManageSmileys()
{
	global $context, $txt, $modSettings;

	isAllowedTo('manage_smileys');

	loadLanguage('ManageSmileys');

	$subActions = [
		'addsmiley' => 'AddSmiley',
		'editsmileys' => 'EditSmileys',
		'modifysmiley' => 'EditSmileys',
		'setorder' => 'EditSmileyOrder',
		'settings' => 'EditSmileySettings',
	];

	// Default the sub-action to 'edit smiley settings'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'editsmileys';

	$context['page_title'] = $txt['smileys_manage'];
	$context['sub_action'] = $_REQUEST['sa'];
	$context['sub_template'] = $context['sub_action'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['smileys_manage'],
		'help' => 'smileys',
		'description' => $txt['smiley_settings_explain'],
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
			'settings' => [
				'description' => $txt['smiley_settings_explain'],
			],
		],
	];

	routing_integration_hook('integrate_manage_smileys', [&$subActions]);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Handles modifying smileys settings.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function EditSmileySettings($return_config = false)
{
	global $modSettings, $context, $txt, $boarddir, $sourcedir, $scripturl;

	// The directories...
	$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? $boarddir . '/Smileys' : $modSettings['smileys_dir'];
	$context['smileys_dir_found'] = is_dir($context['smileys_dir']);

	// All the settings for the page...
	$config_vars = [
			['text', 'smileys_url', 40],
			['warning', !is_dir($context['smileys_dir']) ? 'setting_smileys_dir_wrong' : ''],
			['text', 'smileys_dir', 'invalid' => !$context['smileys_dir_found'], 40],
	];

	settings_integration_hook('integrate_modify_smiley_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['smileys_manage'] . ' - ' . $txt['settings'], $config_vars];

	// Setup the basics of the settings template.
	require_once($sourcedir . '/ManageServer.php');

	// Finish up the form...
	$context['post_url'] = $scripturl . '?action=admin;area=smileys;save;sa=settings';

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();

		settings_integration_hook('integrate_save_smiley_settings');

		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);

		cache_put_data('parsing_smileys', null, 480);
		cache_put_data('posting_smileys', null, 480);

		redirectexit('action=admin;area=smileys;sa=settings');
	}

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
	$context['settings_title'] = $txt['settings'];
}

/**
 * Add a smiley, that's right.
 */
function AddSmiley()
{
	global $modSettings, $context, $txt, $boarddir, $smcFunc;

	$context['sub_template'] = 'admin_smiley_add';

	// Get a list of all known smileys.
	$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? $boarddir . '/Smileys' : $modSettings['smileys_dir'];
	$context['smileys_dir_found'] = is_dir($context['smileys_dir']);

	// Submitting a form?
	if (isset($_POST[$context['session_var']], $_POST['smiley_code']))
	{
		checkSession();

		// Some useful arrays... types we allow - and ports we don't!
		$allowedTypes = ['jpeg', 'jpg', 'gif', 'png', 'bmp'];
		$disabledFiles = ['con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php'];

		$_POST['smiley_code'] = htmltrim__recursive($_POST['smiley_code']);
		$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];
		$_POST['smiley_filename'] = htmltrim__recursive($_POST['smiley_filename']);

		// Make sure some code was entered.
		if (empty($_POST['smiley_code']))
			fatal_lang_error('smiley_has_no_code');

		// Check whether the new code has duplicates. It should be unique.
		$request = $smcFunc['db']->query('', '
			SELECT id_smiley
			FROM {db_prefix}smileys
			WHERE code = {raw:mysql_binary_statement} {string:smiley_code}',
			[
				'mysql_binary_statement' => $smcFunc['db']->get_title() == 'MySQL' ? 'BINARY' : '',
				'smiley_code' => $_POST['smiley_code'],
			]
		);
		if ($smcFunc['db']->num_rows($request) > 0)
			fatal_lang_error('smiley_not_unique');
		$smcFunc['db']->free_result($request);

		// If we are uploading - check the smiley folder is writable!
		if ($_POST['method'] != 'existing')
		{
			$writeErrors = [];
			if (!is_writable($context['smileys_dir']))
				$writeErrors[] = $context['smileys_dir'];

			if (!empty($writeErrors))
				fatal_lang_error('smileys_upload_error_notwritable', true, [implode(', ', $writeErrors)]);
		}

		// Uploading just one smiley for all of them?
		if (isset($_FILES['uploadSmiley']['name']) && $_FILES['uploadSmiley']['name'] != '')
		{
			if (!is_uploaded_file($_FILES['uploadSmiley']['tmp_name']) || (ini_get('open_basedir') == '' && !file_exists($_FILES['uploadSmiley']['tmp_name'])))
				fatal_lang_error('smileys_upload_error');

			// Sorry, no spaces, dots, or anything else but letters allowed.
			$_FILES['uploadSmiley']['name'] = preg_replace(['/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'], ['_', '.', ''], $_FILES['uploadSmiley']['name']);

			// We only allow image files - it's THAT simple - no messing around here...
			if (!in_array(strtolower(substr(strrchr($_FILES['uploadSmiley']['name'], '.'), 1)), $allowedTypes))
				fatal_lang_error('smileys_upload_error_types', false, [implode(', ', $allowedTypes)]);

			// We only need the filename...
			$destName = basename($_FILES['uploadSmiley']['name']);

			// Make sure they aren't trying to upload a nasty file - for their own good here!
			if (in_array(strtolower($destName), $disabledFiles))
				fatal_lang_error('smileys_upload_error_illegal');

			// Okay, we're going to put the smiley right here, since it's not there yet!
			$smileyLocation = $context['smileys_dir'] . '/' . $destName;
			move_uploaded_file($_FILES['uploadSmiley']['tmp_name'], $smileyLocation);
			sbb_chmod($smileyLocation, 0644);

			// Finally make sure it's saved correctly!
			$_POST['smiley_filename'] = $destName;
		}

		// Also make sure a filename was given.
		if (empty($_POST['smiley_filename']))
			fatal_lang_error('smiley_has_no_filename');

		// Find the position on the right.
		$smiley_order = '0';
		if ($_POST['smiley_location'] != 1)
		{
			$request = $smcFunc['db']->query('', '
				SELECT MAX(smiley_order) + 1
				FROM {db_prefix}smileys
				WHERE hidden = {int:smiley_location}
					AND smiley_row = {int:first_row}',
				[
					'smiley_location' => $_POST['smiley_location'],
					'first_row' => 0,
				]
			);
			list ($smiley_order) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			if (empty($smiley_order))
				$smiley_order = '0';
		}

		$smcFunc['db']->insert('',
			'{db_prefix}smileys',
			[
				'code' => 'string-30', 'filename' => 'string-48', 'description' => 'string-80', 'hidden' => 'int', 'smiley_order' => 'int',
			],
			[
				$_POST['smiley_code'], $_POST['smiley_filename'], $_POST['smiley_description'], $_POST['smiley_location'], $smiley_order,
			],
			['id_smiley']
		);

		cache_put_data('parsing_smileys', null, 480);
		cache_put_data('posting_smileys', null, 480);

		// No errors? Out of here!
		redirectexit('action=admin;area=smileys;sa=editsmileys');
	}

	// Get all possible filenames for the smileys.
	$context['filenames'] = [];
	if ($context['smileys_dir_found'])
	{
		$dir = dir($context['smileys_dir']);
		while ($entry = $dir->read())
		{
			if (!in_array($entry, $context['filenames']) && in_array(strrchr($entry, '.'), ['.jpg', '.gif', '.jpeg', '.png']))
				$context['filenames'][strtolower($entry)] = [
					'id' => StringLibrary::escape($entry),
					'selected' => false,
				];
		}
		$dir->close();

		ksort($context['filenames']);
	}

	// Create a new smiley from scratch.
	$context['filenames'] = array_values($context['filenames']);
	$context['current_smiley'] = [
		'id' => 0,
		'code' => '',
		'filename' => $context['filenames'][0]['id'],
		'description' => $txt['smileys_default_description'],
		'location' => 0,
		'is_new' => true,
	];
}

/**
 * Add, remove, edit smileys.
 */
function EditSmileys()
{
	global $modSettings, $context, $txt, $boarddir;
	global $smcFunc, $scripturl, $sourcedir;

	// Force the correct tab to be displayed.
	$context[$context['admin_menu_name']]['current_subsection'] = 'editsmileys';

	// Submitting a form?
	if (isset($_POST['smiley_save']) || isset($_POST['smiley_action']) || isset($_POST['deletesmiley']))
	{
		checkSession();

		// Changing the selected smileys?
		if (isset($_POST['smiley_action']) && !empty($_POST['checked_smileys']))
		{
			foreach ($_POST['checked_smileys'] as $id => $smiley_id)
				$_POST['checked_smileys'][$id] = (int) $smiley_id;

			if ($_POST['smiley_action'] == 'delete')
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}smileys
					WHERE id_smiley IN ({array_int:checked_smileys})',
					[
						'checked_smileys' => $_POST['checked_smileys'],
					]
				);
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
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}smileys
					WHERE id_smiley = {int:current_smiley}',
					[
						'current_smiley' => $_POST['smiley'],
					]
				);
			}
			// Otherwise an edit.
			else
			{
				$_POST['smiley'] = (int) $_POST['smiley'];
				$_POST['smiley_code'] = htmltrim__recursive($_POST['smiley_code']);
				$_POST['smiley_filename'] = htmltrim__recursive($_POST['smiley_filename']);
				$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];

				// Make sure some code was entered.
				if (empty($_POST['smiley_code']))
					fatal_lang_error('smiley_has_no_code');

				// Also make sure a filename was given.
				if (empty($_POST['smiley_filename']))
					fatal_lang_error('smiley_has_no_filename');

				// Check whether the new code has duplicates. It should be unique.
				$request = $smcFunc['db']->query('', '
					SELECT id_smiley
					FROM {db_prefix}smileys
					WHERE code = {raw:mysql_binary_type} {string:smiley_code}' . (empty($_POST['smiley']) ? '' : '
						AND id_smiley != {int:current_smiley}'),
					[
						'current_smiley' => $_POST['smiley'],
						'mysql_binary_type' => $smcFunc['db']->get_title() == 'MySQL' ? 'BINARY' : '',
						'smiley_code' => $_POST['smiley_code'],
					]
				);
				if ($smcFunc['db']->num_rows($request) > 0)
					fatal_lang_error('smiley_not_unique');
				$smcFunc['db']->free_result($request);

				$smcFunc['db']->query('', '
					UPDATE {db_prefix}smileys
					SET
						code = {string:smiley_code},
						filename = {string:smiley_filename},
						description = {string:smiley_description},
						hidden = {int:smiley_location}
					WHERE id_smiley = {int:current_smiley}',
					[
						'smiley_location' => $_POST['smiley_location'],
						'current_smiley' => $_POST['smiley'],
						'smiley_code' => $_POST['smiley_code'],
						'smiley_filename' => $_POST['smiley_filename'],
						'smiley_description' => $_POST['smiley_description'],
					]
				);
			}
		}

		cache_put_data('parsing_smileys', null, 480);
		cache_put_data('posting_smileys', null, 480);
	}

	// Prepare overview of all (custom) smileys.
	if ($context['sub_action'] == 'editsmileys')
	{
		// Determine the language specific sort order of smiley locations.
		$smiley_locations = [
			$txt['smileys_location_form'],
			$txt['smileys_location_hidden'],
			$txt['smileys_location_popup'],
		];
		asort($smiley_locations);

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
						'sprintf' => [
							'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=%1$d"><img src="' . $modSettings['smileys_url'] . '/%2$s" alt="%3$s" style="padding: 2px;" id="smiley%1$d"><input type="hidden" name="smileys[%1$d][filename]" value="%2$s"></a>',
							'params' => [
								'id_smiley' => false,
								'filename' => true,
								'description' => true,
							],
						],
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
						'default' => $smcFunc['db']->custom_order('hidden', array_keys($smiley_locations)) ,
						'reverse' => $smcFunc['db']->custom_order('hidden', array_keys($smiley_locations), true),
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
				}
				function changeSet(newSet)
				{
					var currentImage, i, knownSmileys = [];

					if (knownSmileys.length == 0)
					{
						for (var i = 0, n = document.images.length; i < n; i++)
							if (document.images[i].id.substr(0, 6) == \'smiley\')
								knownSmileys[knownSmileys.length] = document.images[i].id.substr(6);
					}

					for (i = 0; i < knownSmileys.length; i++)
					{
						currentImage = document.getElementById("smiley" + knownSmileys[i]);
						currentImage.src = "' . $modSettings['smileys_url'] . '/" + newSet + "/" + document.forms.smileyForm["smileys[" + knownSmileys[i] + "][filename]"].value;
					}
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
		// Get a list of all known smileys.
		$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? $boarddir . '/Smileys' : $modSettings['smileys_dir'];
		$context['smileys_dir_found'] = is_dir($context['smileys_dir']);

		// Get all possible filenames for the smileys.
		$context['filenames'] = [];
		if ($context['smileys_dir_found'])
		{
			$dir = dir($context['smileys_dir']);
			while ($entry = $dir->read())
			{
				if (!in_array($entry, $context['filenames']) && in_array(strrchr($entry, '.'), ['.jpg', '.gif', '.jpeg', '.png']))
					$context['filenames'][strtolower($entry)] = [
						'id' => StringLibrary::escape($entry),
						'selected' => false,
					];
			}
			$dir->close();
			ksort($context['filenames']);
		}

		$request = $smcFunc['db']->query('', '
			SELECT id_smiley AS id, code, filename, description, hidden AS location, 0 AS is_new
			FROM {db_prefix}smileys
			WHERE id_smiley = {int:current_smiley}',
			[
				'current_smiley' => (int) $_REQUEST['smiley'],
			]
		);
		if ($smcFunc['db']->num_rows($request) != 1)
			fatal_lang_error('smiley_not_found');
		$context['current_smiley'] = $smcFunc['db']->fetch_assoc($request);
		$smcFunc['db']->free_result($request);

		$context['current_smiley']['code'] = StringLibrary::escape($context['current_smiley']['code']);
		$context['current_smiley']['filename'] = StringLibrary::escape($context['current_smiley']['filename']);
		$context['current_smiley']['description'] = StringLibrary::escape($context['current_smiley']['description']);

		if (isset($context['filenames'][strtolower($context['current_smiley']['filename'])]))
			$context['filenames'][strtolower($context['current_smiley']['filename'])]['selected'] = true;
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
	global $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT id_smiley, code, filename, description, smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		ORDER BY {raw:sort}',
		[
			'sort' => $sort,
		]
	);
	$smileys = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$smileys[] = $row;
	$smcFunc['db']->free_result($request);

	return $smileys;
}

/**
 * Callback function for createList().
 * @return int The number of smileys
 */
function list_getNumSmileys()
{
	global $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}smileys',
		[]
	);
	list($numSmileys) = $smcFunc['db']->fetch_row;
	$smcFunc['db']->free_result($request);

	return $numSmileys;
}

/**
 * Allows to edit smileys order.
 */
function EditSmileyOrder()
{
	global $context, $txt, $smcFunc;

	$context['sub_template'] = 'admin_smiley_reorder';

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
	}

	$request = $smcFunc['db']->query('', '
		SELECT id_smiley, code, filename, description, smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		WHERE hidden != {int:popup}
		ORDER BY smiley_order, smiley_row',
		[
			'popup' => 1,
		]
	);
	$context['smileys'] = [
		'postform' => [
			'rows' => [],
		],
		'popup' => [
			'rows' => [],
		],
	];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$location = empty($row['hidden']) ? 'postform' : 'popup';
		$context['smileys'][$location]['rows'][$row['smiley_row']][] = [
			'id' => $row['id_smiley'],
			'code' => StringLibrary::escape($row['code']),
			'filename' => StringLibrary::escape($row['filename']),
			'description' => StringLibrary::escape($row['description']),
			'row' => $row['smiley_row'],
			'order' => $row['smiley_order'],
			'selected' => !empty($_REQUEST['move']) && $_REQUEST['move'] == $row['id_smiley'],
		];
	}
	$smcFunc['db']->free_result($request);

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
