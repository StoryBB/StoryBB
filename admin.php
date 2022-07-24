<?php

/**
 * This, as you have probably guessed, is the crux on which StoryBB functions.
 * Everything should start here, so all the setup and security is done
 * properly.  The most interesting part of this file is the action array in
 * the sbb_main() function.  It is formatted as so:
 * 	'action-in-url' => array('Source-File.php', 'FunctionToCall'),
 *
 * Then, you can access the FunctionToCall() function from Source-File.php
 * with the URL index.php?action=action-in-url.  Relatively simple, no?
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\Hook\Mutatable;
use StoryBB\StringLibrary;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;

// Get everything started up...
define('STORYBB', 1);

$php_version = phpversion();
if (version_compare($php_version, '7.1.3', '<'))
{
	die("PHP 7.1.3 or newer is required, your server has " . $php_version . ". Please ask your host to upgrade PHP.");
}

require_once(__DIR__ . '/vendor/autoload.php');
App::start(__DIR__, new \StoryBB\App\AdminWeb);

error_reporting(E_ALL);
$time_start = microtime(true);

// This makes it so headers can be sent!
ob_start();

// Load the settings...
require(dirname(__FILE__) . '/Settings.php');

// Without those we can't go anywhere
require_once($sourcedir . '/QueryString.php');
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/Subs-Auth.php');
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Load.php');

// If we're in hard maintenance, we're upgrading or something.
if (App::in_hard_maintenance())
{
	App::show_hard_maintenance_message();
}

$result = App::dispatch_request();

if ($result && $result instanceof Response)
{
	ob_end_clean();
	$result->send();
	exit;
}
