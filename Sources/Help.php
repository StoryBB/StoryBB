<?php

/**
 * This file has the important job of taking care of help messages and the help center.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Container;
use StoryBB\Helper\Parser;

/**
 * Show some of the more detailed help to give the admin an idea...
 * It shows a popup for administrative or user help.
 * It uses the help parameter to decide what string to display and where to get
 * the string from. ($helptxt or $txt?)
 * It is accessed via ?action=helpadmin;help=?.
 * @uses ManagePermissions language file, if the help starts with permissionhelp.
 * @uses Help template, popup sub template, no layers.
 */
function ShowAdminHelp()
{
	global $txt, $helptxt, $context, $scripturl;

	if (!isset($_GET['help']) || !is_string($_GET['help']))
		fatal_lang_error('no_access', false);

	if (!isset($helptxt))
		$helptxt = [];

	// Load the admin help language file and template.
	loadLanguage('Help');

	// Permission specific help?
	if (isset($_GET['help']) && substr($_GET['help'], 0, 14) == 'permissionhelp')
		loadLanguage('ManagePermissions');

	// Allow mods to load their own language file here
	call_integration_hook('integrate_helpadmin');

	StoryBB\Template::set_layout('popup');

	// Set the page title to something relevant.
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];
	$context['popup_id'] = 'help_popup';

	// Don't show any template layers, just the popup sub template.
	$context['sub_template'] = 'help_text';

	// What help string should be used?
	if (isset($helptxt[$_GET['help']]))
		$context['help_text'] = $helptxt[$_GET['help']];
	elseif (isset($txt[$_GET['help']]))
		$context['help_text'] = $txt[$_GET['help']];
	else
		$context['help_text'] = $_GET['help'];

	// Does this text contain a link that we should fill in?
	if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text']))
		$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
}
