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

use StoryBB\Container;

class DeleteAccount extends AbstractProfileController
{
	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-da%u');
	}

	public function display_action()
	{
		global $txt, $context, $modSettings, $cur_profile;

		if (!$context['user']['is_owner'])
			isAllowedTo('profile_remove_any');
		elseif (!allowedTo('profile_remove_any'))
			isAllowedTo('profile_remove_own');

		createToken($this->get_token_name(), 'post');
		$context['token_check'] = $this->get_token_name();

		$url = App::container()->get('urlgenerator');

		// Permissions for removing stuff...
		$context['can_delete_posts'] = !$context['user']['is_owner'] && allowedTo('moderate_forum');

		// Show an extra option if recycling is enabled...
		$context['show_perma_delete'] = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']);

		// Can they do this, or will they need approval?
		$context['needs_approval'] = $context['user']['is_owner'] && !allowedTo('moderate_forum');
		$context['page_title'] = $txt['deleteAccount'] . ': ' . $cur_profile['real_name'];
		$context['sub_template'] = 'profile_delete';
		$context['delete_account_posts_advice'] = sprintf($txt['delete_account_posts_advice'], $url->generate('contact'));
	}

	public function post_action()
	{
		global $user_info, $sourcedir, $context, $cur_profile, $modSettings, $smcFunc;

		validateToken($this->get_token_name());

		$memID = $this->params['u'];

		// Try get more time...
		@set_time_limit(600);

		// @todo Add a way to delete pms as well?

		if (!$context['user']['is_owner'])
			isAllowedTo('profile_remove_any');
		elseif (!allowedTo('profile_remove_any'))
			isAllowedTo('profile_remove_own');

		$old_profile = &$cur_profile;

		// Too often, people remove/delete their own only account.
		if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
		{
			// Are you allowed to administrate the forum, as they are?
			isAllowedTo('admin_forum');

			$request = $smcFunc['db']->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
					AND id_member != {int:selected_member}
				LIMIT 1',
				[
					'admin_group' => 1,
					'selected_member' => $memID,
				]
			);
			list ($another) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			if (empty($another))
				fatal_lang_error('at_least_one_admin', 'critical');
		}

		// This file is needed for the deleteMembers function.
		require_once($sourcedir . '/Subs-Members.php');

		// Do you have permission to delete others profiles, or is that your profile you wanna delete?
		if ($memID != $user_info['id'])
		{
			isAllowedTo('profile_remove_any');

			// Before we go any further, handle possible poll vote deletion as well
			if (!empty($_POST['deleteVotes']) && allowedTo('moderate_forum'))
			{
				// First we find any polls that this user has voted in...
				$get_voted_polls = $smcFunc['db']->query('', '
					SELECT DISTINCT id_poll
					FROM {db_prefix}log_polls
					WHERE id_member = {int:selected_member}',
					[
						'selected_member' => $memID,
					]
				);

				$polls_to_update = [];

				while ($row = $smcFunc['db']->fetch_assoc($get_voted_polls))
				{
					$polls_to_update[] = $row['id_poll'];
				}

				$smcFunc['db']->free_result($get_voted_polls);

				// Now we delete the votes and update the polls
				if (!empty($polls_to_update))
				{
					$smcFunc['db']->query('', '
						DELETE FROM {db_prefix}log_polls
						WHERE id_member = {int:selected_member}',
						[
							'selected_member' => $memID,
						]
					);

					$smcFunc['db']->query('', '
						UPDATE {db_prefix}polls
						SET votes = votes - 1
						WHERE id_poll IN {array_int:polls_to_update}',
						[
							'polls_to_update' => $polls_to_update
						]
					);
				}
			}

			// Now, have you been naughty and need your posts deleting?
			$topicIDs = [];
			$msgIDs = [];

			// Include RemoveTopics - essential for this type of work!
			require_once($sourcedir . '/RemoveTopic.php');

			$extra = empty($_POST['perma_delete']) ? ' AND t.id_board != {int:recycle_board}' : '';
			$recycle_board = empty($modSettings['recycle_board']) ? 0 : $modSettings['recycle_board'];

			if (allowedTo('moderate_forum'))
			{
				// Identify the OOC account in here.
				$ooc_account = 0;
				foreach ($context['member']['characters'] as $charID => $char)
				{
					if ($char['is_main'])
					{
						$ooc_account = $charID;
						break;
					}
				}

				// Find all the topics started by the OOC character.
				if (!empty($_POST['deleteTopics_ooc']) && $ooc_account)
				{
					// Fetch all topics started by this user.
					$request = $smcFunc['db']->query('', '
						SELECT t.id_topic
						FROM {db_prefix}topics AS t
							INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
						WHERE t.id_member_started = {int:selected_member}
							AND m.id_character = {int:ooc_character}' . $extra,
						[
							'selected_member' => $memID,
							'ooc_character' => $ooc_account,
							'recycle_board' => $recycle_board,
						]
					);
					$topicIDs = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
						$topicIDs[] = $row['id_topic'];
					$smcFunc['db']->free_result($request);
				}
				// And all the IC topics.
				if (!empty($_POST['deleteTopics_ic']) && $ooc_account)
				{
					// Fetch all topics started by this user.
					$request = $smcFunc['db']->query('', '
						SELECT t.id_topic
						FROM {db_prefix}topics AS t
							INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
						WHERE t.id_member_started = {int:selected_member}
							AND m.id_character != {int:ooc_character}' . $extra,
						[
							'selected_member' => $memID,
							'ooc_character' => $ooc_account,
							'recycle_board' => $recycle_board,
						]
					);
					$topicIDs = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
					{
						$topicIDs[] = $row['id_topic'];
					}
					$smcFunc['db']->free_result($request);
				}

				// Find all the messages by the OOC character that aren't in the topics we already found.
				if (!empty($_POST['deletePosts_ooc']))
				{
					$request = $smcFunc['db']->query('', '
						SELECT m.id_msg
						FROM {db_prefix}messages AS m
							INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic
								AND t.id_first_msg != m.id_msg)
						WHERE m.id_member = {int:selected_member}
							AND m.id_character = {int:ooc_character}' . (!empty($topicIDs) ? '
							AND t.id_topic NOT IN ({array:topics})' : '') . $extra,
						[
							'selected_member' => $memID,
							'ooc_character' => $ooc_account,
							'topics' => $topicIDs,
							'recycle_board' => $recycle_board,
						]
					);
					// This could take a while... but ya know it's gonna be worth it in the end.
					while ($row = $smcFunc['db']->fetch_assoc($request))
					{
						$msgIDs[] = $row['id_msg'];
					}
					$smcFunc['db']->free_result($request);
				}
				// Find all the IC posts next.
				if (!empty($_POST['deletePosts_ic']))
				{
					$request = $smcFunc['db']->query('', '
						SELECT m.id_msg
						FROM {db_prefix}messages AS m
							INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic
								AND t.id_first_msg != m.id_msg)
						WHERE m.id_member = {int:selected_member}
							AND m.id_character != {int:ooc_character}' . (!empty($topicIDs) ? '
							AND t.id_topic NOT IN ({array:topics})' : '') . $extra,
						[
							'selected_member' => $memID,
							'ooc_character' => $ooc_account,
							'topics' => $topicIDs,
							'recycle_board' => $recycle_board,
						]
					);
					// This could take a while... but ya know it's gonna be worth it in the end.
					while ($row = $smcFunc['db']->fetch_assoc($request))
					{
						$msgIDs[] = $row['id_msg'];
					}
					$smcFunc['db']->free_result($request);
				}

				if (!empty($topicIDs))
				{
					// Actually remove the topics. Ignore recycling if we want to perma-delete things...
					// @todo This needs to check permissions, but we'll let it slide for now because of moderate_forum already being had.
					removeTopics($topicIDs, true, !empty($extra));
				}

				if (!empty($msgIDs))
				{
					foreach ($msgIDs as $id_msg)
					{
						if (function_exists('apache_reset_timeout'))
							@apache_reset_timeout();

						removeMessage($id_msg);
					}
				}
			}

			// Only delete this poor members account if they are actually being booted out of camp.
			if (isset($_POST['deleteAccount']))
				deleteMembers($memID);
		}
		// They need approval to delete!
		elseif (!allowedTo('moderate_forum'))
		{
			// Setup their account for deletion ;)
			updateMemberData($memID, ['is_activated' => 4]);
			// Another account needs approval...
			updateSettings(['unapprovedMembers' => true], true);
		}
		// Also check if you typed your password correctly.
		else
		{
			deleteMembers($memID);

			$container = Container::instance();
			$container->get('session')->invalidate();
			$persist_cookie = App::get_global_config_item('cookiename') . '_persist';
			if (isset($_COOKIE[$persist_cookie]))
			{
				$persistence = $container->instantiate('StoryBB\\Session\\Persistence');
				$persistence->invalidate_persist_token($_COOKIE[$persist_cookie]);
				setcookie($persist_cookie, "", -3600);
			};
		}

		redirectexit();
	}
}
