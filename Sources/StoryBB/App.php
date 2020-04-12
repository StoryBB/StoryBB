<?php

/**
 * This file does a lot of important stuff. It bootstraps the application.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

use ReflectionMethod;
use StoryBB\Container;
use StoryBB\Routing\Exception\InvalidRouteException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

class App
{
	const SOFTWARE_YEAR = 2020;
	const SOFTWARE_VERSION = '1.0 Alpha 1';

	protected static $storybb_root = '';
	protected static $storybb_sources = '';

	public static function start($path)
	{
		static::$storybb_root = $path;
		static::$storybb_sources = $path . '/Sources';

		static::set_base_environment();
	}

	public static function get_root_path(): string
	{
		return static::$storybb_root;
	}

	public static function get_sources_path(): string
	{
		return static::$storybb_sources;
	}

	public static function set_base_environment()
	{
		ignore_user_abort(true);

		static::setMemoryLimit('128M');
	}

	public static function build_container()
	{
		global $smcFunc;

		$container = Container::instance();
		$container->inject('cachedir', App::get_root_path() . '/cache');

		$container->inject('database', $smcFunc['db']);

		$container->inject('sitesettings', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\SiteSettings');
		});

		$container->inject('filesystem', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\Filesystem');
		});
		$container->inject('smileys', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\Smiley');
		});
		$container->inject('router_public', function() use ($container) {
			$routes = new RouteCollection;
			foreach (ClassManager::get_classes_implementing('StoryBB\\Controller\\Routable') as $controllable)
			{
				$controllable::register_own_routes($routes);
			}

			return $routes;
		});
		$container->inject('compiled_matcher', function() use ($container) {
			$compiled_routes = $container->get('cachedir') . '/compiled_matcher.php';
			if (file_exists($compiled_routes))
			{
				try
				{
					$routes = include($compiled_routes);
					if ($array = unserialize($routes))
					{
						return $array;
					}
				}
				catch (\Throwable $e)
				{
					@unlink($compiled_routes);
				}
			}

			$routes = $container->get('router_public');
			$compilation = (new CompiledUrlMatcherDumper($routes))->getCompiledRoutes();

			file_put_contents($compiled_routes, '<?php return \'' . addcslashes(serialize($compilation), "\0" . '\\\'') . '\';');

			return $compilation;
		});
		$container->inject('compiled_generator', function() use ($container) {
			$compiled_routes = $container->get('cachedir') . '/compiled_generator.php';
			if (file_exists($compiled_routes))
			{
				try
				{
					$routes = include($compiled_routes);
					if ($array = unserialize($routes))
					{
						return $array;
					}
				}
				catch (\Throwable $e)
				{
					@unlink($compiled_routes);
				}
			}

			$routes = $container->get('router_public');
			$compilation = (new CompiledUrlGeneratorDumper($routes))->getCompiledRoutes();

			file_put_contents($compiled_routes, '<?php return \'' . addcslashes(serialize($compilation), "\0" . '\\\'') . '\';');

			return $compilation;
		});
		$container->inject('urlgenerator', function() use ($container) {
			return new CompiledUrlGenerator($container->get('compiled_generator'), $container->get('requestcontext'));
		});
		$container->inject('urlmatcher', function() use ($container) {
			return new CompiledUrlMatcher($container->get('compiled_matcher'), $container->get('requestcontext'));
		});
		$container->inject('templater', function() use ($container) {
			$latte = new \Latte\Engine;
			$latte->setTempDirectory($container->get('cachedir') . '/template');

			$loader = new \Latte\Loaders\FileLoader(self::get_root_path() . '/Themes/default/templates');
			$latte->setLoader($loader);

			$latte->addFilter('translate', function ($string, $langfile = '') {
				return $string . ($langfile ? ' (' . $langfile . ')' : '');
			});
			$latte->addFunction('link', function($url, $params = []) use ($container) {
				try
				{
					$urlgenerator = $container->get('urlgenerator');
					return $urlgenerator->generate($url, $params);
				}
				catch (\Exception $e)
				{
					return $e->getMessage();
				}
			});
			return $latte;
		});

		return $container;
	}

	public static function dispatch_request(Request $request)
	{
		$container = Container::instance();
		static::build_container();
		$container->inject('requestvars', $request);
		$request_context = (new RequestContext('/'))->fromRequest($request);
		$container->inject('requestcontext', function() use ($container) {
			return (new RequestContext('/'))->fromRequest($container->get('requestvars'));
		});

		try
		{
			$matcher = $container->get('urlmatcher');
			$parameters = $matcher->match($container->get('requestcontext')->getPathInfo());

			if (empty($parameters['_controller']))
			{
				throw new InvalidRouteException('No controller defined in matched route ' . ($parameters['_route'] ?? 'unknown'));
			}

			if (!is_array($parameters['_controller']) || !isset($parameters['_controller'][0], $parameters['_controller'][1]))
			{
				throw new InvalidRouteException('Route ' . $parameters['_route'] . ' defines an invalid controller.');
			}

			if (!class_exists($parameters['_controller'][0]))
			{
				throw new ResourceNotFoundException('Controller ' . $parameters['_controller'][0] . ' does not exist');
			}

			$class = $parameters['_controller'][0];
			$method = $parameters['_controller'][1];
			if (!method_exists($class, $method))
			{
				throw new InvalidRouteException('Route ' . $parameters['_route'] . ' asks for ' . implode('::', $parameters['_controller']) . ', not found');
			}

			$controller_method = new ReflectionMethod($class, $method);
			$args = [];
			foreach ($controller_method->getParameters() as $controller_param)
			{
				if (isset($parameters[$controller_param->getName()]))
				{
					$args[] = $parameters[$controller_param->getName()];
					continue;
				}

				// If the parameter the method wants is not optional but we don't have it, abort!
				if (!$controller_param->isOptional())
				{
					throw new InvalidRouteException(implode('::', $parameters['_controller']) . ' is missing method parameter ' . $controller_param->getName());
				}
			}

			$instance = $container->instantiate($class);
			return $instance->$method(...$args);
		}
		catch (ResourceNotFoundException $e)
		{
			return false;
		}
	}

	/**
	 * Helper function to set the system memory to a needed value
	 * - If the needed memory is greater than current, will attempt to get more
	 * - if in_use is set to true, will also try to take the current memory usage in to account
	 *
	 * @param string $needed The amount of memory to request, if needed, like 256M
	 * @param bool $in_use Set to true to account for current memory usage of the script
	 * @return boolean True if we have at least the needed memory
	 */
	public static function setMemoryLimit(string $needed, bool $in_use = false): bool
	{
		// Everything converted to bytes.
		$memory_current = self::memoryReturnBytes(ini_get('memory_limit'));
		$memory_needed = self::memoryReturnBytes($needed);

		// Should we account for how much is currently being used?
		if ($in_use)
			$memory_needed += function_exists('memory_get_usage') ? memory_get_usage() : (2 * 1048576);

		// If more is needed, request it.
		if ($memory_current < $memory_needed)
		{
			@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
			$memory_current = self::memoryReturnBytes(ini_get('memory_limit'));
		}

		$memory_current = max($memory_current, self::memoryReturnBytes(get_cfg_var('memory_limit')));

		// Return if successful.
		return (bool) ($memory_current >= $memory_needed);
	}

	/**
	 * Helper function to convert memory string settings to bytes
	 *
	 * @param string $val The byte string, like 256M or 1G
	 * @return integer The string converted to a proper integer in bytes
	 */
	public static function memoryReturnBytes($val)
	{
		if (is_integer($val))
			return $val;

		// Separate the number from the designator
		$val = trim($val);
		$num = intval(substr($val, 0, strlen($val) - 1));
		$last = strtolower(substr($val, -1));

		// convert to bytes
		switch ($last)
		{
			case 'g':
				$num *= 1024;
			case 'm':
				$num *= 1024;
			case 'k':
				$num *= 1024;
		}
		return $num;
	}
}
