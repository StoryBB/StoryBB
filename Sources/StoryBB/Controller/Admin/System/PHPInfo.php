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

use StoryBB\Phrase;
use StoryBB\Controller\Admin\AbstractAdminController;
use StoryBB\Controller\Administrative;
use StoryBB\Controller\MaintenanceAccessible;
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
			$rendercontext['phpinfo'][$id] = [
				'name' => $area,
				'col2' => [],
				'col2' => [],
			];
			foreach ($php_area as $key => $setting)
			{
				$rendercontext['phpinfo'][$id][is_array($setting) ? 'col3' : 'col2'][$key] = $setting;
			}
		}
		return $this->render('admin/phpinfo.twig', 'system/phpinfo', $rendercontext);
	}
}
