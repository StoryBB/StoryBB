<?php

/**
 * Handles processing for multiple themes.
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

class ThemeManager
{
	use Database;

	protected $theme_list = null;
	protected $font_list = null;

	/**
	 * Gets a list of themes from the system, returning an array of themes each containing a name and folder.
	 *
	 * @return array A list of themes arranged by theme id, containing name and theme_dir properties
	 */
	public function get_theme_list(): array
	{
		if ($this->theme_list === null)
		{
			$db = $this->db();
			$request = $db->query('', '
				SELECT id_theme, variable, value
				FROM {db_prefix}themes
				WHERE id_member = {int:no_member}
					AND variable IN ({literal:name}, {literal:theme_dir}, {literal:theme_url})',
				[
					'no_member' => 0,
				]
			);
			$this->theme_list = [];

			while ($row = $db->fetch_assoc($request))
			{
				$this->theme_list[$row['id_theme']][$row['variable']] = $row['value'];
			}
			$db->free_result($request);
		}

		return $this->theme_list;
	}

	/**
	 * Gets a list of all fonts declared by themes.
	 *
	 * @return array An array of all fonts, key by ident in the fonts, with the array being theme name => [format => url].
	 */
	public function get_font_list(): array
	{
		if ($this->font_list !== null)
		{
			return $this->font_list;
		}

		$valid_formats = [
			'truetype' => true, // TTF fonts.
			'opentype' => true, // OTF fonts.
			'woff' => true, // Web Open Font Format (1).
			'woff2' => true, // Web Open Font Format (2).
			'embedded-opentype' => true, // EOT, mostly old IE.
			'svg' => true,
		];

		$this->font_list = [];

		$themes = $this->get_theme_list();
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
						$this->font_list[$font_name][$themes[$theme_id]['name']]['local'][$format] = strtr($url, ['$theme_url' => $themes[$theme_id]['theme_url']]);
					}
				}
			}
		}

		ksort($this->font_list);

		return $this->font_list;
	}

	/**
	 * Force all themes' cached CSS to be rebuilt.
	 */
	public function clear_css_cache(): void
	{
		$cachedir = App::container()->get('cachedir');

		if (file_exists($cachedir . '/css'))
		{
			foreach (glob($cachedir . '/css/*.css') as $file)
			{
				unlink($file);
			}
		}

		$this->db()->query('', '
			DELETE FROM {db_prefix}themes
			WHERE variable = {literal:compile_time}');
	}
}
