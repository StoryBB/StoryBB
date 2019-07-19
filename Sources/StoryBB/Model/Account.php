<?php

/**
 * This class handles accounts.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles accounts.
 */
class Account
{
	const ACCOUNT_NOTACTIVATED = 0;
	const ACCOUNT_ACTIVATED = 1;
	const ACCOUNT_NOTREACTIVATED = 2;
	const ACCOUNT_ADMINAPPROVALPENDING = 3;
	const ACCOUNT_DELETIONPENDING = 4;
	const ACCOUNT_UNDERAGEPENDING = 5;

	const ACCOUNT_BANNED = 10;
}
