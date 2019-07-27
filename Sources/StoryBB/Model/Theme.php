<?php

/**
 * Handles processing for a theme.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
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
	 * Returns the user preferences from a theme
	 *
	 * @return array The user-preferences from the theme
	 */
	public static function get_user_options(): array
	{
		return self::parse_section('user_options');
	}

	/**
	 * Returns a specific section of configuration, having fetched language strings etc.
	 *
	 * @param string $section The key from the configuration to be parsed
	 * @return array The relevant section from configuration, processed ready for use
	 */
	private static function parse_section(string $section): array
	{
		global $txt, $context;

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
		global $smcFunc, $settings;
		static $cache = null;

		if ($cache !== null)
		{
			return $cache;
		}

		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND variable IN ({string:name}, {string:theme_dir})',
			array(
				'no_member' => 0,
				'name' => 'name',
				'theme_dir' => 'theme_dir',
			)
		);
		$cache = [];

		while ($row = $smcFunc['db_fetch_assoc']($request))
			$cache[$row['id_theme']][$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);

		return $cache;
	}
}
