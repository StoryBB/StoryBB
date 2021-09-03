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
use StoryBB\Container;
use StoryBB\Controller\MaintenanceAccessible;
use StoryBB\Controller\Unloggable;
use StoryBB\Database\AdapterFactory;
use StoryBB\Helper\Cookie;
use StoryBB\Phrase;
use StoryBB\Routing\Exception\InvalidRouteException;
use StoryBB\Search\AdapterFactory as SearchAdapterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Twig\Loader\FilesystemLoader;
use Twig\Environment as TwigEnvironment;
use Twig\TwigFunction;

class App
{
	const SOFTWARE_YEAR = 2021;
	const SOFTWARE_VERSION = '1.0 Alpha 1';

	protected static $global_config = [];
	protected static $storybb_root = '';
	protected static $storybb_sources = '';

	public static function start($path)
	{
		static::$storybb_root = $path;
		static::$storybb_sources = $path . '/Sources';

		require($path . '/Settings.php');

		static::$global_config = [
			'maintenance' => $maintenance ?? 0,
			'maintenance_title' => $mtitle ?? '',
			'maintenance_message' => $mmessage ?? '',
			'language' => $language ?? 'en-us',
			'boardurl' => $boardurl ?? 'http://localhost/',
			'webmaster_email' => $webmaster_email ?? 'root@localhost',
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
	}

	public static function get_root_path(): string
	{
		return static::$storybb_root;
	}

	public static function get_sources_path(): string
	{
		return static::$storybb_sources;
	}

	public static function get_global_config_item(string $key)
	{
		return static::$global_config[$key] ?? null;
	}

	public static function get_global_config()
	{
		return static::$global_config;
	}

	public static function in_maintenance()
	{
		return static::$global_config['maintenance'] > 0;
	}

	public static function in_hard_maintenance()
	{
		return stataic::$global_config['maintenance'] == 2;
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

		$global_config = static::get_global_config();
		$container->inject('database', function() use ($container, $global_config) {
			// Add in the port if needed
			$db_options = [];
			if (!empty($global_config['db_port']))
			{
				$db_options['port'] = $global_config['db_port'];
			}

			$options = array_merge($db_options, ['persist' => $global_config['db_persist']]);

			$db = AdapterFactory::get_adapter($global_config['db_type']);
			$db->set_prefix($global_config['db_prefix']);
			$db->set_server($global_config['db_server'], $global_config['db_name'], $global_config['db_user'], $global_config['db_passwd']);
			$db->connect($options);

			return $db;
		});
		$smcFunc = [
			'db' => $container->get('database'),
		];

		$container->inject('response_headers', function() {
			return new ResponseHeaderBag([]);
		});

		$container->inject('sitesettings', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\SiteSettings');
		});

		$container->inject('filesystem', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\Filesystem');
		});
		$container->inject('search', function() use ($container) {
			$sitesettings = $container->get('sitesettings');
			$backend = $sitesettings->search_backend ?? 'NativeFulltext';

			return $container->instantiate(SearchAdapterFactory::get_adapter_class($backend));
		});
		$container->inject('session', function() use ($container) {
			global $cookiename, $sc;

			$site_settings = $container->get('sitesettings');
			$cookie_url = Cookie::url_parts(!empty($site_settings->localCookies), !empty($site_settings->globalCookies));
			$cookie_settings = [
				'cookie_httponly' => true,
				'cookie_domain' => $cookie_url[0],
				'cookie_path' => $cookie_url[1],
				'cookie_secure' => stripos(parse_url(App::get_global_config_item('boardurl'), PHP_URL_SCHEME), 'https') === 0,
				'cookie_samesite' => 'Lax',
			];

			if (!empty($site_settings->databaseSession_loose))
			{
				session_cache_limiter('private_no_expires');
			}
			if ($site_settings->databaseSession_enable)
			{
				$session_storage = new NativeSessionStorage($cookie_settings, $container->instantiate('StoryBB\\Session\\DatabaseHandler'));
				$session = new Session($session_storage, new NamespacedAttributeBag);
			}
			else
			{
				$session = new Session($cookie_settings, new NamespacedAttributeBag);
			}

			$session->setName($cookiename);
			$session->start();

			$response_headers = $container->get('response_headers');

			if (!$session->has('userid'))
			{
				$persist_cookie = App::get_global_config_item('cookiename') . '_persist';
				$request = $container->get('requestvars');
				if ($request->cookies->has($persist_cookie))
				{
					$persistence = $container->instantiate('StoryBB\\Session\\Persistence');
					$token = $request->cookies->get($persist_cookie);
					$userid = $persistence->validate_persist_token($token);
					if ($userid)
					{
						$session->set('userid', $userid);

						list ($userid, $hash) = explode(':', $token, 2);
						$hash = @base64_decode($hash);
						$response_headers->setCookie($persistence->create_cookie((int) $userid, $hash));
					}
					else
					{
						$session->set('userid', 0);
						$response_headers->setCookie($persistence->remove_cookie());
					}
				}
				else
				{
					$session->set('userid', 0);
				}
			}

			// Change it so the cache settings are a little looser than default.
			header('Cache-Control: private');

			if (!$session->has('session_var'))
			{
				$session->set('session_value', md5($session->getId() . mt_rand()));
				$session->set('session_var', substr(preg_replace('~^\d+~', '', sha1(mt_rand() . $session->getId() . mt_rand())), 0, mt_rand(7, 12)));

				$sc = $session->get('session_value');
			}

			return $session;
		});
		$container->inject('currentuser', function() use ($container) {
			return $container->instantiate('StoryBB\User\CurrentUser');
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
		$container->inject('templaterenderer', function() use ($container) {
			global $settings;
			// @todo
			if (empty($settings['theme_dir']))
			{
				loadTheme();
			}

			$loader = new FilesystemLoader();
			if (file_exists($settings['theme_dir'] . '/templates'))
			{
				$loader->addPath($settings['theme_dir'] . '/templates');
			}
			if (file_exists($settings['theme_dir'] . '/templates/layouts'))
			{
				$loader->addPath($settings['theme_dir'] . '/templates/layouts', 'layouts');
			}
			if (file_exists($settings['theme_dir'] . '/templates/partials'))
			{
				$loader->addPath($settings['theme_dir'] . '/templates/partials', 'partials');
			}

			$loader->addPath($settings['default_theme_dir'] . '/templates');
			$loader->addPath($settings['default_theme_dir'] . '/templates/layouts', 'layouts');
			$loader->addPath($settings['default_theme_dir'] . '/templates/partials', 'partials');

			$sitesettings = $container->get('sitesettings');
			$options = [];
			if (!$sitesettings->debug_templates)
			{
				$options['cache'] = $container->get('cachedir') . '/template/' . $settings['theme_id'];
			}
			$twig = new TwigEnvironment($loader, $options);

			$twig->addFunction(new TwigFunction('phrase', function ($string, ...$params) {
				return new Phrase($string, $params);
			}));
			$twig->addFunction(new TwigFunction('link', function ($url, array $params = []) use ($container) {
				try
				{
					$urlgenerator = $container->get('urlgenerator');
					return $urlgenerator->generate($url, $params);
				}
				catch (\Exception $e)
				{
					return $e->getMessage();
				}
			}, ['is_variadic' => true]));
			$twig->addFunction(new TwigFunction('slugify', function ($string) {
				$string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
				$string = strtolower($string);
				$string = preg_replace('/[^a-z0-9]+/i', '-', $string);
				return trim($string, '-');
			}));

			return $twig;
		});

		$container->inject('page', function() use ($container) {
			$page = $container->instantiate('StoryBB\\Page');
			$urlgenerator = $container->get('urlgenerator');
			$page->addMetaProperty('og:site_name', $container->get('sitesettings')->forum_name);

			$page->addLink('help', $urlgenerator->generate('help'));
			return $page;
		});

		return $container;
	}

	public static function dispatch_request(Request $request)
	{
		$container = Container::instance();
		static::build_container();
		$container->inject('requestvars', $request);
		$container->inject('requestcontext', function() use ($container) {
			$boardurl = App::get_global_config_item('boardurl');
			$sitesettings = $container->get('sitesettings');
			$baseurl = $sitesettings->drop_index_php ? $boardurl : $boardurl . '/index.php';
			return (new RequestContext('/'))->fromRequest($container->get('requestvars'))->setBaseUrl($baseurl);
		});

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

			$result = $instance->$method(...$args);
			// There's a bit of logging we're going to be doing here, potentially.
			if (!$instance instanceof Unloggable)
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
