<?php

/**
 * Maintains all XML-based interaction (mainly XMLhttp)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

/**
 * The main handler and designator for AJAX stuff - jumpto and previews
 */
function XMLhttpMain()
{
	StoryBB\Template::set_layout('raw');

	$subActions = [
		'jumpto' => 'GetJumpTo',
		'previews' => 'RetrievePreview',
	];

	StoryBB\Template::add_helper([
		'cleanXml' => 'cleanXml',
	]);

	// Easy adding of sub actions.
	routing_integration_hook('integrate_XMLhttpMain_subActions', [&$subActions]);

	if (!isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
		fatal_lang_error('no_access', false);

	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Get a list of boards and categories used for the jumpto dropdown.
 */
function GetJumpTo()
{
	global $context, $sourcedir;

	// Find the boards/categories they can see.
	require_once($sourcedir . '/Subs-MessageIndex.php');
	$boardListOptions = [
		'use_permissions' => true,
		'selected_board' => isset($context['current_board']) ? $context['current_board'] : 0,
	];
	$context['jump_to'] = getBoardList($boardListOptions);

	// Make the board safe for display.
	foreach ($context['jump_to'] as $id_cat => $cat)
	{
		$context['jump_to'][$id_cat]['name'] = un_htmlspecialchars(strip_tags($cat['name']));
		foreach ($cat['boards'] as $id_board => $board)
			$context['jump_to'][$id_cat]['boards'][$id_board]['name'] = un_htmlspecialchars(strip_tags($board['name']));
	}

	StoryBB\Template::set_layout('xml');
	$context['sub_template'] = 'xml_jumpto';
}

/**
 * Handles retrieving previews of news items, newsletters, signatures and warnings.
 * Calls the appropriate function based on $_POST['item']
 * @return void|bool Returns false if $_POST['item'] isn't set or isn't valid
 */
function RetrievePreview()
{
	global $context;

	$items = [
		'sig_preview',
		'warning_preview',
		'char_sheet_preview',
	];

	$context['sub_template'] = 'xml_generic';

	if (!isset($_POST['item']) || !in_array($_POST['item'], $items))
		return false;

	$_POST['item']();
}

/**
 * Handles previewing character sheets.
 */
function char_sheet_preview()
{
	global $context, $sourcedir, $txt, $user_info, $scripturl, $user_profile;

	require_once($sourcedir . '/Subs-Post.php');

	loadLanguage('Profile');

	if (!empty($_POST['user_id']) && !empty($_POST['char_id']))
	{
		$user_id = (int) $_POST['user_id'];
		$char_id = (int) $_POST['char_id'];

		loadMemberData($user_id);
		if (!empty($user_profile[$user_id]['characters'][$char_id]))
		{
			$context['character'] = $user_profile[$user_id]['characters'][$char_id];
		}
	}

	$context['post_error']['sheet'] = [];

	$sheet = $_POST['sheet'] ?? '';
	$sheet = trim(StringLibrary::escape($sheet));

	if (!empty($sheet))
	{
		preparsecode($sheet);
		$context['preview_sheet'] = Parser::parse_bbc($sheet, false);
	}
	else
	{
		$context['error_type'] = true;
		$context['post_error']['sheet'][] = $txt['char_sheet_empty'];
	}

	StoryBB\Template::set_layout('xml');
	$context['sub_template'] = 'xml_sheet_preview';
}

/**
 * Handles previewing signatures
 */
function sig_preview()
{
	global $context, $sourcedir, $smcFunc, $txt, $user_info;

	require_once($sourcedir . '/Profile-Modify.php');
	loadLanguage('Profile');
	loadLanguage('Errors');

	$user = isset($_POST['user']) ? (int) $_POST['user'] : 0;
	$is_owner = $user == $user_info['id'];

	// @todo Temporary
	// Borrowed from loadAttachmentContext in Display.php
	$can_change = $is_owner ? allowedTo(['profile_extra_any', 'profile_extra_own']) : allowedTo('profile_extra_any');

	$errors = [];
	if (!empty($user) && $can_change)
	{
		$request = $smcFunc['db']->query('', '
			SELECT chars.signature
			FROM {db_prefix}members mem
			JOIN {db_prefix}characters chars ON (mem.id_member = chars.id_member AND chars.is_main = 1)
			WHERE mem.id_member = {int:id_member}
			LIMIT 1',
			[
				'id_member' => $user,
			]
		);
		list($current_signature) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
		censorText($current_signature);
		$current_signature = !empty($current_signature) ? Parser::parse_bbc($current_signature, true, 'sig' . $user) : $txt['no_signature_set'];

		$preview_signature = !empty($_POST['signature']) ? $_POST['signature'] : $txt['no_signature_preview'];
		$validation = profileValidateSignature($preview_signature);

		if ($validation !== true && $validation !== false)
			$errors[] = ['value' => $txt['profile_error_' . $validation], 'attributes' => ['type' => 'error']];

		censorText($preview_signature);
		$preview_signature = Parser::parse_bbc($preview_signature, true, 'sig' . $user);
	}
	elseif (!$can_change)
	{
		if ($is_owner)
			$errors[] = ['value' => $txt['cannot_profile_extra_own'], 'attributes' => ['type' => 'error']];
		else
			$errors[] = ['value' => $txt['cannot_profile_extra_any'], 'attributes' => ['type' => 'error']];
	}
	else
		$errors[] = ['value' => $txt['no_user_selected'], 'attributes' => ['type' => 'error']];

	$context['xml_data']['signatures'] = [
			'identifier' => 'signature',
			'children' => []
		];
	if (isset($current_signature))
		$context['xml_data']['signatures']['children'][] = [
					'value' => $current_signature,
					'attributes' => ['type' => 'current'],
				];
	if (isset($preview_signature))
		$context['xml_data']['signatures']['children'][] = [
					'value' => $preview_signature,
					'attributes' => ['type' => 'preview'],
				];
	if (!empty($errors))
		$context['xml_data']['errors'] = [
			'identifier' => 'error',
			'children' => array_merge(
				[
					[
						'value' => $txt['profile_errors_occurred'],
						'attributes' => ['type' => 'errors_occurred'],
					],
				],
				$errors
			),
		];
}

/**
 * Handles previewing user warnings
 */
function warning_preview()
{
	global $context, $sourcedir, $txt, $user_info, $scripturl;

	require_once($sourcedir . '/Subs-Post.php');
	loadLanguage('Errors');
	loadLanguage('ModerationCenter');

	$context['post_error']['messages'] = [];
	if (allowedTo('issue_warning'))
	{
		$warning_body = !empty($_POST['body']) ? trim(censorText($_POST['body'])) : '';
		$context['preview_subject'] = !empty($_POST['title']) ? trim(StringLibrary::escape($_POST['title'])) : '';
		if (isset($_POST['issuing']))
		{
			if (empty($_POST['title']) || empty($_POST['body']))
				$context['post_error']['messages'][] = $txt['warning_notify_blank'];
		}
		else
		{
			if (empty($_POST['title']))
				$context['post_error']['messages'][] = $txt['mc_warning_template_error_no_title'];
			if (empty($_POST['body']))
				$context['post_error']['messages'][] = $txt['mc_warning_template_error_no_body'];
			// Add in few replacements.
			/**
			* These are the defaults:
			* - {MEMBER} - Member Name. => current user for review
			* - {MESSAGE} - Link to Offending Post. (If Applicable) => not applicable here, so not replaced
			* - {FORUMNAME} - Forum Name.
			* - {SCRIPTURL} - Web address of forum.
			* - {REGARDS} - Standard email sign-off.
			*/
			$find = [
				'{MEMBER}',
				'{FORUMNAME}',
				'{SCRIPTURL}',
				'{REGARDS}',
			];
			$replace = [
				$user_info['name'],
				$context['forum_name'],
				$scripturl,
				str_replace('{forum_name}', $context['forum_name'], $txt['regards_team']),
			];
			$warning_body = str_replace($find, $replace, $warning_body);
		}

		if (!empty($_POST['body']))
		{
			preparsecode($warning_body);
			$warning_body = Parser::parse_bbc($warning_body, true);
		}
		$context['preview_message'] = $warning_body;
	}
	else
		$context['post_error']['messages'][] = ['value' => $txt['cannot_issue_warning'], 'attributes' => ['type' => 'error']];

	StoryBB\Template::set_layout('xml');
	$context['sub_template'] = 'xml_warning_preview';
}
