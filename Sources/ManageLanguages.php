<?php

/**
 * This file handles the administration of languages tasks.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Model\Policy;
use StoryBB\Model\Language;
use StoryBB\Model\Theme;
use StoryBB\StringLibrary;

/**
 * This is the main function for the languages area.
 * It dispatches the requests.
 * Loads the ManageLanguages template. (sub-actions will use it)
 * @todo lazy loading.
 *
 * @uses ManageSettings language file
 */
function ManageLanguages()
{
	global $context, $txt;

	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['edit_languages'];

	$subActions = [
		'edit' => 'ModifyLanguages',
		'editlang' => 'ModifyLanguage',
	];

	// By default we're managing languages.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'edit';
	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['language_configuration'],
		'description' => $txt['language_description'],
	];

	routing_integration_hook('integrate_manage_languages', [&$subActions]);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * This lists all the current languages and allows editing of them.
 */
function ModifyLanguages()
{
	global $txt, $context, $scripturl, $modSettings;
	global $sourcedir, $language, $boarddir;

	// Setting a new default?
	if (!empty($_POST['set_default']) && !empty($_POST['default_language']))
	{
		checkSession();
		validateToken('admin-lang');

		getLanguages();
		$lang_exists = false;
		foreach ($context['languages'] as $lang)
		{
			if ($_POST['default_language'] == $lang['filename'])
			{
				$lang_exists = true;
				break;
			}
		}

		// First, fix the default language situation.
		if ($_POST['default_language'] != $language && $lang_exists)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(['language' => $_POST['default_language']]);
			$language = $_POST['default_language'];
		}

		// Now to fix a few things.
		if (!empty($_POST['userLanguage']))
		{
			$newsettings = ['userLanguage' => 1];
			$newavailable = [];
			$selectedavailable = !empty($_POST['available']) ? (array) $_POST['available'] : [];
			foreach (array_keys($selectedavailable) as $lang_id)
			{
				if (isset($context['languages'][$lang_id]))
				{
					$newavailable[] = $lang_id;
				}
			}
			if (empty($newavailable))
			{
				$newavailable[] = $language;
			}
			$newsettings['languages_available'] = implode(',', $newavailable);
		}
		else
		{
			$newsettings = [
				'userLanguage' => 0,
				'languages_available' => $language,
			];
		}
		updateSettings($newsettings);

		session_flash('success', $txt['settings_saved']);
		redirectexit($scripturl . '?action=admin;area=languages');
	}

	// Create another one time token here.
	createToken('admin-lang');

	$listOptions = [
		'id' => 'language_list',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=languages',
		'title' => $txt['edit_languages'],
		'get_items' => [
			'function' => 'list_getLanguages',
		],
		'get_count' => [
			'function' => 'list_getNumLanguages',
		],
		'columns' => [
			'available' => [
				'header' => [
					'value' => $txt['languages_available'],
				],
				'data' => [
					'function' => function($rowData)
					{
						return '<input type="checkbox" name="available[' . $rowData['id'] . ']" value="1"' . (!empty($rowData['available']) ? ' checked' : '') . '>';
					},
					'style' => 'text-align: center; width: 8%',
				],
			],
			'default' => [
				'header' => [
					'value' => $txt['languages_default'],
					'class' => 'centercol',
				],
				'data' => [
					'function' => function($rowData) use ($scripturl, $context, $txt)
					{
						return '<input type="radio" name="default_language" value="' . $rowData['id'] . '"' . ($rowData['default'] ? ' checked' : '') . '>';
					},
					'style' => 'width: 8%;',
					'class' => 'centercol',
				],
			],
			'name' => [
				'header' => [
					'value' => $txt['languages_lang_name'],
				],
				'data' => [
					'db_htmlsafe' => 'name',
				],
			],
			'edit' => [
				'header' => [
					'value' => $txt['edit'],
					'class' => 'centercol',
				],
				'data' => [
					'function' => function($rowData) use ($scripturl, $txt)
					{
						return sprintf('<a href="%1$s?action=admin;area=languages;sa=editlang;lid=%2$s">%3$s</a>', $scripturl, $rowData['id'], $txt['edit']);
					},
					'class' => 'centercol',
				],
			],
			'count' => [
				'header' => [
					'value' => $txt['languages_users'],
					'class' => 'centercol',
				],
				'data' => [
					'db_htmlsafe' => 'count',
					'class' => 'centercol',
				],
			],
			'locale' => [
				'header' => [
					'value' => $txt['languages_locale'],
					'class' => 'centercol',
				],
				'data' => [
					'db_htmlsafe' => 'locale',
					'class' => 'centercol',
				],
			],
			'rtl' => [
				'header' => [
					'value' => $txt['languages_right_to_left'],
					'class' => 'centercol',
				],
				'data' => [
					'function' => function($rowData) use ($txt)
					{
						return $rowData['rtl'] ? $txt['yes'] : $txt['no'];
					},
					'class' => 'centercol',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=admin;area=languages',
			'token' => 'admin-lang',
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value' => '<label><input type="checkbox" name="userLanguage"' . (!empty($modSettings['userLanguage']) ? ' checked' : '') . '>' . $txt['userLanguage'] . '</label>',
				'style' => 'text-align: right',
			],
			[
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="set_default" value="' . $txt['save'] . '">',
			],
		],
	];

	// Display a warning if we cannot edit the default setting.
	if (!is_writable($boarddir . '/Settings.php'))
		$listOptions['additional_rows'][] = [
				'position' => 'after_title',
				'value' => $txt['language_settings_writable'],
				'class' => 'smalltext alert',
			];

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'language_list';
}

