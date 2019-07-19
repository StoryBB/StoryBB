<?php

/**
 * Helper file for handling themes.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * Gets a single theme's info.
 *
 * @param int $id The theme ID to get the info from.
 * @return array The theme info as an array.
 */
function get_single_theme($id)
{
	global $smcFunc, $modSettings;

	// No data, no fun!
	if (empty($id))
		return false;

	// Make sure $id is an int.
	$id = (int) $id;

	// List of all possible values.
	$themeValues = array(
		'theme_dir',
		'images_url',
		'theme_url',
		'name',
		'version',
		'install_for',
	);

	// Make changes if you really want it.
	call_integration_hook('integrate_get_single_theme', array(&$themeValues, $id));

	$single = array(
		'id' => $id,
	);

	// Make our known/enable themes a little easier to work with.
	$knownThemes = !empty($modSettings['knownThemes']) ? explode(',', $modSettings['knownThemes']) : [];
	$enableThemes = !empty($modSettings['enableThemes']) ? explode(',', $modSettings['enableThemes']) : [];

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({array_string:theme_values})
			AND id_theme = ({int:id_theme})
			AND id_member = {int:no_member}',
		array(
			'theme_values' => $themeValues,
			'id_theme' => $id,
			'no_member' => 0,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$single[$row['variable']] = $row['value'];

		// Fix the path and tell if its a valid one.
		if ($row['variable'] == 'theme_dir')
		{
			$single['theme_dir'] = realpath($row['value']);
			$single['valid_path'] = file_exists($row['value']) && is_dir($row['value']);
		}
	}

	// Is this theme installed and enabled?
	$single['known'] = in_array($single['id'], $knownThemes);
	$single['enable'] = in_array($single['id'], $enableThemes);

	// It should at least return if the theme is a known one or if its enable.
	return $single;
}

/**
 * Loads and returns all installed themes.
 *
 * Stores all themes on $context['themes'] for easier use.
 * @param bool $enable_only false by default for getting all themes. If true the function will return all themes that are currently enable.
 * @return array With the theme's IDs as key.
 */
function get_all_themes($enable_only = false)
{
	global $modSettings, $context, $smcFunc;

	// Make our known/enable themes a little easier to work with.
	$knownThemes = !empty($modSettings['knownThemes']) ? explode(',', $modSettings['knownThemes']) : [];
	$enableThemes = !empty($modSettings['enableThemes']) ? explode(',', $modSettings['enableThemes']) : [];

	// List of all possible themes values.
	$themeValues = array(
		'theme_dir',
		'images_url',
		'theme_url',
		'name',
		'version',
		'install_for',
	);

	// Make changes if you really want it.
	call_integration_hook('integrate_get_all_themes', array(&$themeValues, $enable_only));

	// So, what is it going to be?
	$query_where = $enable_only ? $enableThemes : $knownThemes;

	// Perform the query as requested.
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({array_string:theme_values})
			AND id_theme IN ({array_string:query_where})
			AND id_member = {int:no_member}',
		array(
			'query_where' => $query_where,
			'theme_values' => $themeValues,
			'no_member' => 0,
		)
	);

	$context['themes'] = [];

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['themes'][$row['id_theme']]['id'] = (int) $row['id_theme'];

		// Fix the path and tell if its a valid one.
		if ($row['variable'] == 'theme_dir')
		{
			$context['themes'][$row['id_theme']][$row['variable']] = realpath($row['value']);
			$context['themes'][$row['id_theme']]['valid_path'] = file_exists(realpath($row['value'])) && is_dir(realpath($row['value']));
		}

		$context['themes'][$row['id_theme']]['known'] = in_array($row['id_theme'], $knownThemes);
		$context['themes'][$row['id_theme']]['enable'] = in_array($row['id_theme'], $enableThemes);
		$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	}

	$smcFunc['db_free_result']($request);
}

/**
 * Reads an .json file and returns the data as an array
 *
 * Removes the entire theme if the .json file couldn't be found or read.
 * @param string $path The absolute path to the JSON file.
 * @return array An array with all the info extracted from the JSON file.
 */
function get_theme_info($path)
{
	global $sourcedir, $forum_version, $txt, $scripturl, $context;
	global $explicit_images;

	if (empty($path))
		return false;

	$explicit_images = false;

	// Perhaps they are trying to install a mod, lets tell them nicely this is the wrong function.
	if (file_exists($path . '/package-info.xml'))
	{
		loadLanguage('Errors');

		// We need to delete the dir otherwise the next time you try to install a theme you will get the same error.
		remove_dir($path);

		$txt['package_get_error_is_mod'] = str_replace('{MANAGEMODURL}', $scripturl . '?action=admin;area=packages;' . $context['session_var'] . '=' . $context['session_id'], $txt['package_get_error_is_mod']);
		fatal_lang_error('package_theme_upload_error_broken', false, $txt['package_get_error_is_mod']);
	}

	// Parse theme.json into something we can work with.
	$theme_info = @json_decode(file_get_contents($path . '/theme.json'), true);

	// Error message, there isn't any valid info.
	if (empty($theme_info) || empty($theme_info['id']) || empty($theme_info['name']))
	{
		remove_dir($path);
		fatal_lang_error('package_get_error_packageinfo_corrupt', false);
	}

	if (empty($theme_info['storybb_version']))
	{
		remove_dir($path);
		fatal_lang_error('package_get_error_theme_not_compatible', false, $forum_version);
	}

	// So, we have an install tag which is cool and stuff but we also need to check it and match your current StoryBB version...
	$the_version = strtr($forum_version, array('StoryBB ' => ''));

	// The theme isn't compatible with the current StoryBB version.
	if (!matchPackageVersion($the_version, $theme_info['storybb_version']))
	{
		remove_dir($path);
		fatal_lang_error('package_get_error_theme_not_compatible', false, $forum_version);
	}

	// Remove things that definitely shouldn't be exported up here.
	unset($theme_info['theme_settings'], $theme_info['theme_options'], $theme_info['additional_files']);
	unset($theme_info['page_index'], $theme_info['disable_files'], $theme_info['author']);

	return $theme_info;
}

