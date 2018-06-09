<?php

/**
 * Handles getting settings from the theme.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace StoryBB\Model;

class Theme
{
	private static function get_theme_json(): array {
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

	public static function get_defaults(): array {
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

	public static function get_theme_settings(): array {
		return self::parse_section('theme_settings');
	}

	public static function get_user_options(): array {
		return self::parse_section('user_options');
	}

	private static function parse_section(string $section): array {
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

				// Smiley sets need to be imported.
				if (isset($item['id']) && $item['id'] == 'smiley_sets_default')
				{
					$item['options'] = $context['smiley_sets'];
				}
				elseif (!empty($item['options']))
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
}
