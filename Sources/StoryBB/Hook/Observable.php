<?php

/**
 * This hook abstract forms the basis of a read-only hook.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook;

use InvalidArgumentException;
use StoryBB\Hook\Hookable;

/**
 * This hook abstract forms the basis of a read-only hook.
 */
abstract class Observable extends Hookable
{
	protected $vars = [];

	final public function __get($property)
	{
		if (!isset($this->vars[$property]))
		{
			throw new InvalidArgumentException(static::class . ' does not have property ' . $property);
		}

		return $this->vars[$property];
	}

	final public function __set($property, $value)
	{
		throw new InvalidArgumentException(static::class . ' is observable only, ' . $property . ' cannot be set');
	}
}