/**
 * Inserts a theme's data to the DataBase.
 *
 * Ends execution with fatal_lang_error() if an error appears.
 * @param array $to_install An array containing all values to be stored into the DB.
 * @return int The newly created theme ID.
 */
function theme_install($to_install = [])
{
	global $smcFunc, $context, $themedir, $themeurl, $modSettings;
	global $settings, $explicit_images;

	// External use? no problem!
	if ($to_install)
		$context['to_install'] = $to_install;

	// One last check.
	if (empty($context['to_install']['theme_dir']) || basename($context['to_install']['theme_dir']) == 'Themes')
		fatal_lang_error('theme_install_invalid_dir', false);

	// OK, is this a newer version of an already installed theme?
	if (!empty($context['to_install']['version']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND variable = {string:name}
				AND value LIKE {string:name_value}
			LIMIT 1',
			array(
				'no_member' => 0,
				'name' => 'name',
				'version' => 'version',
				'name_value' => '%' . $context['to_install']['name'] . '%',
			)
		);

		$to_update = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Got something, lets figure it out what to do next.
		if (!empty($to_update) && !empty($to_update['version']))
			switch (version_compare($context['to_install']['version'], $to_update['version']))
			{
				case 1: // Got a newer version, update the old entry.
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}themes
						SET value = {string:new_value}
						WHERE variable = {string:version}
							AND id_theme = {int:id_theme}',
						array(
							'new_value' => $context['to_install']['version'],
							'version' => 'version',
							'id_theme' => $to_update['id_theme'],
						)
					);

					// Done with the update, tell the user about it.
					$context['to_install']['updated'] = true;

					return $to_update['id_theme'];
					break; // Just for reference.
				case 0: // This is exactly the same theme.
				case -1: // The one being installed is older than the one already installed.
				default: // Any other possible result.
					fatal_lang_error('package_get_error_theme_no_new_version', false, array($context['to_install']['version'], $to_update['version']));
			}
	}

	// Find the newest id_theme.
	$result = $smcFunc['db_query']('', '
		SELECT MAX(id_theme)
		FROM {db_prefix}themes',
		array(
		)
	);
	list ($id_theme) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// This will be theme number...
	$id_theme++;

	// Last minute changes? although, the actual array is a context value you might want to use the new ID.
	call_integration_hook('integrate_theme_install', array(&$context['to_install'], $id_theme));

	$inserts = [];
	foreach ($context['to_install'] as $var => $val)
	{
		if (is_array($val))
			continue;

		$inserts[] = array($id_theme, $var, $val);
	}

	if (!empty($inserts))
		$smcFunc['db_insert']('insert',
			'{db_prefix}themes',
			array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
			$inserts,
			array('id_theme', 'variable')
		);

	// Update the known and enable Theme's settings.
	$known = strtr($modSettings['knownThemes'] . ',' . $id_theme, array(',,' => ','));
	$enable = strtr($modSettings['enableThemes'] . ',' . $id_theme, array(',,' => ','));
	updateSettings(array('knownThemes' => $known, 'enableThemes' => $enable));

	return $id_theme;
}

/**
 * Removes a directory from the themes dir.
 *
 * This is a recursive function, it will call itself if there are subdirs inside the main directory.
 * @param string $path The absolute path to the directory to be removed
 * @return bool true when success, false on error.
 */
function remove_dir($path)
{
	if (empty($path))
		return false;

	if (is_dir($path))
	{
		$objects = scandir($path);

		foreach ($objects as $object)
			if ($object != '.' && $object != '..')
			{
				if (filetype($path . '/' . $object) == 'dir')
					remove_dir($path . '/' . $object);

				else
					unlink($path . '/' . $object);
			}
	}

	reset($objects);
	rmdir($path);
}

/**
 * Removes a theme from the DB, includes all possible places where the theme might be used.
 *
 * @param int $themeID The theme ID
 * @return bool true when success, false on error.
 */
function remove_theme($themeID)
{
	global $smcFunc, $modSettings;

	// Can't delete the default theme, sorry!
	if (empty($themeID) || $themeID == 1)
		return false;

	$known = explode(',', $modSettings['knownThemes']);
	$enable = explode(',', $modSettings['enableThemes']);

	// Remove it from the themes table.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}',
		array(
			'current_theme' => $themeID,
		)
	);

	// Update users preferences.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $themeID,
		)
	);

	// Update characters settings too.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $themeID,
		)
	);

	// Some boards may have it as preferred theme.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $themeID,
		)
	);

	// Remove it from the list of known themes.
	$known = array_diff($known, array($themeID));

	// And the enable list too.
	$enable = array_diff($enable, array($themeID));

	// Back to good old comma separated string.
	$known = strtr(implode(',', $known), array(',,' => ','));
	$enable = strtr(implode(',', $enable), array(',,' => ','));

	// Clear any cache of them having been minified before.
	cache_put_data('minimized_'. $themeID .'_css', null, 0);
	cache_put_data('minimized_'. $themeID .'_js', null, 0);

	// Update the enableThemes list.
	updateSettings(array('enableThemes' => $enable, 'knownThemes' => $known));

	// Fix it if the theme was the overall default theme.
	if ($modSettings['theme_guests'] == $themeID)
		updateSettings(array('theme_guests' => '1'));

	return true;
}
