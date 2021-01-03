<?php

/**
 * This hook abstract forms the basis of any hook.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook;

use StoryBB\Hook\Manager;

/**
 * This hook abstract forms the basis of a read-only hook.
 */
abstract class Hookable
{
	final public function execute()
	{
		Manager::execute($this);
	}

	final public function __toString(): string
	{
		return static::class;
	}
}
