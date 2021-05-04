<?php

/**
 * This hook abstract forms the basis of a read-write hook.
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
 * This hook abstract forms the basis of a read-write hook.
 */
abstract class Mutatable extends Hookable
{
	/**
	 * @var array $vars Storage of the data being passed between hooked functions.
	 */
	protected $vars = [];

	/**
	 * Getter for variables carried by this hook.
	 *
	 * @param string $property The setting intended to be set with this object
	 * @return mixed This is a getter with potentially any return value
	 * @throws InvalidArgumentException if trying to access a key that is known to not exist.
	 */
	final public function &__get(string $property)
	{
		if (!isset($this->vars[$property]))
		{
			throw new InvalidArgumentException(static::class . ' does not have property ' . $property);
		}

		return $this->vars[$property];
	}

	/**
	 * Setter for variables carried by this hook.
	 *
	 * @param string $property The setting intended to be set with this object
	 * @param mixed $value The value to be set to it
	 * @return void This is a setter with no return value
	 * @throws InvalidArgumentException if trying to access a key that is known to not exist.
	 */
	final public function __set(string $property, $value): void
	{
		if (!isset($this->vars[$property]))
		{
			throw new InvalidArgumentException(static::class . ' does not have property ' . $property);
		}

		$this->vars[$property] = $value;
	}
}
