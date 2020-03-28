<?php

/**
 * Support functions for installation.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use StoryBB\App;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\Filesystem;

/**
 * Support functions for managing files.
 */
class Installer
{
	use Database;
	use Filesystem;

	public function upload_favicon()
	{
		$db = $this->db();
		$filesystem = $this->filesystem();

		$filesystem->copy_physical_file(App::get_root_path() . '/install_resources/favicon.ico', 'favicon.ico', 'image/x-icon', 'favicon', 0);
	}

	public function upload_smileys()
	{
		$db = $this->db();
		$filesystem = $this->filesystem();
		$smileydir = App::get_root_path() . '/Smileys';

		// First, get the smileys.
		$request = $db->query('', '
			SELECT id_smiley, filename
			FROM {db_prefix}smileys
			ORDER BY id_smiley');

		$mimetypes = [
			'gif' => 'image/gif',
			'jpg' => 'image/jpeg',
			'png' => 'image/png',
			'svg' => 'image/svg+xml',
		];

		while ($row = $db->fetch_assoc($request))
		{
			$ext = strtolower(substr(strrchr($row['filename'], '.'), 1));
			$mimetype = $mimetypes[$ext];
			$filesystem->copy_physical_file($smileydir . '/' . $row['filename'], $row['filename'], $mimetype, 'smiley', $row['id_smiley']);
		}
	}
}
