<?php

/**
 * Handle persisting user logged-in state beyond session lifetime.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Session;

use StoryBB\Database\DatabaseAdapter;
use StoryBB\Dependency\Database;
use StoryBB\Helper\Random;

class Persistence
{
	use Database;

	const TOKEN_LENGTH = 32;

	/**
	 * Creates a new token for the user that they can use to be remembered by.
	 *
	 * @param int $userid The user ID to create a token for.
	 * @return string The persistence token.
	 */
	public function create_for_user(int $userid): string
	{
		$hash = Random::get_random_bytes(static::TOKEN_LENGTH);

		$this->db()->insert(DatabaseAdapter::INSERT_INSERT,
			'{db_prefix}sessions_persist',
			['id_member' => 'int', 'persist_key' => 'binary', 'timecreated' => 'int', 'timeexpires' => 'int'],
			[$userid, $hash, time(), strtotime('+1 month')],
			['id_persist'],
			DatabaseAdapter::RETURN_NOTHING
		);

		return $hash;
	}

	/**
	 * Authenticate the user using the token.
	 *
	 * @param string $token
	 * @return int $user User ID; 0 if no match.
	 */
	public function validate_persist_token(string $token): int
	{
		if (strpos($token, ':') === false)
		{
			// If the token is the wrong format, get rid.
			return 0;
		}

		list ($userid, $hash) = explode(':', $token, 2);
		$hash = @base64_decode($hash);

		if (!is_numeric($userid) || strlen($hash) !== static::TOKEN_LENGTH)
		{
			// Doesn't match what we know it should contain.
			return 0;
		}

		$db = $this->db();
		$result = $db->query('', '
			SELECT id_member
			FROM {db_prefix}sessions_persist
			WHERE id_member = {int:id_member}
				AND persist_key = {binary:hash}
				AND timecreated < {int:time}
				AND timeexpires >= {int:time}',
			[
				'id_member' => $userid,
				'hash' => $hash,
				'time' => time(),
			]
		);

		if ($row = $db->fetch_assoc($result))
		{
			$row['id_member'] = (int) $row['id_member'];
		}

		$db->free_result($result);

		return !empty($row['id_member']) ? $row['id_member'] : 0;
	}
}