/**
 * How many languages?
 * Callback for the list in ManageLanguageSettings().
 * @return int The number of available languages
 */
function list_getNumLanguages()
{
	return count(getLanguages(false));
}

/**
 * Fetch the actual language information.
 * Callback for $listOptions['get_items']['function'] in ManageLanguageSettings.
 * Determines which languages are available by looking for the "index.{language}.php" file.
 * Also figures out how many users are using a particular language.
 * @return array An array of information about currenty installed languages
 */
function list_getLanguages()
{
	global $settings, $smcFunc, $language, $context, $txt, $modSettings;

	$available = !empty($modSettings['languages_available']) ? explode(',', $modSettings['languages_available']) : [];
	if (empty($available))
	{
		$available[] = !empty($language) ? $language : 'en-us';
	}

	$languages = [];
	// Keep our old entries.
	$old_txt = $txt;
	$backup_actual_theme_dir = $settings['actual_theme_dir'];
	$backup_base_theme_dir = !empty($settings['base_theme_dir']) ? $settings['base_theme_dir'] : '';

	// Override these for now.
	$settings['actual_theme_dir'] = $settings['base_theme_dir'] = $settings['default_theme_dir'];
	getLanguages();

	// Put them back.
	$settings['actual_theme_dir'] = $backup_actual_theme_dir;
	if (!empty($backup_base_theme_dir))
		$settings['base_theme_dir'] = $backup_base_theme_dir;
	else
		unset($settings['base_theme_dir']);

	// Get the language files and data...
	foreach ($context['languages'] as $lang)
	{
		// Load the file to get the character set.
		$general = json_decode(file_get_contents($settings['default_theme_dir'] . '/languages/' . $lang['filename'] . '/' . $lang['filename'] . '.json'), true);

		$languages[$lang['filename']] = [
			'id' => $lang['filename'],
			'count' => 0,
			'available' => $language == $lang['filename'] || in_array($lang['filename'], $available),
			'default' => $language == $lang['filename'] || ($language == '' && $lang['filename'] == 'en-us'),
			'locale' => $general['locale'],
			'name' => $general['native_name'] . (!empty($general['english_name']) && $general['english_name'] != $general['native_name'] ? ' (' . $general['english_name'] . ')' : ''),
			'rtl' => !empty($general['is_rtl']),
		];
	}

	// Work out how many people are using each language.
	$request = $smcFunc['db']->query('', '
		SELECT lngfile, COUNT(*) AS num_users
		FROM {db_prefix}members
		GROUP BY lngfile',
		[]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Default?
		if (empty($row['lngfile']) || !isset($languages[$row['lngfile']]))
			$row['lngfile'] = $language;

		if (!isset($languages[$row['lngfile']]) && isset($languages['en-us']))
			$languages['en-us']['count'] += $row['num_users'];
		elseif (isset($languages[$row['lngfile']]))
			$languages[$row['lngfile']]['count'] += $row['num_users'];
	}
	$smcFunc['db']->free_result($request);

	// Restore the current users language.
	$txt = $old_txt;

	// Return how many we have.
	return $languages;
}

