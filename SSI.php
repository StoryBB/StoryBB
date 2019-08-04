<?php

/**
 * Integrates StoryBB functions from outside StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

// Don't do anything if StoryBB is already loaded.
if (defined('STORYBB'))
	return true;

define('STORYBB', 'SSI');

// We're going to want a few globals... these are all set later.
global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
global $boardurl, $boarddir, $sourcedir, $webmaster_email, $cookiename;
global $db_type, $db_server, $db_name, $db_user, $db_prefix, $db_persist;
global $db_connection, $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
global $smcFunc, $ssi_db_user, $scripturl, $ssi_db_passwd, $db_passwd, $cachedir;

$time_start = microtime(true);

// Just being safe...
foreach (array('cachedir') as $variable)
	if (isset($GLOBALS[$variable]))
		unset($GLOBALS[$variable]);

// Get the forum's settings for database and file paths.
require_once(dirname(__FILE__) . '/Settings.php');

// Make absolutely sure the cache directory is defined.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';

$ssi_error_reporting = error_reporting(E_ALL);
/* Set this to one of three values depending on what you want to happen in the case of a fatal error.
	false:	Default, will just load the error sub template and die - not putting any theme layers around it.
	true:	Will load the error sub template AND put the StoryBB layers around it (Not useful if on total custom pages).
	string:	Name of a callback function to call in the event of an error to allow you to define your own methods. Will die after function returns.
*/
$ssi_on_error_method = false;

// Don't do john didley if the forum's been shut down completely.
if ($maintenance == 2 && (!isset($ssi_maintenance_off) || $ssi_maintenance_off !== true))
	die($mmessage);

// Fix for using the current directory as a path.
if (substr($sourcedir, 0, 1) == '.' && substr($sourcedir, 1, 1) != '.')
	$sourcedir = dirname(__FILE__) . substr($sourcedir, 1);

require_once($boarddir . '/vendor/symfony/polyfill-iconv/bootstrap.php');
require_once($boarddir . '/vendor/symfony/polyfill-mbstring/bootstrap.php');
require_once($boarddir . '/vendor/autoload.php');

// Load the important includes.
require_once($sourcedir . '/QueryString.php');
require_once($sourcedir . '/Session.php');
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Logging.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Security.php');
require_once($sourcedir . '/Subs-Auth.php');

// Create a variable to store some StoryBB specific functions in.
$smcFunc = [];

// Initiate the database connection and define some database functions to use.
loadDatabase();

// Load installed 'Mods' settings.
reloadSettings();
// Clean the request variables.
cleanRequest();

// Seed the random generator?
if (empty($modSettings['rand_seed']) || mt_rand(1, 250) == 69)
	sbb_seed_generator();

// Check on any hacking attempts.
if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
	die('No direct access...');
if (isset($_REQUEST['context']))
	die('No direct access...');

// Start the session... known to scramble SSI includes in cases...
if (!headers_sent())
	loadSession();
else
{
	if (isset($_COOKIE[session_name()]) || isset($_REQUEST[session_name()]))
	{
		// Make a stab at it, but ignore the E_WARNINGs generated because we can't send headers.
		$temp = error_reporting(error_reporting() & !E_WARNING);
		loadSession();
		error_reporting($temp);
	}

	if (!isset($_SESSION['session_value']))
	{
		$_SESSION['session_var'] = substr(md5(mt_rand() . session_id() . mt_rand()), 0, rand(7, 12));
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
	}
	$sc = $_SESSION['session_value'];
}

// Get rid of $board and $topic... do stuff loadBoard would do.
unset($board, $topic);
$user_info['is_mod'] = false;
$context['user']['is_mod'] = &$user_info['is_mod'];
$context['linktree'] = [];

// Load the user and their cookie, as well as their settings.
loadUserSettings();

// Load the current user's permissions....
loadPermissions();

// Load the current or SSI theme. (just use $ssi_theme = id_theme;)
loadTheme();

// @todo: probably not the best place, but somewhere it should be set...
if (!headers_sent())
	header('Content-Type: text/html; charset=UTF-8');

// Do we allow guests in here?
if (empty($ssi_guest_access) && empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && basename($_SERVER['PHP_SELF']) != 'SSI.php')
{
	require_once($sourcedir . '/Subs-Auth.php');
	KickGuest();
	obExit(null, true);
}

setupThemeContext();

// Make sure they didn't muss around with the settings... but only if it's not cli.
if (isset($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['is_cli']) && session_id() == '')
	trigger_error($txt['ssi_session_broken'], E_USER_NOTICE);

// Without visiting the forum this session variable might not be set on submit.
if (!isset($_SESSION['USER_AGENT']) && (!isset($_GET['ssi_function']) || $_GET['ssi_function'] !== 'pollVote'))
	$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

// Have the ability to easily add functions to SSI.
call_integration_hook('integrate_SSI');

error_reporting($ssi_error_reporting);

return true;
