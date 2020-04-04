<?php

/**
 * A library for handling class discovery.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

class ClassManager
{
	/**
	 * Given the name of an interface, return the classes which implement that interface.
	 * The interface in question must be an extension of the Discoverable interface.
	 *
	 * e.g. StoryBB\Helper\Autocomplete\Completable is a valid interface.
	 *
	 * @param string $interface The interface name from root namespace
	 * @return array An array of class names that can be autoloaded
	 */
	public static function get_classes_implementing(string $interface): array
	{
		$cache = self::get_cache();
		return isset($cache[$interface]) ? $cache[$interface] : [];
	}

	/**
	 * Fetches the data required for the classes. Loads from cache where possible.
	 *
	 * @return array An array of interface -> implementing classes (where interface implements Discoverable)
	 */
	protected static function get_cache(): array
	{
		$cachedir = App::get_root_path() . '/cache';
		if (!file_exists($cachedir . '/class_cache.php'))
		{
			$class_cache = self::rebuild_cache();
		}
		else
		{
			$class_cache = [];
			include($cachedir . '/class_cache.php');
		}

		return $class_cache;
	}

	/**
	 * Forces a rebuild of the cache of interfaces/classes. Also returns the list so the run
	 * that generates the build gets it too.
	 *
	 * @return An array of interface -> implementing classes (where interface implements Discoverable)
	 */
	public static function rebuild_cache(): array
	{
		$cachedir = App::get_root_path() . '/cache';
		$sourcedir = App::get_sources_path();

		$class_cache = [];

		list($base_classes, $base_interfaces) = self::get_oo_from_basepath($sourcedir);
		foreach ($base_interfaces as $interface)
		{
			if ($interface === 'StoryBB\\Discoverable')
			{
				continue;
			}
			if (is_subclass_of($interface, 'StoryBB\\Discoverable'))
			{
				foreach ($base_classes as $class)
				{
					if (is_subclass_of($class, $interface))
					{
						$class_cache[$interface][] = $class;
					}
				}
			}
		}

		$cachefile = '<?php if (!defined(\'STORYBB\')) die; $class_cache = ' . var_export($class_cache, true) . ';';
		file_put_contents($cachedir . '/class_cache.php', $cachefile);
		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($cachedir . '/class_cache.php', true);
		}

		return $class_cache;
	}

	/**
	 * Finds all the things that look like classes or interfaces that live in a given folder/its subfolders.
	 *
	 * @param string $path The absolute path to start from
	 * @return array An array of two elements: an array of classes and an array of interfaces found in the path
	 */ 
	protected static function get_oo_from_basepath(string $path): array
	{
		$pathiterator = new \RecursiveDirectoryIterator($path);
		$fileiterator = new \RecursiveIteratorIterator($pathiterator);
		$filteriterator = new \RegexIterator($fileiterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

		$current_classes = get_declared_classes();
		$current_interfaces = get_declared_interfaces();

		foreach ($filteriterator as $file)
		{
			// Match the filename part of the PHP file. We're going to need that part.
			$match = basename($file[0]);
			if (strtolower(substr($match, -4)) === '.php') {
				$match = substr($match, 0, -4);
			}

			$filecontent = file_get_contents($file[0]);
			// Anything that references Behat should be excluded because we won't necessarily have those classes.
			if (strpos($filecontent, 'extends Behat\\') !== false || strpos($filecontent, 'use Behat\\') !== false)
			{
				continue;
			}

			// Does this file contain a class or interface?
			if (strpos($filecontent, "\nclass " . $match) !== false || strpos($filecontent, "\ninterface " . $match) !== false)
			{
				try
				{
					include_once($file[0]);
				}
				catch (\Throwable $e)
				{
					// We don't really care if this happens.
					continue;
				}
			}
		}

		return [
			array_diff(get_declared_classes(), $current_classes),
			array_diff(get_declared_interfaces(), $current_interfaces),
		];
	}
}
