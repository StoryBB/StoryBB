<?php

/**
 * Handle persisting user logged-in state beyond session lifetime.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Session;

use StoryBB\App;
use StoryBB\Database\DatabaseAdapter;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\SiteSettings;
use StoryBB\Helper\Cookie as CookieHelper;
use StoryBB\Helper\Random;
use Symfony\Component\HttpFoundation\Cookie;

class Persistence
{
	use Database;
	use SiteSettings;

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
			SELECT id_persist, id_member
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

			// Extend the lifetime of this token for another month.
			$db->query('', '
				UPDATE {db_prefix}sessions_persist
				SET timeexpires = {int:expires}
				WHERE id_persist = {int:persist}',
				[
					'persist' => $row['id_persist'],
					'expires' => strtotime('+1 month'),
				]
			);
		}

		$db->free_result($result);

		return !empty($row['id_member']) ? $row['id_member'] : 0;
	}

	public function invalidate_persist_token(string $token)
	{
		if (strpos($token, ':') === false)
		{
			// If the token is the wrong format, abort.
			return;
		}

		list ($userid, $hash) = explode(':', $token, 2);
		$hash = @base64_decode($hash);

		$this->db()->query('', '
			DELETE FROM {db_prefix}sessions_persist
			WHERE id_member = {int:id_member}
				AND persist_key = {binary:hash}',
			[
				'id_member' => $userid,
				'hash' => $hash,
			]
		);
	}

	public function create_cookie(int $userid, string $key): Cookie
	{
		$boardurl = App::get_global_config_item('boardurl');

		$site_settings = $this->sitesettings();
		$cookie_url = CookieHelper::url_parts(!empty($site_settings->localCookies), !empty($site_settings->globalCookies));

		$name = App::get_global_config_item('cookiename') . '_persist';
		$value = $userid . ':' . base64_encode($key);
		$expire = strtotime('+1 month');
		$path = $cookie_url[1];
		$domain = $cookie_url[0];
		$secure = stripos(parse_url($boardurl, PHP_URL_SCHEME), 'https') === 0;
		$httpOnly = true;
		$raw = false;
		$sameSite = Cookie::SAMESITE_LAX;
		return Cookie::create($name, $value, $expire, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
	}

	public function remove_cookie(): Cookie
	{
		$boardurl = App::get_global_config_item('boardurl');

		$site_settings = $this->sitesettings();
		$cookie_url = CookieHelper::url_parts(!empty($site_settings->localCookies), !empty($site_settings->globalCookies));

		$name = App::get_global_config_item('cookiename') . '_persist';
		$value = '';
		$expire = strtotime('-1 year');
		$path = $cookie_url[1];
		$domain = $cookie_url[0];
		$secure = stripos(parse_url($boardurl, PHP_URL_SCHEME), 'https') === 0;
		$httpOnly = true;
		$raw = false;
		$sameSite = Cookie::SAMESITE_LAX;
		return Cookie::create($name, $value, $expire, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
	}
}
