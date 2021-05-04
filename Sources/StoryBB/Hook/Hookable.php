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
	/**
	 * Calls the execution of the hook that concretes this abstract class.
	 *
	 * @return void
	 */
	final public function execute(): void
	{
		Manager::execute($this);
	}

	/**
	 * Exports the name of the concrete class of this hook.
	 *
	 * @return string The fully qualified class name for this hook.
	 */
	final public function __toString(): string
	{
		return static::class;
	}
}
