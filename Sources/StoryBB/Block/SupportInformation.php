<?php

/**
 * A block for displaying versions of things in the admin.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\App;
use StoryBB\Cache;

class SupportInformation extends AbstractBlock implements Block
{
	protected $config;
	protected $content;

	public function __construct($config = [])
	{
		$this->config = $config;
	}

	public function get_name(): string
	{
		global $txt;
		return $txt['support_title'];
	}


	public function get_default_title(): string
	{
		return 'Admin/txt.support_title';
	}

	public function get_block_content(): string
	{
		global $txt, $scripturl, $smcFunc, $sourcedir, $_PHPA;

		require_once($sourcedir . '/Subs-Admin.php');
		loadLanguage('Admin');

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		$admin_news = getAdminFile('updates.json');
		$current_version = $admin_news['current_version'] ?? null;
		if ($current_version)
		{
			$needs_update = version_compare(App::SOFTWARE_VERSION, $current_version, '<');
		}
		else
		{
			$needs_update = true;
			$current_version = '??';
		}

		$versions = [
			'php' => [
				'title' => $txt['support_versions_php'],
				'version' => PHP_VERSION,
				'more' => $scripturl . '?action=admin;area=serversettings;sa=phpinfo',
			],
			'server' => [
				'title' => $txt['support_versions_server'],
				'version' => $_SERVER['SERVER_SOFTWARE'],
			],
		];

		if ($smcFunc['db']->connection_active())
		{
			$versions['db_server'] = [
				'title' => $txt['support_versions_db'],
				'version' => $smcFunc['db']->get_server() . ' ' . $smcFunc['db']->get_version(),
			];
		}

		// Is GD available?  If it is, we should show version information for it too.
		if (function_exists('gd_info'))
		{
			$temp = gd_info();
			$versions['gd'] = [
				'title' => $txt['support_versions_gd'],
				'version' => $temp['GD Version'],
			];
		}

		// Why not have a look at ImageMagick? If it's installed, we should show version information for it too.
		if (class_exists('Imagick') || function_exists('MagickGetVersionString'))
		{
			if (class_exists('Imagick'))
			{
				$temp = New Imagick;
				$temp2 = $temp->getVersion();
				$im_version = $temp2['versionString'];
				$extension_version = 'Imagick ' . phpversion('Imagick');
			}
			else
			{
				$im_version = MagickGetVersionString();
				$extension_version = 'MagickWand ' . phpversion('MagickWand');
			}

			// We already know it's ImageMagick and the website isn't needed...
			$im_version = str_replace(['ImageMagick ', ' https://www.imagemagick.org'], '', $im_version);
			$versions['imagemagick'] = ['title' => $txt['support_versions_imagemagick'], 'version' => $im_version . ' (' . $extension_version . ')'];
		}

		// Check to see if we have any accelerators installed...
		if (isset($_PHPA, $_PHPA['VERSION']))
		{
			$versions['phpa'] = [
				'title' => 'ionCube PHP-Accelerator',
				'version' => $_PHPA['VERSION'],
			];
		}

		$caches = [
			'apcu' => Cache\Apcu::class,
			'memcache' => Cache\Memcache::class,
			'memcached' => Cache\Memcached::class,
			'redis' => Cache\Redis::class,
		];

		foreach ($caches as $cache_name => $cache_class)
		{
			$cache = new $cache_class;
			if ($cache->isSupported(true))
			{
				$versions[$cache_name] = [
					'title' => $cache->getName(),
					'version' => '',
				];
				if ($client_version = $cache->getClientVersion())
				{
					$versions[$cache_name]['version'] = $client_version . ' (client)';
				}
				if ($cache->isSupported() && $server_version = $cache->getServerVersion())
				{
					$versions[$cache_name]['version'] .= ' ' . $server_version . ' (server)';
				}

				if (!trim($versions[$cache_name]['version']))
				{
					$versions[$cache_name]['version'] = '???';
				}
			}
		}

		$this->content = $this->render('block_support_information', [
			'txt' => $txt,
			'forum_version' => App::SOFTWARE_VERSION,
			'current_version' => $current_version,
			'needs_update' => $needs_update,
			'server_versions' => $versions,
		]);

		return $this->content;
	}
}
