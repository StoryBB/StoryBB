<?php

/**
 * A library for performing filesystem operations.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

class FileOperations
{
	/**
	 * Deletes a directory, and all the files and direcories inside it.
	 * requires access to delete these files.
	 *
	 * @param string $dir A directory
	 * @param bool $delete_dir If false, only deletes everything inside the directory but not the directory itself
	 */
	public static function deltree(string $dir, bool $delete_dir = true): void
	{
		if (!file_exists($dir))
		{
			return;
		}

		$current_dir = @opendir($dir);
		if ($current_dir == false)
		{
			return;
		}

		while ($entryname = readdir($current_dir))
		{
			if (in_array($entryname, ['.', '..']))
			{
				continue;
			}

			if (is_dir($dir . '/' . $entryname))
			{
				static::deltree($dir . '/' . $entryname);
			}
			else
			{
				if (!is_writable($dir . '/' . $entryname))
				{
					static::make_writable($dir . '/' . $entryname, 0777);
				}
				@unlink($dir . '/' . $entryname);
			}
		}

		closedir($current_dir);

		if ($delete_dir)
		{
			if (!is_writable($dir))
			{
				static::make_writable($dir, 0777);
			}
			@rmdir($dir);
		}
	}

	/**
	 * Tries different modes to make file/dirs writable. Wrapper function for chmod()
	 * @param string $file The file/dir full path.
	 * @return boolean  true if the file/dir is already writable or the function was able to make it writable, false if the function couldn't make the file/dir writable.
	 */
	public static function make_writable(string $file): bool
	{
		// No file? no checks!
		if (empty($file))
		{
			return false;
		}

		// Already writable?
		if (is_writable($file))
		{
			return true;
		}

		// Do we have a file or a dir?
		$isDir = is_dir($file);
		$isWritable = false;

		// Set different modes.
		$chmodValues = $isDir ? [0750, 0755, 0775, 0777] : [0644, 0664, 0666];

		foreach ($chmodValues as $val)
		{
			// If it's writable, break out of the loop.
			if (is_writable($file))
			{
				$isWritable = true;
				break;
			}
			else
			{
				@chmod($file, $val);
			}
		}

		return $isWritable;
	}
}
