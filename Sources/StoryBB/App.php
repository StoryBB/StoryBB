<?php

/**
 * This file does a lot of important stuff. It bootstraps the application.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB;

use ReflectionMethod;
use RuntimeException;
use StoryBB\Container;
use StoryBB\Database\AdapterFactory;
use StoryBB\Helper\Cookie;
use StoryBB\Phrase;
use StoryBB\Routing\Behaviours\Administrative;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Routing\Behaviours\Unloggable;
use StoryBB\Routing\Exception\InvalidRouteException;
use StoryBB\Search\AdapterFactory as SearchAdapterFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class App
{
	const SOFTWARE_YEAR = 2021;
	const SOFTWARE_VERSION = '1.0 Alpha 1';

	/** @var array $global_config The storage for the global configuration. */
	protected static $global_config = [];

	/** @var string $storybb_root The path to the application instance. */
	protected static $storybb_root = '';

	/** @var string $storybb_sources The path to the Sources folder. */
	protected static $storybb_sources = '';

	/** @var string $storybb_languages The path to the Languages folder. */
	protected static $storybb_languages = '';

	/** @var object $app The App object that is the basis of the DI container. */
	protected static $app = null;

	/** @var object $container The DI container. */
	protected static $container = null;

	/**
	 * Initialises the application.
	 *
	 * @param string $path The absolute path to the root of the platform.
	 * @param mixed $app An object in StoryBB\App that defines the container configuration.
	 */
	public static function start($path, $app)
	{
		static::$storybb_root = $path;
		static::$storybb_sources = $path . '/Sources';
		static::$storybb_languages = $path . '/Languages';

		require($path . '/Settings.php');

		static::$global_config = [
			'language' => $language ?? 'en-us',
			'boardurl' => $boardurl ?? 'http://localhost/',
			'cookiename' => $cookiename ?? 'SBBCookie123',
			'db_type' => $db_type ?? 'mysql',
			'db_server' => $db_server ?? 'localhost',
			'db_port' => $db_port ?? false,
			'db_name' => $db_name ?? 'storybb',
			'db_user' => $db_user ?? 'root',
			'db_passwd' => $db_passwd ?? '',
			'db_prefix' => $db_prefix ?? 'sbb_',
			'db_persist' => $db_persist ?? 0,
			'db_show_debug' => isset($db_show_debug) && $db_show_debug === true,
			'cache_accelerator' => $cache_accelerator ?? '',
			'cache_enable' => $cache_enable ?? 0,
			'cache_memcached' => $cache_memcached ?? '',
			'cache_redis' => $cache_redis ?? '',
			'image_proxy_enabled' => $image_proxy_enabled ?? 0,
			'image_proxy_secret' => $image_proxy_secret ?? 'invalidhash',
			'image_proxy_maxsize' => $image_proxy_maxsize ?? 5120,
		];

		static::set_base_environment();

		static::$app = $app;
		static::$container = $app->build_container();
	}

	/**
	 * Returns the root path to the current installation.
	 *
	 * @return string The absolute path to the current install of StoryBB.
	 */
	public static function get_root_path(): string
	{
		return static::$storybb_root;
	}

	/**
	 * Returns the path to the Sources folder of the current install.
	 *
	 * @return string Absolute path to the Sources/ folder.
	 */
	public static function get_sources_path(): string
	{
		return static::$storybb_sources;
	}

	/**
	 * Returns the path to the Languages folder of the current install.
	 *
	 * @return string Absolute path to the Languages/ folder.
	 */
	public static function get_languages_path(): string
	{
		return static::$storybb_languages;
	}

	/**
	 * Returns an item from global configuration (i.e. Settings.php)
	 *
	 * @param string $key The item desired from global configuration.
	 * @return mixed The configuration value, null if not known.
	 */
	public static function get_global_config_item(string $key)
	{
		return static::$global_config[$key] ?? null;
	}

	/**
	 * Returns the entire global configuration (i.e. Settings.php)
	 *
	 * @return array The configuration values.
	 */
	public static function get_global_config()
	{
		return static::$global_config;
	}

	/**
	 * Returns whether the site is in any kind of maintenance mode.
	 *
	 * @return bool True if the site is in *some* kind of maintenance.
	 */
	public static function in_maintenance(): bool
	{
		$site_settings = static::container()->get('sitesettings');
		return $site_settings->maintenance_mode;
	}

	/**
	 * Returns whether the site is in hard maintenance mode.
	 *
	 * In hard maintenance mode all actions to the site are disabled,
	 * cron is suspended and only some command line functions work.
	 *
	 * @return bool True if in hard maintenance.
	 */
	public static function in_hard_maintenance(): bool
	{
		$cachedir = static::container()->get('cachedir');
		return file_exists($cachedir . '/maintenance.html');
	}

	/**
	 * Displays the hard maintenance message.
	 */
	public static function show_hard_maintenance_message()
	{
		$cachedir = static::container()->get('cachedir');
		$message = file_get_contents($cachedir . '/maintenance.html');

		// Don't cache this page!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		// Send the right error codes.
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		header('Retry-After: 3600');
		if ($message)
		{
			die($message);
		}
		else
		{
			die('<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex">
		<title>Maintenance Mode</title>
	</head>
	<body>
		<h3>Maintenance Mode</h3>
		This site is currently in maintenance mode.
	</body>
</html>');
		}
	}

	/**
	 * Sets some initial environment settings.
	 */
	public static function set_base_environment(): void
	{
		ignore_user_abort(true);

		static::setMemoryLimit('128M');
	}

	/**
	 * Attempts to match the current request to a route and return its response.
	 *
	 * @todo Make return type nullable Response.
	 * @return mixed Response object if matched, false if not.
	 */
	public static function dispatch_request()
	{
		$container = Container::instance();

		try
		{
			$matcher = $container->get('urlmatcher');
			$parameters = $matcher->match($container->get('requestcontext')->getPathInfo());

			// @todo remove this dirty hack once everything is a proper route.
			if (!empty($parameters['_function']))
			{
				return $parameters;
			}

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

			if (static::in_maintenance() && !$instance instanceof MaintenanceAccessible) {
				// @todo remove this dirty hack when there is a real controller to replace InMaintenance
				// @todo allow those with admin_forum to proceed
				return false;
			}

			if (method_exists($instance, 'requires_permissions'))
			{
				$permissions = $instance->requires_permissions();
				$user = $container->get('currentuser');
				if (!empty($permissions) && !$user->can($permissions))
				{
					if (!$user->is_authenticated())
					{
						return new RedirectResponse($container->get('url')->generate('login'));
					}
					die('No permission.');
				}
			}

			$result = $instance->$method(...$args);
			// There's a bit of logging we're going to be doing here, potentially.
			if (!$instance instanceof Unloggable && !$instance instanceof Administrative)
			{
				// @todo log the hit

				$log = $parameters;
				unset ($log['_controller']);
				$container->get('session')->set('last_url', ['route' => $log]);
			}

			$response_headers = $container->get('response_headers');
			if (!empty($response_headers))
			{
				foreach ($response_headers->all() as $header => $value)
				{
					if ($header == 'date' || $header == 'cache-control')
					{
						continue;
					}

					if ($header == 'set-cookie')
					{
						foreach ($response_headers->getCookies() as $cookie)
						{
							$result->headers->setCookie($cookie);
						}
						continue;
					}
					$result->headers->set($header, $value);
				}
			}
			return $result;
		}
		catch (ResourceNotFoundException $e)
		{
			return false;
		}
	}

	/**
	 * Facade for creating something from the DI container.
	 *
	 * @param string $class The fully qualified class name.
	 * @param array $args Arguments to be passed in to the constructor.
	 */
	public static function make($class, ...$args)
	{
		if (empty(static::$container))
		{
			throw new RuntimeException('Container has not been set up yet!');
		}
		return static::$container->instantiate($class, ...$args);
	}

	/**
	 * Returns the current instance of the DI container.
	 *
	 * @return object Current DI container.
	 */
	public static function container()
	{
		if (empty(static::$container))
		{
			throw new RuntimeException('Container has not been set up yet!');
		}
		return static::$container;
	}

	/**
	 * Helper function to set the system memory to a needed value
	 * - If the needed memory is greater than current, will attempt to get more
	 * - if in_use is set to true, will also try to take the current memory usage in to account
	 *
	 * @param string $needed The amount of memory to request, if needed, like 256M
	 * @param bool $in_use Set to true to account for current memory usage of the script
	 * @return bool True if we have at least the needed memory
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
	 * @return int The string converted to a proper integer in bytes
	 */
	public static function memoryReturnBytes($val): int
	{
		if (is_integer($val))
		{
			return $val;
		}

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
