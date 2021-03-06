<?php

/**
 * This file provides functionality for managing plugins.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Plugin;

use stdClass;
use StoryBB\StringLibrary;
use StoryBB\Hook\Mutatable;
use StoryBB\Hook\Observable;

class Plugin
{
	private $path = '';
	private $pluginfolder = '';
	private $install_errors = [];
	private $manifest = false;

	public function __construct($path)
	{
		$this->path = $path;
		$this->pluginfolder = basename($path);

		if (!file_exists($path . '/plugin.json'))
		{
			$this->install_errors[] = 'no_manifest';
			return;
		}

		$rawmanifest = file_get_contents($path . '/plugin.json');
		$this->manifest = @json_decode($rawmanifest);
		if (empty($this->manifest))
		{
			$this->set_default_manifest();
			$this->install_errors[] = 'invalid_manifest';
			return;
		}

		if (empty($this->manifest->plugin))
		{
			$this->set_default_manifest();
			$this->install_errors[] = 'manifest_invalid_id';
			return;
		}
		if ($this->manifest->plugin != $this->pluginfolder)
		{
			$this->set_default_manifest();
			$this->install_errors[] = 'invalid_plugin_path';
			return;
		}

		if (empty($this->manifest->author))
		{
			$this->install_errors[] = 'manifest_no_author';
			$this->manifest->author = '???';
		}
		if (empty($this->manifest->name))
		{
			$this->install_errors[] = 'manifest_no_name';
			$this->manifest->name = $this->pluginfolder;
		}

		if (empty($this->manifest->version))
		{
			$this->install_errors[] = 'manifest_no_version';
			$this->manifest->version = '???';
		}

		if (empty($this->manifest->description))
		{
			$this->manifest->description = '';
		}

		if (!empty($this->manifest->hooks) && is_object($this->manifest->hooks))
		{
			foreach (get_object_vars($this->manifest->hooks) as $hook_class => $hook_details)
			{
				if (!class_exists($hook_class))
				{
					$this->install_errors[] = 'missing_hook';
					break;
				}

				if (!is_subclass_of($hook_class, Observable::class) && !is_subclass_of($hook_class, Mutatable::class))
				{
					$this->install_errors[] = 'invalid_hook_type';
					break;
				}

				if (empty($hook_details->callable))
				{
					$this->install_errors[] = 'missing_hook_callable';
					break;
				}

				if (empty($hook_details->priority))
				{
					$this->manifest->hooks->$hook_class->priority = 50;
				}
			}
		}
	}

	public function installable(): bool
	{
		return empty($this->install_errors) && !$this->enabled();
	}

	public function install_errors(): array
	{
		return $this->install_errors;
	}

	public function enabled(): bool
	{
		global $context;
		return isset($context['enabled_plugins'][$this->pluginfolder]);
	}

	protected function set_default_manifest()
	{
		$this->manifest = (object) [
			'name' => $this->pluginfolder,
			'plugin' => $this->pluginfolder,
			'author' => '???',
			'version' => '???',
			'description' => '',
		];
	}

	public function folder(): string
	{
		return $this->pluginfolder;
	}

	public function name(): string
	{
		return StringLibrary::escape($this->manifest->name);
	}

	public function author(): string
	{
		return StringLibrary::escape($this->manifest->author);
	}

	public function description(): string
	{
		return StringLibrary::escape($this->manifest->description);
	}

	public function version(): string
	{
		return StringLibrary::escape($this->manifest->version);
	}

	public function hooks(): stdClass
	{
		return !empty($this->manifest->hooks) ? $this->manifest->hooks : new stdClass;
	}
}
