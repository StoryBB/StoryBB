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
use StoryBB\ClassManager;
use StoryBB\Container;

/**
 * This hook abstract forms the basis of a read-only hook for internal integrations.
 */
abstract class AbstractIntegratable
{
	/**
	 * @var array $vars Storage of the data being passed between hooked functions.
	 */
	protected $vars = [];

	/**
	 * @var array $integrations Storage of the configuration for integrations defined.
	 */
	protected static $integrations = null;

	/**
	 * @var array $connectors Storage of class instances for integrations.
	 */
	protected static $connectors = null;

	/**
	 * Getter for variables carried by this hook.
	 *
	 * @param string $property The setting intended to be set with this object
	 * @return mixed This is a getter with potentially any return value
	 * @throws InvalidArgumentException if trying to access a key that is known to not exist.
	 */
	final public function __get(string $property)
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
	 * @throws InvalidArgumentException if extending Observable as these do not permit any alterations.
	 */
	final public function __set(string $property, $value)
	{
		throw new InvalidArgumentException(static::class . ' is observable only, ' . $property . ' cannot be set');
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

	final public function execute(): void
	{
		if (self::$integrations === null)
		{
			self::populate_integration_cache();
		}

		foreach (self::$integrations as $integration)
		{
			if ($integration['integratable'] !== static::class)
			{
				continue;
			}

			$class = self::get_connector($integration['integration']);
			if (!$class)
			{
				continue;
			}

			$method = self::get_method(static::class);
			if ($method && is_callable([$class, $method]))
			{
				$class->$method($this, $integration['options']);
			}
		}
	}

	final protected function populate_integration_cache(): void
	{
		global $smcFunc;

		self::$integrations = [];
		$request = $smcFunc['db']->query('', '
			SELECT i.integratable, i.integration, i.options
			FROM {db_prefix}integrations AS i
			WHERE i.active = 1');
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$row['options'] = !empty($row['options']) ? @json_decode($row['options'], true) : [];
			self::$integrations[] = $row;
		}
		$smcFunc['db']->free_result($request);
	}

	final protected function get_connector(string $integration): ?object
	{
		global $smcFunc;

		if (self::$connectors === null)
		{
			self::$connectors = [];
			foreach (ClassManager::get_classes_implementing('StoryBB\\Integration\\Integration') as $connector)
			{
				$classname = strtolower(substr(strrchr($connector, '\\'), 1));
				self::$connectors[$classname] = $connector;
			}
		}

		if (isset(self::$connectors[$integration]))
		{
			if (is_string(self::$connectors[$integration]))
			{
				$container = Container::instance();
				self::$connectors[$integration] = $container->instantiate(self::$connectors[$integration]);
			}

			return self::$connectors[$integration];
		}

		return null;
	}

	final public static function get_method(string $classname): ?string
	{
		$pos = stripos($classname, '\\integratable\\');
		if ($pos === false)
		{
			return null;
		}

		return str_replace('\\', '_', strtolower(substr($classname, $pos + 14)));
	}
}
