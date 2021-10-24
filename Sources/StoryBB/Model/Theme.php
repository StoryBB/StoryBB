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

use StoryBB\App;
use StoryBB\Dependency\Database;
use InvalidArgumentException;

/**
 * Handles processing for a theme.
 */
class Theme
{
	use Database;

	protected $theme_id = 0;

	protected $settings = null;

	public function __construct(?int $theme_id = null)
	{
		if ($theme_id)
		{
			$this->theme_id = $theme_id;
		}
		else
		{
			$this->theme_id = App::container()->get('current_theme_id');
		}		
	}

	public function __get($key)
	{
		if ($this->settings === null)
		{
			$this->load_theme_details();
		}
		if (!isset($this->settings[$key]))
		{
			throw new InvalidArgumentException('Invalid key ' . $key);
		}

		return $this->settings[$key];
	}

	public function get_compiled_time(string $scssfile): int
	{
		if ($this->settings === null)
		{
			$this->load_theme_details();
		}
		return isset($this->settings['compile_time_' . $scssfile]) ? (int) $this->settings['compile_time_' . $scssfile] : 0;
	}

	protected function load_theme_details()
	{
		$db = $this->db();

		$this->settings = [
			'id' => $this->theme_id,
		];

		$request = $db->query('', '
			SELECT variable, value
			FROM {db_prefix}themes
			WHERE id_member = 0
				AND id_theme = {int:id_theme}',
			[
				'id_theme' => $this->theme_id,
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$this->settings[$row['variable']] = $row['value'];
		}
		$db->free_result($request);
	}

	/**
	 * Gets the theme's JSON configuration file
	 *
	 * @return array Return the current theme's configuration settings
	 */
	private function get_theme_json(): array
	{
		if (file_exists($this->settings['theme_dir'] . '/theme.json'))
		{
			$theme_settings = file_get_contents($this->settings['theme_dir'] . '/theme.json');
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
	public function get_defaults(): array
	{
		$theme_json = $this->get_theme_json();
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
	public function get_theme_settings(): array
	{
		return self::parse_section('theme_settings');
	}

	/**
	 * Returns a specific section of configuration, having fetched language strings etc.
	 *
	 * @param string $section The key from the configuration to be parsed
	 * @return array The relevant section from configuration, processed ready for use
	 */
	private function parse_section(string $section): array
	{
		global $txt;

		$theme_json = $this->get_theme_json();

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
}
