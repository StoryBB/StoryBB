<?php

/**
 * The admin home page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin\System;

use StoryBB\App;
use StoryBB\Phrase;
use StoryBB\Cache;
use StoryBB\Controller\Admin\AbstractAdminController;
use StoryBB\Routing\Behaviours\Administrative;
use StoryBB\Routing\Behaviours\MaintenanceAccessible;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Response;

class PHPInfo extends AbstractAdminController implements Administrative, MaintenanceAccessible
{
	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('system/phpinfo', (new Route('/system/phpinfo', ['_controller' => [static::class, 'get_phpinfo']])));
	}

	public function get_phpinfo(): Response
	{
		// Get the data direct from PHP Info. Sadly there's no nice way to do this.
		ob_start();
		phpinfo();

		// We only want it for its body tag.
		$info_lines = preg_replace('~^.*<body>(.*)</body>.*$~', '$1', ob_get_contents());
		$info_lines = explode("\n", strip_tags($info_lines, "<tr><td><h2>"));
		ob_end_clean();

		// Remove things that are sensitive or not relevant.
		$remove = '_COOKIE|Cookie|_GET|_REQUEST|REQUEST_URI|QUERY_STRING|REQUEST_URL|HTTP_REFERER';

		$localsettingslabel = (string) (new Phrase('Admin:phpinfo_localsettings'));
		$defaultsettingslabel = (string) (new Phrase('Admin:phpinfo_defaultsettings'));

		// Convert it to an array.
		foreach ($info_lines as $line)
		{
			if (preg_match('~(' . $remove . ')~', $line))
				continue;

			// Is this a new category?
			if (strpos($line, '<h2>') !== false)
			{
				$category = preg_match('~<h2>(.*)</h2>~', $line, $title) ? $category = $title[1] : $category;
			}

			// Load it as setting => value or the old setting local master.
			if (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val))
			{
				$pinfo[$category][$val[1]] = $val[2];
			}
			elseif (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val))
			{
				$pinfo[$category][$val[1]] = [$localsettingslabel => $val[2], $defaultsettingslabel => $val[3]];
			}
		}

		// Assemble it into a final array and hand it to the template.
		$rendercontext['phpinfo'] = [];
		foreach ($pinfo as $area => $php_area)
		{
			$id = str_replace(' ', '_', $area);
			if (empty($id))
			{
				$id = 'PHP';
			}
			$rendercontext['phpinfo'][$id] = [
				'name' => $area ?: 'PHP',
				'col2' => [],
				'col2' => [],
			];
			foreach ($php_area as $key => $setting)
			{
				$rendercontext['phpinfo'][$id][is_array($setting) ? 'col3' : 'col2'][$key] = $setting;
			}
		}

		$rendercontext['server_versions'] = $this->get_other_versions();

		return $this->render('admin/phpinfo.twig', 'system/phpinfo', $rendercontext);
	}

	protected function get_other_versions(): array
	{
		$db = App::container()->get('database');
		$versions = [
			'php' => [
				'title' => new Phrase('Admin:support_versions_php'),
				'version' => PHP_VERSION,
			],
			'server' => [
				'title' => new Phrase('Admin:support_versions_server'),
				'version' => $_SERVER['SERVER_SOFTWARE'],
			],
		];

		if ($db->connection_active())
		{
			$versions['db_server'] = [
				'title' => new Phrase('Admin:support_versions_db'),
				'version' => $db->get_server() . ' ' . $db->get_version(),
			];
		}

		// Is GD available?  If it is, we should show version information for it too.
		if (function_exists('gd_info'))
		{
			$temp = gd_info();
			$versions['gd'] = [
				'title' => new Phrase('Admin:support_versions_gd'),
				'version' => $temp['GD Version'],
			];
		}

		// Why not have a look at ImageMagick? If it's installed, we should show version information for it too.
		if (class_exists('Imagick') || function_exists('MagickGetVersionString'))
		{
			if (class_exists('Imagick'))
			{
				$temp = New Imagick;
				$temp2 = $temp->getVersion();
				$im_version = $temp2['versionString'];
				$extension_version = 'Imagick ' . phpversion('Imagick');
			}
			else
			{
				$im_version = MagickGetVersionString();
				$extension_version = 'MagickWand ' . phpversion('MagickWand');
			}

			// We already know it's ImageMagick and the website isn't needed...
			$im_version = str_replace(['ImageMagick ', ' https://www.imagemagick.org'], '', $im_version);
			$versions['imagemagick'] = ['title' => new Phrase('Admin:support_versions_imagemagick'), 'version' => $im_version . ' (' . $extension_version . ')'];
		}

		// Check to see if we have any accelerators installed...
		if (isset($_PHPA, $_PHPA['VERSION']))
		{
			$versions['phpa'] = [
				'title' => 'ionCube PHP-Accelerator',
				'version' => $_PHPA['VERSION'],
			];
		}

		$caches = [
			'apcu' => Cache\Apcu::class,
			'memcache' => Cache\Memcache::class,
			'memcached' => Cache\Memcached::class,
			'redis' => Cache\Redis::class,
		];

		foreach ($caches as $cache_name => $cache_class)
		{
			$cache = new $cache_class;
			if ($cache->isSupported(true))
			{
				$versions[$cache_name] = [
					'title' => $cache->getName(),
					'version' => '',
				];
				if ($client_version = $cache->getClientVersion())
				{
					$versions[$cache_name]['version'] = $client_version . ' (client)';
				}
				if ($cache->isSupported() && $server_version = $cache->getServerVersion())
				{
					$versions[$cache_name]['version'] .= ' ' . $server_version . ' (server)';
				}

				if (!trim($versions[$cache_name]['version']))
				{
					$versions[$cache_name]['version'] = '???';
				}
			}
		}

		return $versions;
	}
}
