<?php

/**
 * This file initiates updates of the master list of known TLDs for link recognition.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;

use StoryBB\Helper\TLD;

/**
 * This file initiates updates of the master list of known TLDs for link recognition.
 */
class UpdateTldRegex extends \StoryBB\Task\Adhoc
{
	/**
	 * This executes the task. It just calls set_tld_regex helper.
	 * @return bool Always returns true
	 */
	public function execute()
	{
		TLD::set_tld_regex(true);

		return true;
	}
}
