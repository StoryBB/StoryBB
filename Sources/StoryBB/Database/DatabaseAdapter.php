<?php

/**
 * Any database connector should implement this.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Database;

/**
 * Any database connector should implement this.
 */
interface DatabaseAdapter
{
	const INSERT_INSERT = 'insert';
	const INSERT_IGNORE = 'ignore';
	const INSERT_REPLACE = 'replace';

	const RETURN_NOTHING = 0;
	const RETURN_LAST_ID = 1;
	const RETURN_ALL = 2;
}
