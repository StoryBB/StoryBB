<?php

/**
 * Clears the file cache.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Maintenance;

use StoryBB\App;
use StoryBB\Cache;
use StoryBB\Phrase;

/**
 * This task handles notifying users when something is liked.
 */
class ClearFileCache implements MaintenanceTask
{
	public function get_name(): Phrase
	{
		return new Phrase('ManageMaintenance:maintain_cache');
	}

	public function get_description(): Phrase
	{
		return new Phrase('ManageMaintenance:maintain_cache_info');
	}

	public function execute(): bool
	{
		$cachedir = App::container()->get('cachedir');

		// First, clear the legacy cache.
		$dh = opendir($cachedir);
		while (($file = readdir($dh)) !== false)
		{
			if (strpos($file, 'data_') === 0 || strpos($file, 'compiled_') === 0 || $file == 'class_cache.php' || $file == 'plugin_cache.php')
			{
				@unlink($cachedir . '/' . $file);
			}
		}
		closedir($dh);

		clearstatcache();

		return true;
	}
}
