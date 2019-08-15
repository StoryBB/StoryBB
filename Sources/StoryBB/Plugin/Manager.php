<?php

/**
 * This file provides functionality for managing plugins.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Plugin;

use StoryBB\Plugin\Plugin;

class Manager
{
	public static function get_plugin_dir(): string
	{
		global $boarddir;

		return $boarddir . '/Plugins';
	}

	public static function get_available_plugins(): array
	{
		$pluginsdir = static::get_plugin_dir();

		$all_plugins = [];

		if (empty($pluginsdir))
		{
			return $all_plugins;
		}

		if ($handle = opendir($pluginsdir))
		{
			while (($folder = readdir($handle)) !== false)
			{
				if ($folder[0] == '.' || strpos($folder, ',') !== false)
				{
					continue;
				}

				if (filetype($pluginsdir . '/' . $folder) == 'dir')
				{
					$all_plugins[$folder] = new Plugin($pluginsdir . '/' . $folder);
				}
			}
			closedir($handle);
		}

		return $all_plugins;
	}
}
