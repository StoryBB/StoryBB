<?php

/**
 * Checks the schema and implements upgrades if needed.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

define('STORYBB', 'BACKGROUND');
define('FROM_CLI', empty($_SERVER['REQUEST_METHOD']));

require_once(__DIR__ . '/../Settings.php');

$safe_mode = true;
if (!FROM_CLI)
{
	if (!isset($admin_cli_password) || !isset($_GET['secret']) || $_GET['secret'] !== $admin_cli_password)
	{
		die('Script can only be run from command line or with administrative CLI password');
	}
	if (isset($_GET['safe_mode']) && $_GET['safe_mode'] === 'false')
	{
		$safe_mode = false;
	}
}
else
{
	if (in_array('--safe_mode=false', $argv))
	{
		$safe_mode = false;
	}
}

require_once($boarddir . '/vendor/symfony/polyfill-iconv/bootstrap.php');
require_once($boarddir . '/vendor/symfony/polyfill-mbstring/bootstrap.php');
require_once($boarddir . '/vendor/autoload.php');

require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Subs.php');

unset ($db_show_debug);
loadDatabase();
reloadSettings();

require_once($sourcedir . '/Subs-Admin.php');
updateSettingsFile(['maintenance' => 2]);

$results = StoryBB\Schema\Database::update_schema($safe_mode);

updateSettingsFile(['maintenance' => 0]);

if ($safe_mode)
{
	foreach ($results as $resultid => $result)
	{
		if (empty($result))
		{
			unset($results[$resultid]);
			continue;
		}
		$result = rtrim($result);
		if (substr($result, -1) !== ';')
		{
			$results[$resultid] = $result . ';';
		}
	}

	if (!empty($results))
	{
		$output = "The following queries would be run in non-safe mode:\n\n" . implode("\n\n", $results);
		echo FROM_CLI ? $output . "\n\n" : str_replace("\n", '<br>');
	}
	else
	{
		echo 'Schema is already up to date.';
	}
}
else
{
	echo 'Schema is now up to date.';
}
