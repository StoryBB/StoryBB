<?php

/**
 * Handles caching for the template layer.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace StoryBB\Template;

class Cache
{
	public static function is_enabled(): bool {
		global $modSettings;
		return empty($modSettings['debug_templates']);
	}

	public static function fetch(string $cache_id = '') {
		global $cachedir;

		if (empty($cache_id) || !self::is_enabled())
		{
			return '';
		}

		if (file_exists($cachedir . '/template-' . $cache_id . '.php'))
		{
			$phpStr = @include($cachedir . '/template-' . $cache_id . '.php');
			if (!empty($phpStr))
			{
				return $phpStr;
			}
		}

		return '';
	}

	public static function push(string $cache_id, string $phpStr): bool {
		global $cachedir;

		if (empty($cache_id) || !self::is_enabled())
		{
			return false;
		}

		return file_put_contents($cachedir . '/template-' . $cache_id . '.php', '<?php ' . $phpStr) > 0;
	}

	/**
	 * Cleans the template cache.

	 */
	public static function clean()
	{
		global $cachedir;

		$dh = opendir($cachedir);
		while ($file = readdir($dh))
		{
			if (strpos($file, 'template') === 0)
				@unlink($cachedir . '/' . $file);
		}
		closedir($dh);
		clearstatcache();
	}
}

?>