/**
 * Edit a particular set of language entries.
 */
function ModifyLanguage()
{
	global $settings, $context, $txt, $scripturl;

	loadLanguage('ManageSettings');

	// Select the languages tab.
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
	$context['page_title'] = $txt['edit_languages'];

	$context['lang_id'] = isset($_GET['lid']) ? $_GET['lid'] : '';
	list($theme_id, $file_id) = empty($_REQUEST['tfid']) || strpos($_REQUEST['tfid'], '_') === false ? [1, ''] : explode('_', $_REQUEST['tfid']);

	// Clean the ID - just in case.
	if (preg_match('~([A-Za-z0-9_-]+)~', $context['lang_id'], $matches))
	{
		$context['lang_id'] = $matches[1];
	}
	else
	{
		$context['lang_id'] = '';
	}

	// Get all the theme data.
	$themes = [
		1 => [
			'name' => $txt['default_templates'],
			'theme_dir' => $settings['default_theme_dir'],
		],
	];
	foreach (Theme::get_theme_list() as $tid => $theme)
	{
		if (isset($themes[$tid]))
		{
			continue;
		}
		$themes[$tid] = $theme;
	}

	// This will be where we look
	$lang_dirs = [];

	// Does a hook need to add in some additional places to look for languages?
	call_integration_hook('integrate_modifylanguages', [&$themes, &$lang_dirs]);

	// Check we have themes with a path and a name - just in case - and add the path.
	foreach ($themes as $id => $data)
	{
		if (count($data) != 2)
			unset($themes[$id]);
		elseif (is_dir($data['theme_dir'] . '/languages'))
			$lang_dirs[$id] = $data['theme_dir'] . '/languages';
	}

	$current_file = $file_id ? $lang_dirs[$theme_id] . '/' . $context['lang_id'] . '/' . $file_id . '.php' : '';
	if (!file_exists($current_file))
	{
		$current_file = '';
	}

	// Now for every theme get all the files and stick them in context!
	$context['possible_files'] = [];
	foreach ($lang_dirs as $theme => $theme_dir)
	{
		// Open it up.
		$dir = dir($theme_dir);
		while ($entry = $dir->read())
		{
			if ($entry[0] == '.')
			{
				continue;
			}

			if (!is_dir($theme_dir . '/' . $entry) || !file_exists($theme_dir . '/' . $entry . '/' . $entry . '.json'))
				continue;

			foreach (scandir($theme_dir . '/' . $entry) as $file)
			{
				if (!preg_match('/^([A-Z][A-Za-z0-9]+)\.php$/', $file, $matches))
				{
					continue;
				}

				if ($matches[1] == 'EmailTemplates')
				{
					continue;
				}

				if (!isset($context['possible_files'][$theme]))
				{
					$context['possible_files'][$theme] = [
						'id' => $theme,
						'name' => $themes[$theme]['name'],
						'files' => [
							'main_files' => [],
							'admin_files' => [],
							'all_files' => [],
						]
					];
				}

				$file_entry = [
					'id' => $matches[1],
					'name' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1],
					'selected' => $theme_id == $theme && $file_id == $matches[1],
					'edit_link' => $scripturl . '?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . ';tfid=' . $theme . '_' . $matches[1],
				];

				$context['possible_files'][$theme]['files']['all_files'][] = $file_entry;

				$is_admin = in_array($matches[1], ['Admin', 'Modlog']) || strpos($matches[1], 'Manage') === 0;
				$file_block = $is_admin ? 'admin_files' : 'main_files';
				$context['possible_files'][$theme]['files'][$file_block][$matches[1]] = $file_entry;
			}
		}
		$dir->close();
		foreach (['all_files', 'main_files', 'admin_files'] as $fileset)
		{
			uasort($context['possible_files'][$theme]['files'][$fileset], function($val1, $val2)
			{
				return strcmp($val1['name'], $val2['name']);
			});
		}
		// While we had the general strings sorted into their own bubble, we should put them first.
		$context['possible_files'][$theme]['files']['main_files'] = array_merge(
			['General' => $context['possible_files'][$theme]['files']['main_files']['General']],
			$context['possible_files'][$theme]['files']['main_files']
		);
	}

	// Quickly load index language entries.
	$language_manifest = @json_decode(file_get_contents($settings['default_theme_dir'] . '/languages/' . $context['lang_id'] . '/' . $context['lang_id'] . '.json'), true);
	$context['lang_file_not_writable_message'] = '';
	// Setup the primary settings context.
	$context['primary_settings'] = [
		'name' => $language_manifest['native_name'],
		'locale' => $language_manifest['locale'],
		'rtl' => !empty($language_manifest['is_rtl']),
	];

	// If we are editing a file work away at that.
	if ($current_file)
	{
		$master = Language::get_file_for_editing($current_file);
		$delta = Language::get_language_changes((int) $theme_id, $context['lang_id'], $file_id);
		$context['entries'] = [];

		foreach ($master as $lang_var => $lang_strings)
		{
			foreach ($lang_strings as $lang_key => $lang_string)
			{
				$context['entries'][$lang_var . '_' . $lang_key] = [
					'link' => $scripturl . '?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . ';tfid=' . $theme_id . '_' . $file_id . ';eid=' . $lang_var . '_' . urlencode($lang_key),
					'lang_var' => $lang_var,
					'display' => $lang_key,
					'master' => $lang_string,
					'current' => isset($delta[$lang_var][$lang_key]) ? $delta[$lang_var][$lang_key] : '',
				];
			}
		}

		if (isset($_GET['eid']) && isset($context['entries'][$_GET['eid']]))
		{
			$context['sub_template'] = 'admin_languages_edit_entry';
			loadJavaScriptFile('manage_languages.js', ['defer' => true, 'minimize' => false], 'manage_languages');
			$context['current_entry'] = $context['entries'][$_GET['eid']];
			if (empty($context['current_entry']['current']))
			{
				$context['current_entry']['current'] = $context['current_entry']['master'];
			}

			// This is the path we might actually save something.
			if (isset($_POST['save_entry']))
			{
				checkSession();
				validateToken('admin-mlang');

				if (is_array($context['current_entry']['master']))
				{
					$entries = [];
					if (!empty($_POST['entry_key']) && !empty($_POST['entry_value']) && is_array($_POST['entry_key']) && is_array($_POST['entry_value']))
					{
						foreach ($_POST['entry_key'] as $k => $v)
						{
							if (trim($v) != '' && isset($_POST['entry_value'][$k]))
							{
								$entries[$v] = (string) $_POST['entry_value'][$k];
							}
						}
						// Let's see if they are the same.
						$master = $context['current_entry']['master'];
						asort($master);
						$current = $entries;
						asort($current);
						if ($master === $current)
						{
							Language::delete_current_entry((int) $theme_id, $context['lang_id'], $file_id, $context['current_entry']['lang_var'], $context['current_entry']['display']);
						}
						else
						{
							Language::save_multiple_entry((int) $theme_id, $context['lang_id'], $file_id, $context['current_entry']['lang_var'], $context['current_entry']['display'], $entries);
						}
					}
				}
				else
				{
					$entry = !empty($_POST['entry']) ? $_POST['entry'] : '';
					if ($entry === $context['current_entry']['master'])
					{
						Language::delete_current_entry((int) $theme_id, $context['lang_id'], $file_id, $context['current_entry']['lang_var'], $context['current_entry']['display']);
					}
					else
					{
						Language::save_single_entry((int) $theme_id, $context['lang_id'], $file_id, $context['current_entry']['lang_var'], $context['current_entry']['display'], $entry);
					}
				}

				session_flash('success', $txt['settings_saved']);
				redirectexit('action=admin;area=languages;sa=editlang;lid=' . $context['lang_id']);
			}
		}
		else
		{
			$context['sub_template'] = 'admin_languages_edit_list';
		}
	}
	else
	{
		$context['sub_template'] = 'admin_languages_select';

		loadLanguage('Login');
		$context['policies'] = [];
		foreach (Policy::get_policy_list() as $policy_type)
		{
			$context['policies'][] = [
				'name' => $txt['policy_type_' . $policy_type['policy_type']],
				'link' => $scripturl . '?action=admin;area=regcenter;sa=policies;policy=' . $policy_type['id_policy_type'] . ';lang=' . $context['lang_id'],
			];
		}
	}

	createToken('admin-mlang');
}
