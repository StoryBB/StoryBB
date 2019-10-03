<?php

/**
 * This hook abstract forms the basis of a read-write hook.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook;

use InvalidArgumentException;
use StoryBB\Hook\Hookable;

/**
 * This hook abstract forms the basis of a read-write hook.
 */
abstract class Mutatable extends Hookable
{
	protected $vars = [];

	final public function &__get($property)
	{
		if (!isset($this->vars[$property]))
		{
			throw new InvalidArgumentException(static::class . ' does not have property ' . $property);
		}

		return $this->vars[$property];
	}

	final public function __set($property, $value)
	{
		if (!isset($this->vars[$property]))
		{
			throw new InvalidArgumentException(static::class . ' does not have property ' . $property);
		}

		$this->vars[$property] = $value;
	}
}
