<?php

/**
 * Super lightweight DI-style container.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

class Container
{
	protected static $instance;
	protected $container;

	final protected function __construct()
	{
		$this->container = [];
	}

	final public static function instance() : Container
	{
		if (empty(self::$instance))
		{
			self::$instance = new self;
		}

		return self::$instance;
	}

	final protected function __clone()
	{

	}

	final public function inject($key, $value)
	{
		$this->container[$key] = $value;
	}

	final public function has(string $key): bool
	{
		return isset($this->container[$key]);
	}

	final public function get(string $key)
	{
		if (isset($this->container[$key]))
		{
			if (is_callable($this->container[$key]))
			{
				$this->container[$key] = $this->container[$key]();
			}
		}
		return $this->container[$key] ?? null;
	}

	final public function instantiate($class, ...$args)
	{
		$instance = new $class(...$args);
		foreach (array_keys($this->container) as $key)
		{
			$method = '_accept_' . $key;
			if (method_exists($instance, $method))
			{
				$instance->$method($this->get($key));
			}
		}

		return $instance;
	}
}
