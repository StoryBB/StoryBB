<?php

/**
 * This class handles some authentication.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Auth;

use StoryBB\StringLibrary;

class Bcrypt
{
	public function validate($username, $password, $hash)
	{
		return password_verify(StringLibrary::toLower($username) . $password, $hash);
	}
}
