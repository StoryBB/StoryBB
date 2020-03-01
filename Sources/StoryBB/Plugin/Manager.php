<?php

/**
 * This file provides functionality for managing plugins.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Plugin;

use StoryBB\Plugin\Plugin;
use StoryBB\ClassManager;

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

	public static function get_enabled_plugins(): array
	{
		global $modSettings, $plugin_cache, $cachedir;

		$pluginsdir = static::get_plugin_dir();
		$rebuild_cache = false;

		$enabled_plugins = [];

		if (!file_exists($cachedir . '/plugin_cache.php'))
		{
			$plugin_cache = static::rebuild_cache();
		}
		else
		{
			@include($cachedir . '/plugin_cache.php');
		}

		foreach (explode(',', $modSettings['enabled_plugins']) as $plugin)
		{
			// A plugin is missing, this will force a cache rebuild - and disable it in passing.
			if (!file_exists($pluginsdir . '/' . $plugin))
			{
				$rebuild_cache = true;
				continue;
			}

			// If there isn't a cache item, attempt a rebuild. If there still isn't, disable it.
			if (!isset($plugin_cache[$plugin]))
			{
				$plugin_cache = static::rebuild_cache();
				if (!isset($plugin_cache[$plugin]))
				{
					continue;
				}
			}


			$enabled_plugins[$plugin] = $plugin_cache[$plugin];
		}

		if ($rebuild_cache)
		{
			updateSettings(['enabled_plugins' => implode(',', array_keys($enabled_plugins))]);
			$enabled_plugins = static::rebuild_cache();
		}

		return $enabled_plugins;
	}

	public static function enable_plugin(Plugin $plugin)
	{
		global $context;
		if (!isset($context['enabled_plugins']))
		{
			$context['enabled_plugins'] = [];
		}

		$plugins = array_keys($context['enabled_plugins']);
		$plugins[] = $plugin->folder();

		updateSettings(['enabled_plugins' => implode(',', $plugins)]);
		static::rebuild_cache();
	}

	public static function disable_plugin(Plugin $plugin)
	{
		global $context;
		if (!isset($context['enabled_plugins']))
		{
			$context['enabled_plugins'] = [];
		}
		$plugins = array_diff(array_keys($context['enabled_plugins']), [$plugin->folder()]);

		updateSettings(['enabled_plugins' => implode(',', $plugins)]);
		static::rebuild_cache();
	}

	public static function rebuild_cache(): array
	{
		global $context, $modSettings, $cachedir;

		$pluginsdir = static::get_plugin_dir();
		$cachefile = '<?php if (!defined(\'STORYBB\')) die; ';
		$cachedata = [];

		if (!empty($modSettings['enabled_plugins']))
		{
			foreach (explode(',', $modSettings['enabled_plugins']) as $plugin_folder)
			{
				$plugin = new Plugin($pluginsdir . '/' . $plugin_folder);
				if ($plugin->installable() || !$plugin->install_errors())
				{
					$cachedata[$plugin_folder] = [
						'hooks' => static::convert_to_array($plugin->hooks()),
					];
					foreach (['sources', 'template_partials', 'templates', 'template_layouts'] as $type)
					{
						$cachedata[$plugin_folder]['has_' . $type] = file_exists($pluginsdir . '/' . $plugin_folder . '/' . $type);
					}
				}
			}
		}

		$cachefile .= '$plugin_cache = ' . var_export($cachedata, true) . ';';
		@file_put_contents($cachedir . '/plugin_cache.php', $cachefile);
		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($cachedir . '/plugin_cache.php', true);
		}

		ClassManager::rebuild_cache();

		return $cachedata;
	}

	protected static function convert_to_array($data)
	{
		if (is_object($data))
		{
			$data = get_object_vars($data);
		}
		if (is_array($data))
		{
			return array_map(['StoryBB\\Plugin\\Manager', 'convert_to_array'], $data);
		}
		else 
		{
			return $data;
		}
	}

	public static function get_plugin_sources(): array
	{
		global $context;

		$pluginsdir = static::get_plugin_dir();

		$additional_folders = [];
		foreach ($context['enabled_plugins'] as $plugin_folder => $plugin)
		{
			if (!empty($plugin['has_sources']))
			{
				$additional_folders[] = $pluginsdir . '/' . $plugin_folder . '/sources';
			}
		}

		return $additional_folders;
	}

	public static function get_plugin_template_partials(): array
	{
		global $context;

		$pluginsdir = static::get_plugin_dir();

		$additional_folders = [];
		foreach ($context['enabled_plugins'] as $plugin_folder => $plugin)
		{
			if (!empty($plugin['has_template_partials']))
			{
				$additional_folders['plugin_' . $plugin_folder] = $pluginsdir . '/' . $plugin_folder . '/template_partials';
			}
		}

		return $additional_folders;
	}

	public static function get_plugin_template_layouts(): array
	{
		global $context;

		$pluginsdir = static::get_plugin_dir();

		$additional_folders = [];
		foreach ($context['enabled_plugins'] as $plugin_folder => $plugin)
		{
			if (!empty($plugin['has_template_layouts']))
			{
				$additional_folders['plugin_' . $plugin_folder] = $pluginsdir . '/' . $plugin_folder . '/template_layouts';
			}
		}

		return $additional_folders;
	}

	public static function get_plugin_templates(): array
	{
		global $context;

		$pluginsdir = static::get_plugin_dir();

		$additional_folders = [];
		foreach ($context['enabled_plugins'] as $plugin_folder => $plugin)
		{
			if (!empty($plugin['has_templates']))
			{
				$additional_folders['plugin_' . $plugin_folder] = $pluginsdir . '/' . $plugin_folder . '/templates';
			}
		}

		return $additional_folders;
	}
}
