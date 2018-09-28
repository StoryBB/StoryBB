<?php

/**
 * This class handles the main database schema for StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Schema;
use StoryBB\Schema\Table;
use StoryBB\Schema\Column;
use StoryBB\Schema\Index;

/**
 * This class handles the main database schema for StoryBB.
 */
class Schema
{
	/**
	 * Returns all the tables in core StoryBB, without prefixes.
	 *
	 * @return array An array of Table instances representing the schema.
	 */
	public static function get_tables(): array
	{
		return [
			'admin_info_files' => [],
			'approval_queue' => [],
			'attachments' => [],
			'background_tasks' => [],
			'ban_groups' => [],
			'ban_items' => [],
			'board_permissions' => [],
			'boards' => [],
			'categories' => [],
			'characters' => [],
			'character_sheet_comments' => [],
			'character_sheet_templates' => [],
			'character_sheet_versions' => [],
			'contact_form' => [],
			'contact_form_response' => [],
			'custom_fields' => [],
			'group_moderators' => [],
			'log_actions' => [],
			'log_activity' => [],
			'log_banned' => [],
			'log_boards' => [],
			'log_comments' => [],
			'log_digest' => [],
			'log_errors' => [],
			'log_floodcontrol' => [],
			'log_group_requests' => [],
			'log_mark_read' => [],
			'log_member_notices' => [],
			'log_notify' => [],
			'log_online' => [],
			'log_polls' => [],
			'log_reported' => [],
			'log_reported_comments' => [],
			'log_scheduled_tasks' => [],
			'log_seearch_messages' => [],
			'log_search_results' => [],
			'log_search_subjects' => [],
			'log_search_topics' => [],
			'log_subscribed' => [],
			'log_topics' => [],
			'mail_queue' => [],
			'membergroups' => [],
			'members' => [],
			'member_logins' => [],
			'mentions' => [],
			'message_icons' => [],
			'messages' => [],
			'moderators' => [],
			'moderator_groups' => [],
			'permission_profiles' => [],
			'permissions' => [],
			'personal_messages' => [],
			'pm_labels' => [],
			'pm_labeled_messages' => [],
			'pm_recipients' => [],
			'pm_rules' => [],
			'policy' => [],
			'policy_acceptance' => [],
			'policy_revision' => [],
			'policy_types' => [],
			'polls' => [],
			'poll_choices' => [],
			'qanda' => [],
			'scheduled_tasks' => [],
			'settings' => [],
			'sessions' => [],
			'smileys' => [],
			'subscriptions' => [],
			'themes' => [],
			'topics' => [],
			'user_alerts' => [],
			'user_alerts_prefs' => [],
			'user_drafts' => [],
			'user_exports' => [],
			'user_likes' => [],
		];
	}
}
