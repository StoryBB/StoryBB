<?php

/**
 * This file contains functions that are specifically done by administrators.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * Get the contents of a locally-stored admin info file.
 *
 * If the type of the file has a better representation, attempt to provide that (e.g. unpack JSON)
 *
 * @param string $filename The filename to look up
 * @param string $path The path to match against, default to empty for storybb.org cases
 * @return mixed Returns the contents of the file
 */
function getAdminFile(string $filename, string $path = '')
{
	global $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT a.data, a.filetype
		FROM {db_prefix}admin_info_files AS a
		WHERE filename = {string:filename}
			AND a.path = {string:path}
		LIMIT 1',
		[
			'filename' => $filename,
			'path' => $path,
		]
	);
	$data = null;
	if ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$data = $row['data'];
		switch ($row['filetype'])
		{
			case 'application/json':
				return json_decode($data, true);
				break;
		}
	}
	$smcFunc['db']->free_result($request);

	return $data;
}

/**
 * Update the Settings.php file.
 *
 * The most important function in this file for mod makers happens to be the
 * updateSettingsFile() function, but it shouldn't be used often anyway.
 *
 * - updates the Settings.php file with the changes supplied in config_vars.
 * - expects config_vars to be an associative array, with the keys as the
 *   variable names in Settings.php, and the values the variable values.
 * - does not escape or quote values.
 * - preserves case, formatting, and additional options in file.
 * - writes nothing if the resulting file would be less than 10 lines
 *   in length (sanity check for read lock.)
 * - attempts to create a backup file and will use it should the writing of the
 *   new settings file fail
 *
 * @param array $config_vars An array of one or more variables to update
 */
function updateSettingsFile($config_vars)
{
	global $boarddir, $cachedir, $context;

	// When was Settings.php last changed?
	$last_settings_change = filemtime($boarddir . '/Settings.php');

	// Load the settings file.
	$settingsArray = trim(file_get_contents($boarddir . '/Settings.php'));

	// Break it up based on \r or \n, and then clean out extra characters.
	if (strpos($settingsArray, "\n") !== false)
		$settingsArray = explode("\n", $settingsArray);
	elseif (strpos($settingsArray, "\r") !== false)
		$settingsArray = explode("\r", $settingsArray);
	else
		return;

	// Presumably, the file has to have stuff in it for this function to be called :P.
	if (count($settingsArray) < 10)
		return;

	// remove any /r's that made there way in here
	foreach ($settingsArray as $k => $dummy)
		$settingsArray[$k] = strtr($dummy, ["\r" => '']) . "\n";

	// go line by line and see whats changing
	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		// Don't trim or bother with it if it's not a variable.
		if (substr($settingsArray[$i], 0, 1) != '$')
			continue;

		$settingsArray[$i] = trim($settingsArray[$i]) . "\n";

		// Look through the variables to set....
		foreach ($config_vars as $var => $val)
		{
			if (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
			{
				$comment = strstr(substr($settingsArray[$i], strpos($settingsArray[$i], ';')), '#');
				$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment == '' ? '' : "\t\t" . rtrim($comment)) . "\n";

				// This one's been 'used', so to speak.
				unset($config_vars[$var]);
			}
		}

		// End of the file ... maybe
		if (substr(trim($settingsArray[$i]), 0, 2) == '?' . '>')
			$end = $i;
	}

	// This should never happen, but apparently it is happening.
	if (empty($end) || $end < 10)
		$end = count($settingsArray) - 1;

	// Still more variables to go?  Then lets add them at the end.
	if (!empty($config_vars))
	{
		if (trim($settingsArray[$end]) == '?' . '>')
			$settingsArray[$end++] = '';
		else
			$end++;

		// Add in any newly defined vars that were passed
		foreach ($config_vars as $var => $val)
			$settingsArray[$end++] = '$' . $var . ' = ' . $val . ';' . "\n";

		$settingsArray[$end] = '?' . '>';
	}
	else
		$settingsArray[$end] = trim($settingsArray[$end]);

	// Sanity error checking: the file needs to be at least 12 lines.
	if (count($settingsArray) < 12)
		return;

	// Try to avoid a few pitfalls:
	//  - like a possible race condition,
	//  - or a failure to write at low diskspace
	//
	// Check before you act: if cache is enabled, we can do a simple write test
	// to validate that we even write things on this filesystem.
	if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
		$cachedir = $boarddir . '/cache';

	$test_fp = @fopen($cachedir . '/settings_update.tmp', "w+");
	if ($test_fp)
	{
		fclose($test_fp);
		$written_bytes = file_put_contents($cachedir . '/settings_update.tmp', 'test', LOCK_EX);
		@unlink($cachedir . '/settings_update.tmp');

		if ($written_bytes !== 4)
		{
			// Oops. Low disk space, perhaps. Don't mess with Settings.php then.
			// No means no. :P
			return;
		}
	}

	// Protect me from what I want! :P
	clearstatcache();
	if (filemtime($boarddir . '/Settings.php') === $last_settings_change)
	{
		// save the old before we do anything
		$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');
		$settings_backup_fail = !$settings_backup_fail ? (!file_exists($boarddir . '/Settings_bak.php') || filesize($boarddir . '/Settings_bak.php') === 0) : $settings_backup_fail;

		// write out the new
		$write_settings = implode('', $settingsArray);
		$written_bytes = file_put_contents($boarddir . '/Settings.php', $write_settings, LOCK_EX);

		// survey says ...
		if ($written_bytes !== strlen($write_settings) && !$settings_backup_fail)
		{
			// Well this is not good at all, lets see if we can save this
			$context['settings_message'] = 'settings_error';

			if (file_exists($boarddir . '/Settings_bak.php'))
				@copy($boarddir . '/Settings_bak.php', $boarddir . '/Settings.php');
		}
	}

	// Even though on normal installations the filemtime should prevent this being used by the installer incorrectly
	// it seems that there are times it might not. So let's MAKE it dump the cache.
	if (function_exists('opcache_invalidate'))
		opcache_invalidate($boarddir . '/Settings.php', true);
}

