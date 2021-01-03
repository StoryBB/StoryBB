<?php

/**
 * This is a slightly strange file. It is not designed to ever be run directly from within StoryBB's
 * conventional running, but called externally to facilitate background tasks. It can be called
 * either directly or via cron, and in either case will completely ignore anything supplied
 * via command line, or $_GET, $_POST, $_COOKIE etc. because those things should never affect the
 * running of this script.
 *
 * Because of the way this runs, etc. we do need some of StoryBB but not everything to try to keep this
 * running a little bit faster.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\Cli\App as CliApp;
use StoryBB\Container;

define('STORYBB', 'BACKGROUND');
define('FROM_CLI', empty($_SERVER['REQUEST_METHOD']));

// This one setting is worth bearing in mind. If you are running this from proper cron, make sure you
// don't run this file any more frequently than indicated here. It might turn ugly if you do.
// But on proper cron you can always increase this value provided you don't go beyond max_limit.
define('MAX_CRON_TIME', 10);
// If a task fails for whatever reason it will still be marked as claimed. This is the threshold
// by which if a task has not completed in this time, the task should become available again.
define('MAX_CLAIM_THRESHOLD', 300);

// We're going to want a few globals... these are all set later.
global $time_start, $maintenance, $msubject, $mmessage, $language;
global $boardurl, $boarddir, $sourcedir, $webmaster_email;
global $db_server, $db_name, $db_user, $db_prefix, $db_persist;
global $modSettings, $context, $sc, $user_info, $txt;
global $smcFunc, $scripturl, $db_passwd, $cachedir;

define('TIME_START', microtime(true));

require_once(__DIR__ . '/vendor/autoload.php');

App::start(__DIR__);

if (App::in_maintenance())
{
	die(App::get_global_config_item('maintenance_message'));
}
$container = Container::instance();
CliApp::build_container(App::get_global_config());

$smcFunc = [
	'db' => $container->get('database'),
];

$sourcedir = App::get_sources_path();
$cachedir = $container->get('cachedir');

// Have we already turned this off? If so, exist gracefully.
if (file_exists($cachedir . '/cron.lock'))
	obExit_cron();

// Before we go any further, if this is not a CLI request, we need to do some checking.
if (!FROM_CLI)
{
	// We will clean up $_GET shortly. But we want to this ASAP.
	$ts = isset($_GET['ts']) ? (int) $_GET['ts'] : 0;
	if ($ts <= 0 || $ts % 15 != 0 || time() - $ts < 0 || time() - $ts > 20)
		obExit_cron();
}

// Load the most important includes. In general, a background should be loading its own dependencies.
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Subs.php');

// This is our general bootstrap but a bit minimal.
reloadSettings();

// We need to init some super-default things because there's a lot of code that might accidentally rely on it.
$user_info = [
	'time_offset' => 0,
	'time_format' => '%b %d, %Y, %I:%M %p',
];

// Just in case there's a problem...
set_error_handler('sbb_error_handler_cron');
$sc = '';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['REQUEST_URL'] = FROM_CLI ? 'CLI cron.php' : $boardurl . '/cron.php';

// Now 'clean the request' (or more accurately, ignore everything we're not going to use)
cleanRequest_cron();

// At this point we could reseed the RNG but I don't think we need to risk it being seeded *even more*.
// Meanwhile, time we got on with the real business here.
$db = $container->get('database');
while ($task_details = fetch_task($db))
{
	$result = perform_task($task_details);
	if ($result)
	{
		$db->query('', '
			DELETE FROM {db_prefix}adhoc_tasks
			WHERE id_task = {int:task}',
			[
				'task' => $task_details['id_task'],
			]
		);
	}
}
obExit_cron();
exit;

/**
 * The heart of this cron handler...
 * @return bool|array False if there's nothing to do or an array of info about the task
 */
