<?php
/**
 * Check for un-posted attachments and remove them.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Check for un-posted attachments and remove them.
 */
class RemoveTempAttachments extends \StoryBB\Task\Schedulable
{
	/**
	 * Check for un-posted attachments and remove them.
	 * This function uses opendir cycling through all the attachments
	 *
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $modSettings, $context, $txt;

		// We need to know where this thing is going.
		if (!empty($modSettings['currentAttachmentUploadDir']))
		{
			if (!is_array($modSettings['attachmentUploadDir']))
				$modSettings['attachmentUploadDir'] = sbb_json_decode($modSettings['attachmentUploadDir'], true);

			// Just use the current path for temp files.
			$attach_dirs = $modSettings['attachmentUploadDir'];
		}
		else
		{
			$attach_dirs = array($modSettings['attachmentUploadDir']);
		}

		foreach ($attach_dirs as $attach_dir)
		{
			$dir = @opendir($attach_dir);
			if (!$dir)
			{
				loadEssentialThemeData();
				loadLanguage('Post');
				$context['scheduled_errors']['remove_temp_attachments'][] = $txt['cant_access_upload_path'] . ' (' . $attach_dir . ')';
				log_error($txt['cant_access_upload_path'] . ' (' . $attach_dir . ')', 'critical');
				return false;
			}

			while ($file = readdir($dir))
			{
				if ($file == '.' || $file == '..')
					continue;

				if (strpos($file, 'post_tmp_') !== false)
				{
					// Temp file is more than 5 hours old!
					if (filemtime($attach_dir . '/' . $file) < time() - 18000)
						@unlink($attach_dir . '/' . $file);
				}
			}
			closedir($dir);
		}

		return true;
	}
}
