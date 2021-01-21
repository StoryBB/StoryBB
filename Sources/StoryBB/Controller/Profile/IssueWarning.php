<?php

/**
 * Displays the issue-warning page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

class IssueWarning extends AbstractProfileController
{
	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-iw%u');
	}

	public function display_action($from_saving = false)
	{
		global $txt, $scripturl, $modSettings, $user_info;
		global $context, $cur_profile, $smcFunc, $sourcedir;

		// Get all the actual settings.
		list ($modSettings['warning_enable'], $modSettings['user_limit']) = explode(',', $modSettings['warning_settings']);

		$memID = $this->params['u'];

		createToken($this->get_token_name(), 'post');

		$context['token_check'] = $this->get_token_name();

		// This stores any legitimate errors.
		$issueErrors = [];

		// Doesn't hurt to be overly cautious.
		if (empty($modSettings['warning_enable']) || ($context['user']['is_owner'] && !$cur_profile['warning']) || !allowedTo('issue_warning'))
			fatal_lang_error('no_access', false);

		// Get the base (errors related) stuff done.
		loadLanguage('Errors');
		$context['custom_error_title'] = $txt['profile_warning_errors_occured'];

		// Make sure things which are disabled stay disabled.
		$modSettings['warning_watch'] = !empty($modSettings['warning_watch']) ? $modSettings['warning_watch'] : 110;
		$modSettings['warning_moderate'] = !empty($modSettings['warning_moderate']) ? $modSettings['warning_moderate'] : 110;
		$modSettings['warning_mute'] = !empty($modSettings['warning_mute']) ? $modSettings['warning_mute'] : 110;

		$context['warning_limit'] = allowedTo('admin_forum') ? 0 : $modSettings['user_limit'];
		$context['member']['warning'] = $cur_profile['warning'];
		$context['member']['name'] = $cur_profile['real_name'];

		// What are the limits we can apply?
		$context['min_allowed'] = 0;
		$context['max_allowed'] = 100;
		if ($context['warning_limit'] > 0)
		{
			// Make sure we cannot go outside of our limit for the day.
			$request = $smcFunc['db']->query('', '
				SELECT SUM(counter)
				FROM {db_prefix}log_comments
				WHERE id_recipient = {int:selected_member}
					AND id_member = {int:current_member}
					AND comment_type = {string:warning}
					AND log_time > {int:day_time_period}',
				[
					'current_member' => $user_info['id'],
					'selected_member' => $memID,
					'day_time_period' => time() - 86400,
					'warning' => 'warning',
				]
			);
			list ($current_applied) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			$context['min_allowed'] = max(0, $cur_profile['warning'] - $current_applied - $context['warning_limit']);
			$context['max_allowed'] = min(100, $cur_profile['warning'] - $current_applied + $context['warning_limit']);
		}

		// Defaults.
		$context['warning_data'] = [
			'reason' => '',
			'notify' => '',
			'notify_subject' => '',
			'notify_body' => '',
		];

		if (!$from_saving)
		{
			$this->load_warning_list();
		}

		$context['page_title'] = $txt['profile_issue_warning'];

		// Are they warning because of a message?
		if (isset($_REQUEST['msg']) && 0 < (int) $_REQUEST['msg'])
		{
			$request = $smcFunc['db']->query('', '
				SELECT subject
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				WHERE id_msg = {int:message}
					AND {query_see_board}
				LIMIT 1',
				[
					'message' => (int) $_REQUEST['msg'],
				]
			);
			if ($smcFunc['db']->num_rows($request) != 0)
			{
				$context['warning_for_message'] = (int) $_REQUEST['msg'];
				list ($context['warned_message_subject']) = $smcFunc['db']->fetch_row($request);
			}
			$smcFunc['db']->free_result($request);

		}

		// Didn't find the message?
		if (empty($context['warning_for_message']))
		{
			$context['warning_for_message'] = 0;
			$context['warned_message_subject'] = '';
		}

		// Any custom templates?
		$context['notification_templates'] = [];

		$request = $smcFunc['db']->query('', '
			SELECT recipient_name AS template_title, body
			FROM {db_prefix}log_comments
			WHERE comment_type = {literal:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			[
				'generic' => 0,
				'current_member' => $user_info['id'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// If we're not warning for a message skip any that are.
			if (!$context['warning_for_message'] && strpos($row['body'], '{MESSAGE}') !== false)
				continue;

			$context['notification_templates'][] = [
				'title' => $row['template_title'],
				'body' => $row['body'],
			];
		}
		$smcFunc['db']->free_result($request);

		// Setup the "default" templates.
		foreach (['spamming', 'offence', 'insulting'] as $type)
			$context['notification_templates'][] = [
				'title' => $txt['profile_warning_notify_title_' . $type],
				'body' => sprintf($txt['profile_warning_notify_template_outline' . (!empty($context['warning_for_message']) ? '_post' : '')], $txt['profile_warning_notify_for_' . $type]),
			];

		// Replace all the common variables in the templates.
		foreach ($context['notification_templates'] as $k => $name)
		{
			$context['notification_templates'][$k]['body'] = strtr($name['body'], [
				'{MEMBER}' => un_htmlspecialchars($context['member']['name']),
				'{MESSAGE}' => '[url=' . $scripturl . '?msg=' . $context['warning_for_message'] . ']' . un_htmlspecialchars($context['warned_message_subject']) . '[/url]',
				'{SCRIPTURL}' => $scripturl,
				'{FORUMNAME}' => $context['forum_name'],
				'{REGARDS}' => str_replace('{forum_name}', $context['forum_name'], $txt['regards_team']),
			]);
		}

		$context['sub_template'] = 'profile_warning_issue';
	}

	public function post_action()
	{
		global $txt, $scripturl, $modSettings, $user_info;
		global $context, $cur_profile, $smcFunc, $sourcedir;

		validateToken($this->get_token_name());

		$memID = $this->params['u'];

		// Reuse all the setup from the display action, which is all reusable.
		$this->display_action(true);

		// Are we saving?
		if (isset($_POST['save']))
		{
			// This cannot be empty!
			$_POST['warn_reason'] = isset($_POST['warn_reason']) ? trim($_POST['warn_reason']) : '';
			if ($_POST['warn_reason'] == '' && !$context['user']['is_owner'])
				$issueErrors[] = 'warning_no_reason';
			$_POST['warn_reason'] = StringLibrary::escape($_POST['warn_reason']);

			$_POST['warning_level'] = (int) $_POST['warning_level'];
			$_POST['warning_level'] = max(0, min(100, $_POST['warning_level']));
			if ($_POST['warning_level'] < $context['min_allowed'])
				$_POST['warning_level'] = $context['min_allowed'];
			elseif ($_POST['warning_level'] > $context['max_allowed'])
				$_POST['warning_level'] = $context['max_allowed'];

			// Do we actually have to issue them with a PM?
			$id_notice = 0;
			if (!empty($_POST['warn_notify']) && empty($issueErrors))
			{
				$_POST['warn_sub'] = trim($_POST['warn_sub']);
				$_POST['warn_body'] = trim($_POST['warn_body']);
				if (empty($_POST['warn_sub']) || empty($_POST['warn_body']))
					$issueErrors[] = 'warning_notify_blank';
				// Send the PM?
				else
				{
					require_once($sourcedir . '/Subs-Post.php');
					$from = [
						'id' => 0,
						'name' => $context['forum_name_html_safe'],
						'username' => $context['forum_name_html_safe'],
					];
					sendpm(['to' => [$memID], 'bcc' => []], $_POST['warn_sub'], $_POST['warn_body'], false, $from);

					// Log the notice!
					$id_notice = $smcFunc['db']->insert('',
						'{db_prefix}log_member_notices',
						[
							'subject' => 'string-255', 'body' => 'string-65534',
						],
						[
							StringLibrary::escape($_POST['warn_sub']), StringLibrary::escape($_POST['warn_body']),
						],
						['id_notice'],
						1
					);
				}
			}

			// Just in case - make sure notice is valid!
			$id_notice = (int) $id_notice;

			// What have we changed?
			$level_change = $_POST['warning_level'] - $cur_profile['warning'];

			// No errors? Proceed! Only log if you're not the owner.
			if (empty($issueErrors))
			{
				// Log what we've done!
				if (!$context['user']['is_owner'])
					$smcFunc['db']->insert('',
						'{db_prefix}log_comments',
						[
							'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int', 'recipient_name' => 'string-255',
							'log_time' => 'int', 'id_notice' => 'int', 'counter' => 'int', 'body' => 'string-65534',
						],
						[
							$user_info['id'], $user_info['name'], 'warning', $memID, $cur_profile['real_name'],
							time(), $id_notice, $level_change, $_POST['warn_reason'],
						],
						['id_comment']
					);

				// Make the change.
				updateMemberData($memID, ['warning' => $_POST['warning_level']]);

				// Leave a lovely message.
				session_flash('success', $context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_warning_success']);
			}
			else
			{
				// Try to remember some bits.
				$context['warning_data'] = [
					'reason' => $_POST['warn_reason'],
					'notify' => !empty($_POST['warn_notify']),
					'notify_subject' => isset($_POST['warn_sub']) ? $_POST['warn_sub'] : '',
					'notify_body' => isset($_POST['warn_body']) ? $_POST['warn_body'] : '',
				];
			}

			// Show the new improved warning level.
			$context['member']['warning'] = $_POST['warning_level'];
		}

		if (isset($_POST['preview']))
		{
			$warning_body = !empty($_POST['warn_body']) ? trim(censorText($_POST['warn_body'])) : '';
			$context['preview_subject'] = !empty($_POST['warn_sub']) ? trim(StringLibrary::escape($_POST['warn_sub'])) : '';
			if (empty($_POST['warn_sub']) || empty($_POST['warn_body']))
				$issueErrors[] = 'warning_notify_blank';

			if (!empty($_POST['warn_body']))
			{
				require_once($sourcedir . '/Subs-Post.php');

				preparsecode($warning_body);
				$warning_body = Parser::parse_bbc($warning_body, true);
			}

			// Try to remember some bits.
			$context['warning_data'] = [
				'reason' => $_POST['warn_reason'],
				'notify' => !empty($_POST['warn_notify']),
				'notify_subject' => isset($_POST['warn_sub']) ? $_POST['warn_sub'] : '',
				'notify_body' => isset($_POST['warn_body']) ? $_POST['warn_body'] : '',
				'body_preview' => $warning_body,
			];
		}

		if (!empty($issueErrors))
		{
			// Fill in the suite of errors.
			$context['post_errors'] = [];
			foreach ($issueErrors as $error)
				$context['post_errors'][] = $txt[$error];
		}

		$this->load_warning_list();
	}

	public function load_warning_list()
	{
		global $context, $sourcedir, $txt, $modSettings, $scripturl;

		$memID = $this->params['u'];

		loadLanguage('ModerationCenter');

		// Let's use a generic list to get all the current warnings
		require_once($sourcedir . '/Subs-List.php');

		// Work our the various levels.
		$context['level_effects'] = [
			0 => $txt['profile_warning_effect_none'],
			$modSettings['warning_watch'] => $txt['profile_warning_effect_watch'],
			$modSettings['warning_moderate'] => $txt['profile_warning_effect_moderation'],
			$modSettings['warning_mute'] => $txt['profile_warning_effect_mute'],
		];
		$context['current_level'] = 0;
		foreach ($context['level_effects'] as $limit => $dummy)
			if ($context['member']['warning'] >= $limit)
				$context['current_level'] = $limit;
		$context['current_level_effects'] = $context['level_effects'][$context['current_level']];

		$listOptions = [
			'id' => 'view_warnings',
			'title' => $txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => $modSettings['defaultMaxListItems'],
			'no_items_label' => $txt['profile_viewwarning_no_warnings'],
			'base_href' => $scripturl . '?action=profile;area=issue_warning;sa=user;u=' . $memID,
			'default_sort_col' => 'log_time',
			'get_items' => [
				'function' => ['StoryBB\\Helper\\Warning', 'list_getUserWarnings'],
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => ['StoryBB\\Helper\\Warning', 'list_getUserWarningCount'],
				'params' => [
					$memID,
				],
			],
			'columns' => [
				'issued_by' => [
					'header' => [
						'value' => $txt['profile_warning_previous_issued'],
						'style' => 'width: 20%;',
					],
					'data' => [
						'function' => function($warning)
						{
							return $warning['issuer']['link'];
						},
					],
					'sort' => [
						'default' => 'lc.member_name DESC',
						'reverse' => 'lc.member_name',
					],
				],
				'log_time' => [
					'header' => [
						'value' => $txt['profile_warning_previous_time'],
						'style' => 'width: 30%;',
					],
					'data' => [
						'db' => 'time',
					],
					'sort' => [
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					],
				],
				'reason' => [
					'header' => [
						'value' => $txt['profile_warning_previous_reason'],
					],
					'data' => [
						'function' => function($warning) use ($scripturl, $txt)
						{
							$ret = '
							<div class="floatleft">
								' . $warning['reason'] . '
							</div>';

							if (!empty($warning['id_notice']))
								$ret .= '
							<div class="floatright">
								<a href="' . $scripturl . '?action=moderate;area=notice;nid=' . $warning['id_notice'] . '" onclick="return reqOverlayDiv(this.href, \'' . $txt['show_notice'] . '\', \'warn.png\');" target="_blank" rel="noopener" title="' . $txt['profile_warning_previous_notice'] . '" class="main_icons filter centericon"></a>
							</div>';

							return $ret;
						},
					],
				],
				'level' => [
					'header' => [
						'value' => $txt['profile_warning_previous_level'],
						'style' => 'width: 6%;',
					],
					'data' => [
						'db' => 'counter',
					],
					'sort' => [
						'default' => 'lc.counter DESC',
						'reverse' => 'lc.counter',
					],
				],
			],
		];

		// Create the list for viewing.
		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);
	}
}
