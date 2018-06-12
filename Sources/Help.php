<?php

/**
 * This file has the important job of taking care of help messages and the help center.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Redirect to the user help ;).
 * It loads information needed for the help section.
 * It is accessed by ?action=help.
 * @uses Help template and Manual language file.
 */
function ShowHelp()
{
	global $context, $txt, $scripturl;

	loadLanguage('Manual');

	$subActions = array(
		'index' => 'HelpIndex',
		'rules' => 'HelpRules',
		'smileys' => 'HelpSmileys',
	);

	$context['manual_sections'] = [
		'smileys' => [
			'link' => $scripturl . '?action=help;sa=smileys',
			'title' => $txt['manual_smileys'],
			'desc' => $txt['manual_smileys_desc'],
		],
		'rules' => [
			'link' => $scripturl . '?action=help;sa=rules',
			'title' => $txt['terms_and_rules'],
			'desc' => $txt['manual_terms_and_rules'],
		],
	];

	// CRUD $subActions as needed.
	call_integration_hook('integrate_manage_help', array(&$subActions));

	$sa = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'index';
	call_helper($subActions[$sa]);
}

/**
 * The main page for the Help section
 */
function HelpIndex()
{
	global $scripturl, $context, $txt;

	$context['canonical_url'] = $scripturl . '?action=help';

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help',
		'name' => $txt['help'],
	);

	// Lastly, some minor template stuff.
	$context['page_title'] = $txt['manual_storybb_user_help'];
	$context['sub_template'] = 'help_manual';
}

/**
 * The smileys list in the Help section
 */
function HelpSmileys()
{
	global $smcFunc, $scripturl, $context, $txt, $modSettings;

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help',
		'name' => $txt['help'],
	);
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help;sa=smileys',
		'name' => $txt['manual_smileys'],
	);

	$context['smileys'] = [];
	$request = $smcFunc['db_query']('', '
		SELECT code, filename, description
		FROM {db_prefix}smileys
		ORDER BY smiley_row, smiley_order, hidden');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['smileys'][$row['filename']]))
		{
			$context['smileys'][$row['filename']] = [
				'text' => $row['description'],
				'code' => [$row['code']],
				'image' => $modSettings['smileys_url'] . '/' . $modSettings['smiley_sets_default'] . '/' . $row['filename'],
			];
		}
		else
		{
			if (empty($context['smileys'][$row['filename']]['text']))
			{
				$context['smileys'][$row['filename']]['text'] = $row['description'];
			}
			$context['smileys'][$row['filename']]['code'][] = $row['code'];
		}
	}
	$smcFunc['db_free_result']($request);

	$context['page_title'] = $txt['manual_smileys'];
	$context['sub_template'] = 'help_smileys';
}

/**
 * Displays forum rules
 */
function HelpRules()
{
	global $context, $txt, $boarddir, $user_info, $scripturl;

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help',
		'name' => $txt['help'],
	);
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help;sa=rules',
		'name' => $txt['terms_and_rules'],
	);

	// Have we got a localized one?
	if (file_exists($boarddir . '/agreement.' . $user_info['language'] . '.txt'))
		$context['agreement'] = parse_bbc(file_get_contents($boarddir . '/agreement.' . $user_info['language'] . '.txt'), true, 'agreement_' . $user_info['language']);
	elseif (file_exists($boarddir . '/agreement.txt'))
		$context['agreement'] = parse_bbc(file_get_contents($boarddir . '/agreement.txt'), true, 'agreement');
	else
		$context['agreement'] = '';

	// Nothing to show, so let's get out of here
	if (empty($context['agreement']))
	{
		// No file found or a blank file! Just leave...
		redirectexit();
	}

	$context['canonical_url'] = $scripturl . '?action=help;sa=rules';

	$context['page_title'] = $txt['terms_and_rules'];
	$context['sub_template'] = 'help_terms';
}

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
		$helptxt = array();

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
	if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text'], $match))
		$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
}
