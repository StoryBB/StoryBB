<?php

/**
 * This represents the current user.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\User;

use RuntimeException;
use StoryBB\Dependency\Database;

class CurrentUser
{
	const STATE_UNACTIVATED = 0;
	const STATE_ACTIVATED = 1;
	const STATE_PENDING_REACTIVATION = 2;
	const STATE_PENDING_ADMIN_ACTIVATION = 3;
	const STATE_PENDING_DELETION = 4;

	const STATE_BANNED = 10;

	const GROUP_GUEST = -1;
	const GROUP_UNALLOCATED = 0;
	const GROUP_ADMIN = 1;

	use Database;

	protected $user_data;

	public function load_user(int $userid)
	{
		$db = $this->db();

		if ($userid)
		{
			$request = $db->query('', '
				SELECT mem.*, chars.id_character, chars.character_name, chars.signature AS char_signature,
					chars.id_theme AS char_theme, chars.is_main, chars.main_char_group, chars.char_groups, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, mainchar.avatar AS char_avatar
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}characters AS chars ON (chars.id_character = mem.current_character)
					LEFT JOIN {db_prefix}characters AS mainchar ON (mainchar.id_member = mem.id_member AND mainchar.is_main = 1)
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = mainchar.id_character AND a.attachment_type = 1)
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				[
					'id_member' => $userid,
				]
			);
			$user_data = $db->fetch_assoc($request);
			$db->free_result($request);
		}

		if (!empty($user_data))
		{
			$this->user_data = $user_data;
			$this->user_data['authenticated'] = true;

			$this->user_data['groups'] = array_merge([$this->user_data['id_group']], explode(',', $this->user_data['additional_groups']));
			$this->user_data['groups'] = array_unique(array_map('intval', $this->user_data['groups']));
		}
		else
		{
			$this->user_data = [
				'authenticated' => false,
				'groups' => [self::GROUP_GUEST],
				'time_offset' => 0,
			];
		}

		$GLOBALS['user_settings'] = $this->user_data; // @todo Dirty legacy hack.
	}

	public function is_authenticated(): bool
	{
		return $this->user_data['authenticated'];
	}

	public function is_activated(): bool
	{
		return in_array($this->user_data['is_activated'], [self::STATE_ACTIVATED, self::STATE_BANNED + self::STATE_ACTIVATED]);
	}

	public function get_groups(): array
	{
		return $this->user_data['groups'];
	}

	public function is_site_admin(): bool
	{
		return in_array(self::GROUP_ADMIN, $this->user_data['groups']);
	}
}