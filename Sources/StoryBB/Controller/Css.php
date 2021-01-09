<?php

/**
 * The help page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use Exception;
use StoryBB\App;
use StoryBB\Container;
use StoryBB\Controller\MaintenanceAccessible;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\SiteSettings;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\Routing\ErrorResponse;
use StoryBB\Routing\NotFoundResponse;
use StoryBB\Routing\RenderResponse;
use StoryBB\StringLibrary;
use ScssPhp\ScssPhp\Compiler;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Css implements Routable, MaintenanceAccessible
{
	use Database;
	use SiteSettings;
	use UrlGenerator;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('css', (new Route('/css/{theme<\d+>}/{timestamp<\d+>?0}', ['_controller' => [static::class, 'css_emitter']])));
	}

	public function css_emitter(int $theme, int $timestamp)
	{
		$available_themes = $this->get_available_themes();

		if (!in_array($theme, $available_themes))
		{
			return new NotFoundResponse;
		}

		$themes = $this->get_theme_settings($available_themes);

		if (empty($themes[$theme]['theme_dir']) || !file_exists($themes[$theme]['theme_dir'] . '/css/index.scss'))
		{
			return new NotFoundResponse;
		}

		// Are we using a legacy version somehow?
		if (!empty($themes[$theme]['compile_time']) && $timestamp != $themes[$theme]['compile_time'])
		{
			$url = $this->urlgenerator()->generate('css', [
				'theme' => $theme,
				'timestamp' => $themes[$theme]['compile_time'],
			]);
			return new RedirectResponse($url, 301);
		}

		// Do we have a cached version?
		$cachedir = App::get_root_path() . '/cache';
		$cached_css_file = $cachedir . '/css/' . $theme . '_' . $timestamp . '.css';
		if (file_exists($cached_css_file))
		{
			return new Response(file_get_contents($cached_css_file), 200, ['content-type' => 'text/css']);
		}

		try
		{
			$cached_css = static::compile_theme($themes, $theme);

			return new Response($cached_css, 200, ['content-type' => 'text/css']);
		}
		catch (Exception $e)
		{
			return new ErrorResponse('/* Problem building CSS: ' . $e->getMessage() . ' */', 500, ['content-type' => 'text/css']);
		}
	}

	private function get_available_themes(): array
	{
		$site_settings = $this->sitesettings();

		$available_themes = [];
		if ($site_settings->knownThemes)
		{
			$available_themes = array_map('intval', explode(',', $site_settings->knownThemes));
		}

		// Default theme is always available if there is nothing.
		if (empty($available_themes))
		{
			$available_themes[] = 1;
		}

		return $available_themes;
	}

	private function get_theme_settings(array $available_themes): array
	{
		$db = $this->db();

		$themes = [];

		// @todo abstract into a get theme method later.
		$request = $db->query('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member = {int:none}
				AND id_theme IN ({array_int:themes})',
			[
				'none' => 0,
				'themes' => $available_themes,
			]
		);

		while ($row = $db->fetch_assoc($request))
		{
			$themes[$row['id_theme']][$row['variable']] = $row['value'];
		}
		$db->free_result($request);

		return $themes;
	}

	private function compile_theme(array $themes, int $theme): string
	{
		$db = $this->db();

		$cachedir = App::get_root_path() . '/cache';
		$valid_theme_dirs = [];
		foreach ($themes as $enabled_id => $theme_settings)
		{
			if (!empty($theme_settings['theme_dir']) && !empty($theme_settings['shortname']))
			{
				$valid_theme_dirs[$theme_settings['shortname']] = $theme_settings['theme_dir'];
			}
		}

		$scss = new Compiler;

		$injections = [];
		if (isset($themes[$theme]['images_url']))
		{
			$injections['images_url'] = '"' . $themes[$theme]['images_url'] . '"';
		}

		// Add in all the theme's image URLs in case we want to cross the streams (e.g. refer to default iamges)
		foreach ($themes as $theme_id => $theme_settings)
		{
			if (isset($theme_settings['shortname']) && isset($theme_settings['images_url']))
			{
				$injections[$theme_settings['shortname'] . '__images_url'] = '"' . $theme_settings['images_url'] . '"';
			}
		}

		if (!empty($injections))
		{
			$scss->setVariables($injections);
		}

		$scss->addImportPath(function ($path) use ($valid_theme_dirs) {
			// @todo harden against LFI or dir trav
			foreach ($valid_theme_dirs as $slug => $theme_path)
			{
				if (strpos($path, $slug . '/') === 0 && file_exists($theme_path . '/css' . substr($path, strlen($slug))))
				{

					return $theme_path . '/css' . substr($path, strlen($slug));
				}
			}
			return null;
		});

		$scss->setFormatter('ScssPhp\\ScssPhp\\Formatter\\Crunched');
		$result = $scss->compile(file_get_contents($themes[$theme]['theme_dir'] . '/css/index.scss'));

		$parsed = $scss->getParsedFiles();
		if (count($parsed) === 0)
		{
			throw new Exception('Nothing parsed.');
		}

		$compile_time = max($parsed);
		$filename = $theme . '_' . $compile_time;
		if (!file_exists($cachedir . '/css'))
		{
			@mkdir($cachedir . '/css');
		}

		$site_settings = $this->sitesettings();
		if ($site_settings->minimize_files)
		{	
			file_put_contents($cachedir . '/css/' . $filename . '.css', $result);

			$db->insert('replace',
				'{db_prefix}themes',
				['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'],
				[0, $theme, 'compile_time', $compile_time],
				['id_member', 'id_theme', 'variable']
			);
		}

		return $result;
	}
}