/**
 * Saves the admin's current preferences to the database.
 */
function updateAdminPreferences()
{
	global $options, $context, $smcFunc, $settings, $user_info;

	// This must exist!
	if (!isset($context['admin_preferences']))
		return false;

	// This is what we'll be saving.
	$options['admin_preferences'] = json_encode($context['admin_preferences']);

	// Just check we haven't ended up with something theme exclusive somehow.
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
		AND variable = {string:admin_preferences}',
		[
			'default_theme' => 1,
			'admin_preferences' => 'admin_preferences',
		]
	);

	// Update the themes table.
	$smcFunc['db']->insert('replace',
		'{db_prefix}themes',
		['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
		[$user_info['id'], 1, 'admin_preferences', $options['admin_preferences']],
		['id_member', 'id_theme', 'variable']
	);

	// Make sure we invalidate any cache.
	cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 0);
}

/**
 * Send all the administrators a lovely email.
 * - loads all users who are admins or have the admin forum permission.
 * - uses the email template and replacements passed in the parameters.
 * - sends them an email.
 *
 * @param string $template Which email template to use
 * @param array $replacements An array of items to replace the variables in the template
 * @param array $additional_recipients An array of arrays of info for additional recipients. Should have 'id', 'email' and 'name' for each.
 */
function emailAdmins($template, $replacements = [], $additional_recipients = [])
{
	global $smcFunc, $sourcedir, $language, $modSettings;

	// We certainly want this.
	require_once($sourcedir . '/Subs-Post.php');

	// Load all members which are effectively admins.
	require_once($sourcedir . '/Subs-Members.php');
	$members = membersAllowedTo('admin_forum');

	$request = $smcFunc['db']->query('', '
		SELECT id_member, member_name, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE id_member IN({array_int:members})',
		[
			'members' => $members,
		]
	);
	$emails_sent = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Stick their particulars in the replacement data.
		$replacements['IDMEMBER'] = $row['id_member'];
		$replacements['REALNAME'] = $row['member_name'];
		$replacements['USERNAME'] = $row['real_name'];

		// Load the data from the template.
		$emaildata = loadEmailTemplate($template, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// Then send the actual email.
		StoryBB\Helper\Mail::send($row['email_address'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);

		// Track who we emailed so we don't do it twice.
		$emails_sent[] = $row['email_address'];
	}
	$smcFunc['db']->free_result($request);

	// Any additional users we must email this to?
	if (!empty($additional_recipients))
		foreach ($additional_recipients as $recipient)
		{
			if (in_array($recipient['email'], $emails_sent))
				continue;

			$replacements['IDMEMBER'] = $recipient['id'];
			$replacements['REALNAME'] = $recipient['name'];
			$replacements['USERNAME'] = $recipient['name'];

			// Load the template again.
			$emaildata = loadEmailTemplate($template, $replacements, empty($recipient['lang']) || empty($modSettings['userLanguage']) ? $language : $recipient['lang']);

			// Send off the email.
			StoryBB\Helper\Mail::send($recipient['email'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);
		}
}
