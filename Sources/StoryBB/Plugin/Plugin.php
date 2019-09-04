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
		];
	}

	public function folder(): string
	{
		return $this->pluginfolder;
	}

	public function name(): string
	{
		global $smcFunc;
		return $smcFunc['htmlspecialchars']($this->manifest->name);
	}

	public function author(): string
	{
		global $smcFunc;
		return $smcFunc['htmlspecialchars']($this->manifest->author);
	}

	public function description(): string
	{
		global $smcFunc;
		return $smcFunc['htmlspecialchars']($this->manifest->description);
	}

	public function version(): string
	{
		global $smcFunc;
		return $smcFunc['htmlspecialchars']($this->manifest->version);
	}
}
