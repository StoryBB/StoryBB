<?php

/**
 * This file does a lot of important stuff. It bootstraps the CLI application.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\App;

use StoryBB\App;
use StoryBB\ClassManager;
use StoryBB\Container;
use StoryBB\Database\AdapterFactory;
use StoryBB\Routing\Exception\InvalidRouteException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

class Cli
{
	public function build_container()
	{
		$global_config = App::get_global_config();

		$container = Container::instance();
		$container->inject('cachedir', \StoryBB\App::get_root_path() . '/cache');

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

			// Dirty legacy hack.
			$GLOBALS['smcFunc'] = ['db' => $db];

			return $db;
		});

		$container->inject('sitesettings', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\SiteSettings');
		});

		$container->inject('filesystem', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\Filesystem');
		});

		$container->inject('smileys', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\Smiley');
		});

		$container->inject('formatter', function() use ($container) {
			return $container->instantiate('StoryBB\\Helper\\Formatter');
		});

		$container->inject('router_public', function() use ($container) {
			$routes = new RouteCollection;
			foreach (ClassManager::get_classes_implementing('StoryBB\\Routing\\Behaviours\\Routable') as $controllable)
			{
				$controllable::register_own_routes($routes);
			}

			return $routes;
		});
		$container->inject('router_admin', function() use ($container) {
			$routes = new RouteCollection;
			foreach (ClassManager::get_classes_implementing('StoryBB\\Routing\\Behaviours\\Administrative') as $controllable)
			{
				$controllable::register_own_routes($routes);
			}

			return $routes;
		});

		$matcher = function($type = 'public') use ($container) {
			$compiled_routes = $container->get('cachedir') . '/compiled_' . $type . '_matcher.php';
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

			$routes = $container->get('router_' . $type);
			$compilation = (new CompiledUrlMatcherDumper($routes))->getCompiledRoutes();

			file_put_contents($compiled_routes, '<?php return \'' . addcslashes(serialize($compilation), "\0" . '\\\'') . '\';');

			return $compilation;
		};
		$generator = function($type = 'public') use ($container) {
			$compiled_routes = $container->get('cachedir') . '/compiled_' . $type . '_generator.php';
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

			$routes = $container->get('router_' . $type);
			$compilation = (new CompiledUrlGeneratorDumper($routes))->getCompiledRoutes();

			file_put_contents($compiled_routes, '<?php return \'' . addcslashes(serialize($compilation), "\0" . '\\\'') . '\';');

			return $compilation;
		};

		$container->inject('compiled_matcher', function() use ($matcher) {
			return $matcher('public');
		});
		$container->inject('admin_matcher', function() use ($matcher) {
			return $matcher('admin');
		});
		$container->inject('compiled_generator', function() use ($generator) {
			return $generator('public');
		});
		$container->inject('admin_generator', function() use ($generator) {
			return $generator('admin');
		});

		$container->inject('urlgenerator', function() use ($container) {
			return new CompiledUrlGenerator($container->get('compiled_generator'), $container->get('requestcontext'));
		});
		$container->inject('urlmatcher', function() use ($container) {
			return new CompiledUrlMatcher($container->get('compiled_matcher'), $container->get('requestcontext'));
		});
		$container->inject('requestcontext', function() use ($container) {
			$boardurl = \StoryBB\App::get_global_config_item('boardurl');
			$sitesettings = $container->get('sitesettings');
			$baseurl = $sitesettings->drop_index_php ? $boardurl : $boardurl . '/index.php';
			return (new RequestContext('/'))->setBaseUrl($baseurl);
		});
		$container->inject('current_theme_id', function() use ($container) {
			$site_settings = $container->get('sitesettings');
			return (int) $site_settings->theme_guests;
		});
		$container->inject('current_theme', function() use ($container) {
			return $container->instantiate('StoryBB\\Model\\Theme', $container->get('current_theme_id'));
		});

		return $container;
	}
}
