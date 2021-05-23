<?php

/**
 * Handles processing for a theme.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

declare(strict_types=1);

namespace StoryBB\Model;

/**
 * Handles processing for a theme.
 */
class Theme
{
	/**
	 * Gets the theme's JSON configuration file
	 *
	 * @return array Return the current theme's configuration settings
	 */
	private static function get_theme_json(): array
	{
		global $settings;

		if (file_exists($settings['theme_dir'] . '/theme.json'))
		{
			$theme_settings = file_get_contents($settings['theme_dir'] . '/theme.json');
			if (!empty($theme_settings))
			{
				$theme_json = json_decode($theme_settings, true);
			}
		}

		return !empty($theme_json) && is_array($theme_json) ? $theme_json : [];
	}

	/**
	 * Returns the default settings for the current theme
	 *
	 * @return array The default generic settings as set in the theme configuration
	 */
	public static function get_defaults(): array
	{
		$theme_json = self::get_theme_json();
		unset($theme_json['theme_settings'], $theme_json['user_options']);
		if (empty($theme_json['additional_files']))
		{
			$theme_json['additional_files'] = [];
		}
		if (empty($theme_json['additional_files']['css']))
		{
			$theme_json['additional_files']['css'] = [];
		}
		if (empty($theme_json['additional_files']['js']))
		{
			$theme_json['additional_files']['js'] = [];
		}
		return $theme_json;
	}

	/**
	 * Returns the theme settings as opposed to its configuration
	 *
	 * @return array The configurable settings for a theme
	 */
	public static function get_theme_settings(): array
	{
		return self::parse_section('theme_settings');
	}

	/**
	 * Returns a specific section of configuration, having fetched language strings etc.
	 *
	 * @param string $section The key from the configuration to be parsed
	 * @return array The relevant section from configuration, processed ready for use
	 */
	private static function parse_section(string $section): array
	{
		global $txt;

		$theme_json = self::get_theme_json();

		// If there's nothing here, there's nothing here.
		if (!isset($theme_json[$section]))
		{
			return [];
		}

		$theme_settings = [];
		foreach ($theme_json[$section] as $item)
		{
			if (empty($item))
			{
				// Empty item, to be used as a divider.
				$theme_settings[] = $item;
			}
			elseif (is_string($item))
			{
				// It's a bare string. Is it in $txt? If so use that, if not put the identifier.
				$theme_settings[] = isset($txt[$item]) ? $txt[$item] : $item;
			}
			elseif (is_array($item))
			{
				// It's an array so we want it probably as-is. But we need to fix a few things, like language strings.
				if (isset($item['label']) && isset($txt[$item['label']]))
				{
					$item['label'] = $txt[$item['label']];
				}
				if (isset($item['description']) && isset($txt[$item['description']]))
				{
					$item['description'] = $txt[$item['description']];
				}

				if (!empty($item['options']))
				{
					// Other dropdowns might need setting up of language strings.
					foreach ($item['options'] as $key => $value)
					{
						if (is_string($value) && isset($txt[$value]))
						{
							$item['options'][$key] = $txt[$value];
						}
					}
				}

				$theme_settings[] = $item;
			}
		}

		return $theme_settings;
	}

	/**
	 * Gets a list of themes from the system, returning an array of themes each containing a name and folder.
	 *
	 * @return array A list of themes arranged by theme id, containing name and theme_dir properties
	 */
	public static function get_theme_list(): array
	{
		global $smcFunc;
		static $cache = null;

		if ($cache !== null)
		{
			return $cache;
		}

		$request = $smcFunc['db']->query('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND variable IN ({literal:name}, {literal:theme_dir}, {literal:theme_url})',
			[
				'no_member' => 0,
			]
		);
		$cache = [];

		while ($row = $smcFunc['db']->fetch_assoc($request))
			$cache[$row['id_theme']][$row['variable']] = $row['value'];
		$smcFunc['db']->free_result($request);

		return $cache;
	}

	/**
	 * Gets a list of all fonts declared by themes.
	 *
	 * @return array An array of all fonts, key by ident in the fonts, with the array being theme name => [format => url].
	 */
	public static function get_font_list(): array
	{
		static $cache = null;

		if ($cache !== null)
		{
			return $cache;
		}

		$valid_formats = [
			'truetype' => true, // TTF fonts.
			'opentype' => true, // OTF fonts.
			'woff' => true, // Web Open Font Format (1).
			'woff2' => true, // Web Open Font Format (2).
			'embedded-opentype' => true, // EOT, mostly old IE.
			'svg' => true,
		];

		$cache = [];

		$themes = static::get_theme_list();
		foreach (array_keys($themes) as $theme_id)
		{
			// If a theme doesn't have a theme dir, abort.
			if (!isset($themes[$theme_id]['theme_dir']) || !isset($themes[$theme_id]['name']) || !isset($themes[$theme_id]['theme_url']))
			{
				continue;
			}

			// If it doesn't exist we can't do anything with it.
			if (!file_exists($themes[$theme_id]['theme_dir'] . '/theme.json'))
			{
				continue;
			}

			// Load the theme JSON, if it's missing JSON or missing a fonts section, skip.
			$json = json_decode(file_get_contents($themes[$theme_id]['theme_dir'] . '/theme.json'), true);
			if (!is_array($json) || empty($json['fonts']))
			{
				continue;
			}

			foreach (array_keys($json['fonts']) as $font_name) {
				if (!empty($json['fonts'][$font_name]['local']))
				{
					$formats = array_intersect_key($json['fonts'][$font_name]['local'], $valid_formats);

					foreach ($formats as $format => $url)
					{
						$cache[$font_name][$themes[$theme_id]['name']]['local'][$format] = strtr($url, ['$theme_url' => $themes[$theme_id]['theme_url']]);
					}
				}
			}
		}

		ksort($cache);

		return $cache;
	}

	/**
	 * Force all themes' cached CSS to be rebuilt.
	 */
	public static function clear_css_cache(): void
	{
		global $smcFunc, $cachedir;

		if (file_exists($cachedir . '/css'))
		{
			foreach (glob($cachedir . '/css/*.css') as $file)
			{
				unlink($file);
			}
		}

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}themes
			WHERE variable = {literal:compile_time}');
	}
}
