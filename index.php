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
App::start(__DIR__);

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

// If $maintenance is set specifically to 2, then we're upgrading or something.
if (!empty($maintenance) && $maintenance == 2)
	display_maintenance_message();

$result = App::dispatch_request(Request::createFromGlobals());

if ($result && $result instanceof Response)
{
	ob_end_clean();
	$result->send();
	exit;
}

// Load the settings from the settings table, and perform operations like optimizing.
$context = [];
reloadSettings();
// Clean the request variables, add slashes, etc.
cleanRequest();

// Before we get carried away, are we doing a scheduled task? If so save CPU cycles by jumping out!
if (isset($_GET['scheduled']))
{
	require_once($sourcedir . '/ScheduledTasks.php');
	AutoTask();
}

// And important includes.
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Logging.php');
require_once($sourcedir . '/Security.php');

// Register an error handler.
set_error_handler('sbb_error_handler');

// What function shall we execute? (done like this for memory's sake.)
call_user_func(sbb_main());

// Call obExit specially; we're coming from the main area ;).
obExit(null, null, true);

/**
 * The main dispatcher.
 * This delegates to each area.
 * @return array|string|void An array containing the file to include and name of function to call, the name of a function to call or dies with a fatal_lang_error if we couldn't find anything to do.
 */
function sbb_main()
{
	global $modSettings, $settings, $user_info, $board, $topic, $context;
	global $board_info, $maintenance, $sourcedir;

	// We should set our security headers now.
	frameOptionsHeader();

	// Load the user's cookie (or set as guest) and load their settings.
	loadUserSettings();

	// Load the current board's information.
	loadBoard();

	// Load the current user's permissions.
	loadPermissions();

	// Attachments don't require the entire theme to be loaded.
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'dlattach')
		detectBrowser();
	// Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
	else
		loadTheme();

	// Check if the user should be disallowed access.
	is_not_banned();

	// If we are in a topic and don't have permission to approve it then duck out now.
	if (!empty($topic) && empty($board_info['cur_topic_approved']) && !allowedTo('approve_posts') && ($user_info['id'] != $board_info['cur_topic_starter'] || $user_info['is_guest']))
		fatal_lang_error('not_a_topic', false);

	$no_stat_actions = ['autocomplete', 'dlattach', 'jsoption', 'likes', 'suggest', '.xml', 'xmlhttp', 'verificationcode', 'viewquery'];
	call_integration_hook('integrate_pre_log_stats', [&$no_stat_actions]);
	// Do some logging, unless this is an attachment, avatar, toggle of editor buttons, theme option, XML feed etc.
	if (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], $no_stat_actions))
	{
		// Log this user as online.
		writeLog();

		// Track forum statistics and hits...?
		if (!empty($modSettings['hitStats']))
			trackStats(['hits' => '+']);
	}
	unset($no_stat_actions);

	// Is the forum in maintenance mode? (doesn't apply to administrators.)
	if (!empty($maintenance) && !allowedTo('admin_forum'))
	{
		// You're getting maintenance mode; neither login nor logout run through here.
		return 'InMaintenance';
	}
	// If guest access is off, a guest can only do one of the very few following actions.
	elseif (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], ['reminder', 'activate', 'help', 'helpadmin', 'verificationcode', 'signup', 'signup2'])))
		return 'KickGuest';

	// Apply policy settings if appropriate.
	if ($user_info['id'] && $user_info['policy_acceptance'] != 2) /* StoryBB\Model\Policy::POLICY_CURRENTLYACCEPTED */
	{
		// Some agreement is probably necessary.
		require_once($sourcedir . '/Reagreement.php');

		if (!on_allowed_reagreement_actions())
		{
			return 'Reagreement';
		}
	}

	if (empty($_REQUEST['action']))
	{
		// Action and board are both empty... BoardIndex! Unless someone else wants to do something different.
		if (empty($board) && empty($topic))
		{
			$_REQUEST['action'] = !empty($modSettings['integrate_default_action']) ? $modSettings['integrate_default_action'] : 'forum';
		}

		// Topic is empty, and action is empty.... MessageIndex!
		elseif (empty($topic))
		{
			$_REQUEST['action'] = 'board';
		}

		// Board is not empty... topic is not empty... action is empty.. Display!
		else
		{
			$_REQUEST['action'] = 'topic';
		}
	}

	// Setting the cookie cookie.
	if ($_REQUEST['action'] == 'cookie')
	{
		if ($context['show_cookie_notice'] && $context['user']['is_guest'])
		{
			setcookie('cookies', '1', time() + (30 * 24 * 60 * 60));
		}
		redirectexit();
	}

	$context['current_action'] = isset($_REQUEST['action']) ? StringLibrary::escape($_REQUEST['action']) : null;

	// Here's the monstrous $_REQUEST['action'] array - $_REQUEST['action'] => array($file, $function).
	$actionArray = [
		'activate' => ['Register.php', 'Activate'],
		'admin' => ['Admin.php', 'AdminMain'],
		'attachapprove' => ['ManageAttachments.php', 'ApproveAttach'],
		'autocomplete' => ['Autocomplete.php', 'Autocomplete'],
		'board' => ['MessageIndex.php', 'MessageIndex'],
		'bookmark' => ['Bookmark.php', 'Bookmark'],
		'buddy' => ['Subs-Members.php', 'BuddyListToggle'],
		'characters' => ['Profile-Chars.php', 'CharacterList'],
		'contact' => ['Contact.php', 'Contact'],
		'deletemsg' => ['RemoveTopic.php', 'DeleteMessage'],
		'dlattach' => ['ShowAttachments.php', 'showAttachment'],
		'editpoll' => ['Poll.php', 'EditPoll'],
		'editpoll2' => ['Poll.php', 'EditPoll2'],
		'forum' => ['BoardIndex.php', 'BoardIndex'],
		'groups' => ['Groups.php', 'Groups'],
		'help' => ['Help.php', 'ShowHelp'],
		'helpadmin' => ['Help.php', 'ShowAdminHelp'],
		'jsmodify' => ['Post.php', 'JavaScriptModify'],
		'jsoption' => ['Themes.php', 'SetJavaScript'],
		'likes' => ['Likes.php', 'Likes::call#'],
		'lock' => ['Topic.php', 'LockTopic'],
		'lockvoting' => ['Poll.php', 'LockVoting'],
		'markasread' => ['Subs-Boards.php', 'MarkRead'],
		'mergetopics' => ['SplitTopics.php', 'MergeTopics'],
		'moderate' => ['ModerationCenter.php', 'ModerationMain'],
		'movetopic' => ['MoveTopic.php', 'MoveTopic'],
		'movetopic2' => ['MoveTopic.php', 'MoveTopic2'],
		'notify' => ['Notify.php', 'Notify'],
		'notifyboard' => ['Notify.php', 'BoardNotify'],
		'notifytopic' => ['Notify.php', 'TopicNotify'],
		'pm' => ['PersonalMessage.php', 'MessageMain'],
		'post' => ['Post.php', 'Post'],
		'post2' => ['Post.php', 'Post2'],
		'profile' => ['Profile.php', 'ModifyProfile'],
		'quotefast' => ['Post.php', 'QuoteFast'],
		'quickmod' => ['MessageIndex.php', 'QuickModeration'],
		'quickmod2' => ['Display.php', 'QuickInTopicModeration'],
		'reattributepost' => ['Profile-Chars.php', 'ReattributePost'],
		'reagreement' => ['Reagreement.php', 'Reagreement'],
		'recent' => ['Recent.php', 'RecentPosts'],
		'reminder' => ['Reminder.php', 'RemindMe'],
		'removepoll' => ['Poll.php', 'RemovePoll'],
		'removetopic2' => ['RemoveTopic.php', 'RemoveTopic2'],
		'reporttm' => ['ReportToMod.php', 'ReportToModerator'],
		'restoretopic' => ['RemoveTopic.php', 'RestoreTopic'],
		'search' => ['Search.php', 'PlushSearch1'],
		'search2' => ['Search.php', 'PlushSearch2'],
		'sendactivation' => ['Register.php', 'SendActivation'],
		'signup' => ['Register.php', 'Register'],
		'signup2' => ['Register.php', 'Register2'],
		'suggest' => ['Subs-Editor.php', 'AutoSuggestHandler'],
		'splittopics' => ['SplitTopics.php', 'SplitTopics'],
		'stats' => ['Stats.php', 'DisplayStats'],
		'sticky' => ['Topic.php', 'Sticky'],
		'theme' => ['Themes.php', 'ThemesMain'],
		'topic' => ['Display.php', 'Display'],
		'trackip' => ['Profile-View.php', 'trackIP'],
		'unread' => ['Recent.php', 'UnreadTopics'],
		'unreadreplies' => ['Recent.php', 'UnreadReplies'],
		'uploadAttach' => ['Attachments.php', 'Attachments::call#'],
		'verificationcode' => ['Register.php', 'VerificationCode'],
		'viewprofile' => ['Profile.php', 'ModifyProfile'],
		'vote' => ['Poll.php', 'Vote'],
		'viewquery' => ['ViewQuery.php', 'ViewQuery'],
		'who' => ['Who.php', 'Who'],
		'.xml' => ['News.php', 'ShowXmlFeed'],
		'xmlhttp' => ['Xml.php', 'XMLhttpMain'],
	];

	// Get the function and file to include - if it's not there, do the board index.
	if (!isset($actionArray[$_REQUEST['action']]))
	{
		fatal_lang_error('not_found', false, [], 404);
	}

	// Otherwise, it was set - so let's go to that action.
	if (!empty($actionArray[$_REQUEST['action']][0]))
	{
		require_once($sourcedir . '/' . $actionArray[$_REQUEST['action']][0]);
	}

	// Do the right thing.
	return call_helper($actionArray[$_REQUEST['action']][1], true);
}