function fetch_task($db)
{
	// Check we haven't run over our time limit.
	if (microtime(true) - TIME_START > MAX_CRON_TIME)
		return false;

	// Try to find a task. Specifically, try to find one that hasn't been claimed previously, or failing that,
	// a task that was claimed but failed for whatever reason and failed long enough ago. We should not care
	// what task it is, merely that it is one in the queue, the order is irrelevant.
	$request = $db->query('', '
		SELECT id_task, task_file, task_class, task_data, claimed_time
		FROM {db_prefix}adhoc_tasks
		WHERE claimed_time < {int:claim_limit}
		LIMIT 1',
		[
			'claim_limit' => time() - MAX_CLAIM_THRESHOLD,
		]
	);
	if ($row = $db->fetch_assoc($request))
	{
		// We found one. Let's try and claim it immediately.
		$db->free_result($request);
		$db->query('', '
			UPDATE {db_prefix}adhoc_tasks
			SET claimed_time = {int:new_claimed}
			WHERE id_task = {int:task}
				AND claimed_time = {int:old_claimed}',
			[
				'new_claimed' => time(),
				'task' => $row['id_task'],
				'old_claimed' => $row['claimed_time'],
			]
		);
		// Could we claim it? If so, return it back.
		if ($db->affected_rows() != 0)
		{
			// Update the time and go back.
			$row['claimed_time'] = time();
			// Also, put this into the 'session' value in case the error log needs to show it.
			$sc = 'task' . $row['id_task'];
			return $row;
		}
		else
		{
			// Uh oh, we just missed it. Try to claim another one, and let it fall through if there aren't any.
			return fetch_task($db);
		}
	}
	else
	{
		// No dice. Clean up and go home.
		$db->free_result($request);
		return false;
	}
}

/**
 * This actually handles the task
 * @param array $task_details An array of info about the task
 * @return bool|void True if the task is invalid; otherwise calls the function to execute the task
 */
function perform_task($task_details)
{
	global $sourcedir, $boarddir;

	// This indicates the file to load.
	if (!empty($task_details['task_file']))
	{
		$include = strtr(trim($task_details['task_file']), ['$boarddir' => $boarddir, '$sourcedir' => $sourcedir]);
		if (file_exists($include))
			require_once($include);
	}

	if (empty($task_details['task_class']))
	{
		// This would be nice to translate but the language files aren't loaded for any specific language.
		log_error('Invalid background task specified (no class, ' . (empty($task_details['task_file']) ? ' no file' : ' to load ' . $task_details['task_file']) . ')');
		return true; // So we clear it from the queue.
	}

	// All background tasks need to be classes.
	elseif (class_exists($task_details['task_class']) && is_subclass_of($task_details['task_class'], 'StoryBB\\Task\\Adhoc'))
	{
		$details = empty($task_details['task_data']) ? [] : json_decode($task_details['task_data'], true);
		$bgtask = new $task_details['task_class']($details);
		return $bgtask->execute();
	}
	else
	{
		log_error('Invalid background task specified: (class: ' . $task_details['task_class'] . ', ' . (empty($task_details['task_file']) ? ' no file' : ' to load ' . $task_details['task_file']) . ')');
		return true; // So we clear it from the queue.
	}
}

// These are all our helper functions that resemble their big brother counterparts. These are not so important.
/**
 * Cleans up the request variables
 * @return void
 */
function cleanRequest_cron()
{
	global $scripturl, $boardurl;

	$scripturl = $boardurl . '/index.php';

	// These keys shouldn't be set...ever.
	if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
		die('Invalid request variable.');

	// Save some memory.. (since we don't use these anyway.)
	unset($GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_VARS']);
	unset($GLOBALS['HTTP_POST_FILES'], $GLOBALS['HTTP_POST_FILES']);
	unset($GLOBALS['_GET'], $GLOBALS['_POST'], $GLOBALS['_REQUEST'], $GLOBALS['_COOKIE'], $GLOBALS['_FILES']);
}

/**
 * The error handling function
 * @param int $error_level One of the PHP error level constants (see )
 * @param string $error_string The error message
 * @param string $file The file where the error occurred
 * @param int $line What line of the specified file the error occurred on
 * @return void
 */
function sbb_error_handler_cron($error_level, $error_string, $file, $line)
{
	global $modSettings;

	// Ignore errors if we're ignoring them or they are strict notices from PHP 5 (which cannot be solved without breaking PHP 4.)
	if (error_reporting() == 0)
		return;

	$error_type = 'cron';

	log_error($error_level . ': ' . $error_string, $error_type, $file, $line);

	// If this is an E_ERROR or E_USER_ERROR.... die.  Violently so.
	if ($error_level % 255 == E_ERROR)
		die('No direct access...');
}

/**
 * The exit function
 */
function obExit_cron()
{
	if (FROM_CLI)
		die(0);
	else
	{
		header('Content-Type: image/gif');
		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}
}
