<?php

/**
 * This file initiates updates of $modSettings['tld_regex']
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;

/**
 * Calls the existing functionality for TLD management, just makes it available as an adhoc task.
 */
class UpdateTldRegex extends \StoryBB\Task\Adhoc
{
    /**
     * This executes the task. It just calls set_tld_regex helper.
     * @return bool Always returns true
     */
	public function execute()
 	{
		StoryBB\Helper\TLD::set_tld_regex(true);

		return true;
	}
}
