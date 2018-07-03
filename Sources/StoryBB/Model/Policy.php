<?php

/**
 * This class handles policies in the system.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles attachments.
 */
class Policy
{
	const POLICY_NOTACCEPTED = 0;
	const POLICY_PREVIOUSLYACCEPTED = 1;
	const POLICY_CURRENTLYACCEPTED = 2;
}
