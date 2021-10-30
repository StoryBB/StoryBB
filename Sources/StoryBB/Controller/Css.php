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
use StoryBB\Controller\Unloggable;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\SiteSettings;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\Routing\ErrorResponse;
use StoryBB\Routing\NotFoundResponse;
use StoryBB\StringLibrary;
use ScssPhp\ScssPhp\Compiler;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Css implements Routable, Unloggable, MaintenanceAccessible
{
	use Database;
	use SiteSettings;
	use UrlGenerator;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('css', (new Route('/css/{theme<\d+>}/{scssfile}/{timestamp<\d+>?0}', ['_controller' => [static::class, 'css_emitter']])));
	}

	public function css_emitter(int $theme, string $scssfile, int $timestamp)
	{
		$available_themes = $this->get_available_themes();

		if (!in_array($theme, $available_themes) && !($theme == 1 && $scssfile == 'admin'))
		{
			return new NotFoundResponse;
		}

		$themes = $this->get_theme_settings($available_themes);

		if (empty($themes[$theme]['theme_dir']) || !file_exists($themes[$theme]['theme_dir'] . '/css/' . $scssfile . '.scss'))
		{
			return new NotFoundResponse;
		}

		// Are we using a legacy version somehow?
		$compile_time_setting = 'compile_time_' . $scssfile;
		if ($this->sitesettings()->minimize_css && !empty($themes[$theme][$compile_time_setting]) && $timestamp != $themes[$theme][$compile_time_setting])
		{
			$url = $this->urlgenerator()->generate('css', [
				'theme' => $theme,
				'scssfile' => $scssfile,
				'timestamp' => $themes[$theme][$compile_time_setting],
			]);
			return new RedirectResponse($url, 301);
		}

		// Do we have a cached version?
		$cachedir = App::get_root_path() . '/cache';
		$cached_css_file = $cachedir . '/css/' . $theme . '_' . $scssfile . '_' . $timestamp . '.css';
		if (file_exists($cached_css_file))
		{
			return new Response(file_get_contents($cached_css_file), 200, ['content-type' => 'text/css']);
		}

		try
		{
			$cached_css = static::compile_theme($themes, $theme, $scssfile);

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
		if ($site_settings->enableThemes)
		{
			$available_themes = array_map('intval', explode(',', $site_settings->enableThemes));
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

	private function compile_theme(array $themes, int $theme, string $scssfile): string
	{
		$db = $this->db();
		$site_settings = $this->sitesettings();

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
		if (isset($themes[$theme]['theme_url']))
		{
			$injections['theme_url'] = '"' . $themes[$theme]['theme_url'] . '"';
		}
		if (isset($themes[$theme]['images_url']))
		{
			$injections['images_url'] = '"' . $themes[$theme]['images_url'] . '"';
		}

		// Add in all the theme's image URLs in case we want to cross the streams (e.g. refer to default iamges)
		foreach ($themes as $theme_id => $theme_settings)
		{
			if (isset($theme_settings['shortname']) && isset($theme_settings['theme_url']))
			{
				$injections[$theme_settings['shortname'] . '__theme_url'] = '"' . $theme_settings['theme_url'] . '"';
			}
			if (isset($theme_settings['shortname']) && isset($theme_settings['images_url']))
			{
				$injections[$theme_settings['shortname'] . '__images_url'] = '"' . $theme_settings['images_url'] . '"';
			}
		}

		$settings_to_export = [
			'avatar_max_width' => ['raw', 125],
			'avatar_max_height' => ['raw', 125],
		];

		foreach ($settings_to_export as $setting => $details)
		{
			[$format, $default] = $details;

			$value = $site_settings->$setting ?? $default;

			switch ($format)
			{
				case 'raw':
					$injections[$setting] = $value;
					break;
				case 'px':
					$injections[$setting] = $value . 'px';
					break;
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
		$result = $scss->compile(file_get_contents($themes[$theme]['theme_dir'] . '/css/' . $scssfile . '.scss'));

		$parsed = $scss->getParsedFiles();
		if (count($parsed) === 0)
		{
			throw new Exception('Nothing parsed.');
		}

		$result .= $this->get_font_css($themes, $theme);

		$compile_time = max($parsed);
		$filename = $theme . '_' . $scssfile . '_' . $compile_time;
		if (!file_exists($cachedir . '/css'))
		{
			@mkdir($cachedir . '/css');
		}

		if ($site_settings->minimize_css)
		{	
			file_put_contents($cachedir . '/css/' . $filename . '.css', $result);

			$db->insert('replace',
				'{db_prefix}themes',
				['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'],
				[0, $theme, 'compile_time_' . $scssfile, $compile_time],
				['id_member', 'id_theme', 'variable']
			);
		}

		return $result;
	}

	private function get_font_css(array $themes, int $theme): string
	{
		$valid_formats = [
			'truetype' => true, // TTF fonts.
			'opentype' => true, // OTF fonts.
			'woff' => true, // Web Open Font Format (1).
			'woff2' => true, // Web Open Font Format (2).
			'embedded-opentype' => true, // EOT, mostly old IE.
			'svg' => true,
		];

		// We need all the fonts from the current theme in any case.
		$fonts_done = [];

		$json = json_decode(file_get_contents($themes[$theme]['theme_dir'] . '/theme.json'), true);
		if (!empty($json) && !empty($json['fonts']))
		{
			foreach ($json['fonts'] as $font_id => $locations)
			{
				if (isset($locations['local']))
				{
					$srcs = [];
					foreach ($locations['local'] as $format => $url)
					{
						$srcs[] = 'url("' . strtr($url, ['$theme_url' => $themes[$theme]['theme_url']]) . '") format("' . $format . '")';
						if (!isset($valid_formats[$format]))
						{
							continue;
						}
					}

					if (!empty($srcs))
					{
						$css = '@font-face {';
						$css .= 'font-family: ' . $font_id . ';';
						$css .= 'src: ' . implode(', ', $srcs);
						$css .= '}';
					}

					$fonts_done[$font_id] = $css;
				}
			}
		}

		// Then we need to include all the fonts from other themes that aren't covered but requested.
		$theme_fonts = App::container()->get('thememanager')->get_font_list();
		foreach ($theme_fonts as $font_id => $font)
		{
			if (isset($fonts_done[$font_id]))
			{
				continue;
			}

			// @todo
		}

		// And finally return it all in a string. We don't need to minify this, it's already pretty-much minified.
		return "\n" . implode("\n", $fonts_done);
	}
}
