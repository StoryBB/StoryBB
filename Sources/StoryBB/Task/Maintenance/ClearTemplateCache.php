<?php

/**
 * Clears the language cache.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Maintenance;

use StoryBB\App;
use StoryBB\Helper\FileOperations;
use StoryBB\Phrase;

/**
 * This task handles notifying users when something is liked.
 */
class ClearTemplateCache implements MaintenanceTask
{
	public function get_name(): Phrase
	{
		return new Phrase('ManageMaintenance:maintain_template_cache');
	}

	public function get_description(): Phrase
	{
		return new Phrase('ManageMaintenance:maintain_template_cache_info');
	}

	public function execute(): bool
	{
		$cachedir = App::container()->get('cachedir');

		// First, clear the legacy cache.
		$dh = opendir($cachedir);
		while (($file = readdir($dh)) !== false)
		{
			if (strpos($file, 'template-') === 0)
			{
				@unlink($cachedir . '/' . $file);
			}
		}
		closedir($dh);

		// Seocnd, clear the not-so-legacy cache.
		if (file_exists($cachedir . '/template'))
		{
			$dh = opendir($cachedir . '/template');
			while (($file = readdir($dh)) !== false)
			{
				if ($file !== '.' && $file !== '..')
				{
					FileOperations::deltree($cachedir . '/template/' . $file);
				}
			}
			closedir($dh);
		}

		clearstatcache();

		return true;
	}
}
