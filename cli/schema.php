<?php

/**
 * Checks the schema and implements upgrades if needed.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

define('STORYBB', 'BACKGROUND');
define('FROM_CLI', empty($_SERVER['REQUEST_METHOD']));

require_once(__DIR__ . '/../Settings.php');

if (!FROM_CLI)
{
	if (!isset($admin_cli_password) || !isset($_GET['secret']) || $_GET['secret'] !== $admin_cli_password)
	{
		die('Script can only be run from command line or with administrative CLI password');
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

StoryBB\Schema\Database::update_schema();

updateSettingsFile(['maintenance' => 0]);

echo 'Schema is now up to date.';