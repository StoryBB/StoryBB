<?php

/**
 * Supporting features for integrations.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Integratable;

use InvalidArgumentException;

trait CharacterDetails
{
	protected function get_post_owner(int $msgid): ?array
	{
		global $smcFunc;

		$return = null;
		$request = $smcFunc['db']->query('', '
			SELECT id_member, id_character
			FROM {db_prefix}messages
			WHERE id_msg = {int:msgid}',
			[
				'msgid' => $msgid,
			]
		);
		if ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$return = $row;
		}
		$smcFunc['db']->free_result($request);

		return $return;
	}

	protected function get_character_details(int $account_id, int $character_id): array
	{
		global $user_profile, $scripturl, $modSettings, $smcFunc;
		static $images_url;

		$return = [
			'username' => null,
			'avatar' => null,
			'url' => null,
		];

		if ($account_id)
		{
			if (loadMemberData($account_id))
			{
				if (isset($user_profile[$account_id]['characters'][$character_id]))
				{
					$return['username'] = html_entity_decode($user_profile[$account_id]['characters'][$character_id]['character_name'], ENT_QUOTES, 'UTF-8');
					if (!empty($user_profile[$account_id]['characters'][$character_id]['avatar']))
					{
						$return['avatar'] = $user_profile[$account_id]['characters'][$character_id]['avatar'];
					}

					if (!empty($user_profile[$account_id]['characters'][$character_id]['is_main']))
					{
						$return['url'] = $scripturl . '?action=profile;u=' . $account_id;
					}
					else
					{
						$return['url'] = $scripturl . '?action=profile;u=' . $account_id . ';area=characters;char=' . $character_id;
					}
				}
			}
		}

		if (stripos($return['avatar'], '{IMAGES_URL}') !== false)
		{
			if (empty($images_url))
			{
				$result = $smcFunc['db']->query('', '
					SELECT value
					FROM {db_prefix}themes
					WHERE id_member = 0
						AND id_theme = {int:guest_theme}
						AND variable = {literal:images_url}',
					[
						'guest_theme' => $modSettings['theme_guests'],
					]
				);
				$row = $smcFunc['db']->fetch_assoc($result);
				if ($row)
				{
					$images_url = $row['value'];
				}
				$smcFunc['db']->free_result($result);
			}

			$return['avatar'] = str_ireplace('{IMAGES_URL}', $images_url, $return['avatar']);
		}

		return $return;
	}
}
