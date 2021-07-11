<?php

/**
 * A class for managing the user preferences.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\User;

use StoryBB\Dependency\Database;

class PreferenceManager
{
	use Database;

	protected static $cache = [];

	protected function empty_cache(): void
	{
		self::$cache = [];
	}

	protected function fill_cache($userid): void
	{
		if (isset(self::$cache[$userid]))
		{
			return;
		}

		$db = $this->db();
		if (!isset(self::$cache[0]))
		{
			self::$cache[0] = [];
		}
		self::$cache[$userid] = [];

		$result = $db->query('', '
			SELECT id_member, preference, value
			FROM {db_prefix}user_preferences
			WHERE id_member IN ({int:guest}, {int:user})',
			[
				'guest' => 0,
				'user' => $userid,
			]
		);
		while ($row = $db->fetch_assoc($result))
		{
			self::$cache[$row['id_member']][$row['preference']] = $row['value'];
		}
		$db->free_result($result);
	}

	public function get_preferences_for_user(int $userid): array
	{
		$this->fill_cache($userid);

		return self::$cache[$userid] + self::$cache[0];
	}

	public function get_preference(int $userid, string $preference)
	{
		$this->fill_cache($userid);

		if (isset(self::$cache[$userid][$preference]))
		{
			return self::$cache[$userid][$preference];
		}

		if (isset(self::$cache[0][$preference]))
		{
			return self::$cache[0][$preference];
		}

		return null;
	}

	public function save_preferences(int $userid, array $preferences): void
	{
		// First, remove these preferences from the database if we have them.
		$this->fill_cache($userid);

		// Make sure that we're not setting variables that aren't different to what we already have.
		foreach ($preferences as $key => $value)
		{
			if (isset(self::$cache[$userid][$key]) && self::$cache[$userid][$key] == $value)
			{
				unset ($preferences[$key]);
			}
		}

		if (empty($preferences))
		{
			return;
		}

		$db = $this->db();
		$db->query('', '
			DELETE FROM {db_prefix}user_preferences
			WHERE id_member = {int:userid}
				AND preference IN ({array_string:prefs})',
			[
				'userid' => $userid,
				'prefs' => array_keys($preferences),
			]
		);

		$insert = [];
		foreach ($preferences as $pref_key => $pref_value)
		{
			$insert[] = [
				$userid, $pref_key, $pref_value,
			];
		}

		$db->insert($db::INSERT_INSERT,
			'{db_prefix}user_preferences',
			['id_member' => 'int', 'preference' => 'string', 'value' => 'string'],
			$insert,
			['id_preference'],
			$db::RETURN_NOTHING
		);

		$this->empty_cache();
		$this->fill_cache($userid);
	}

	public function get_default_preferences(): array
	{
		$preferences = [
			'theme_opt_display',
			[
				'id' => 'topics_per_page',
				'label' => 'topics_per_page',
				'options' => [
					0 => 'per_page_default',
					5 => 5,
					10 => 10,
					25 => 25,
					50 => 50,
				],
				'disableOn' => 'disableCustomPerPage',
			],
			[
				'id' => 'messages_per_page',
				'label' => 'messages_per_page',
				'options' => [
					0 => 'per_page_default',
					5 => 5,
					10 => 10,
					25 => 25,
					50 => 50,
				],
				'disableOn' => 'disableCustomPerPage',
			],
			[
				'id' => 'view_newest_first',
				'label' => 'recent_posts_at_top',
			],
			[
				'id' => 'show_avatars',
				'label' => 'show_avatars',
			],
			[
				'id' => 'show_signatures',
				'label' => 'show_signatures',
			],
			[
				'id' => 'posts_apply_ignore_list',
				'label' => 'posts_apply_ignore_list',
			],
			'theme_opt_posting',
			[
				'id' => 'return_to_post',
				'label' => 'return_to_post',
			],
			[
				'id' => 'auto_notify',
				'label' => 'auto_notify',
			],
			[
				'id' => 'wysiwyg_default',
				'label' => 'wysiwyg_default',
			],
			'theme_opt_personal_messages',
			[
				'id' => 'view_newest_pm_first',
				'label' => 'recent_pms_at_top',
			],
			[
				'id' => 'pm_remove_inbox_label',
				'label' => 'pm_remove_inbox_label',
			],
		];

		return $preferences;
	}
}
