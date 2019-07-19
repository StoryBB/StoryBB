<?php

/**
 * This class handles attachments.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles attachments.
 */
class Attachment
{
	const ATTACHMENT_STANDARD = 0;
	const ATTACHMENT_AVATAR = 1;
	const ATTACHMENT_THUMBNAIL = 3;
	const ATTACHMENT_EXPORT = 4;

	public static function get_new_filename($filename)
	{
		return sha1(md5($filename . time()) . mt_rand());
	}

	/**
	 * Get an attachment's encrypted filename. If $new is true, won't check for file existence.
	 * @todo this currently returns the hash if new, and the full filename otherwise.
	 * Something messy like that.
	 * @todo and of course everything relies on this behavior and work around it. :P.
	 * Converters included.
	 *
	 * @param string $filename The name of the file
	 * @param int $attachment_id The ID of the attachment
	 * @param string $dir Which directory it should be in (null to use current one)
	 * @param string $file_hash The file hash
	 * @return string The path to the file
	 */
	public static function get_filename($filename, $attachment_id, $dir = null, $file_hash = '')
	{
		global $modSettings, $smcFunc;

		// Just make sure that attachment id is only a int
		$attachment_id = (int) $attachment_id;

		// Grab the file hash if it wasn't added.
		// Left this for legacy.
		if ($file_hash === '')
		{
			$request = $smcFunc['db_query']('', '
				SELECT file_hash
				FROM {db_prefix}attachments
				WHERE id_attach = {int:id_attach}',
				array(
					'id_attach' => $attachment_id,
				));

			if ($smcFunc['db_num_rows']($request) === 0)
				return false;

			list ($file_hash) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		// Still no hash? mmm...
		if (empty($file_hash))
			$file_hash = sha1(md5($filename . time()) . mt_rand());

		// Are we using multiple directories?
		if (is_array($modSettings['attachmentUploadDir']))
			$path = $modSettings['attachmentUploadDir'][$dir];

		else
			$path = $modSettings['attachmentUploadDir'];

		return $path . '/' . $attachment_id . '_' . $file_hash .'.dat';
	}
}
