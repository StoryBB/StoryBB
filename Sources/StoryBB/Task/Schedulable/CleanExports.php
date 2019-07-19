<?php
/**
 * Clean up older data exports.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

use StoryBB\Model\Attachment;

/**
 * Clean up older data exports.
 */
class CleanExports extends \StoryBB\Task\Schedulable
{
	/**
	 * Clean up older data exports.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $smcFunc, $modSettings;

		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = sbb_json_decode($modSettings['attachmentUploadDir'], true);

		// Identify which files are out of date.
		$request = $smcFunc['db_query']('', '
			SELECT a.id_attach, a.filename, a.file_hash, a.id_folder, ue.id_export
			FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}user_exports AS ue ON (ue.id_attach = a.id_attach)
			WHERE attachment_type = {int:export}
				AND ue.requested_on < {int:expired_timestamp}',
			[
				'export' => Attachment::ATTACHMENT_EXPORT,
				'expired_timestamp' => time() - (86400 * 7),
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Log all the ids.
			$exports[] = $row['id_export'];
			$attachments[] = $row['id_attach'];

			// Prune the files.
			if (!isset($modSettings['attachmentUploadDir'][$row['id_folder']]))
			{
				continue;
			}

			@unlink($modSettings['attachmentUploadDir'][$row['id_folder']] . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.dat');
		}
		$smcFunc['db_free_result']($request);

		// Clean up dangling entries.
		if (!empty($exports))
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}user_exports
				WHERE id_export IN ({array_int:exports})',
				[
					'exports' => $exports,
				]
			);
		}
		if (!empty($attachments))
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}attachments
				WHERE id_attach IN ({array_int:attachments})',
				[
					'attachments' => $attachments,
				]
			);
		}

		// All done.
		return true;
	}
}
