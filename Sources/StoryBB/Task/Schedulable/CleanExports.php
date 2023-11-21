<?php
/**
 * Clean up older data exports.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

use StoryBB\Model\Attachment;

/**
 * Clean up older data exports.
 */
class CleanExports extends \StoryBB\Task\AbstractSchedulable implements \StoryBB\Task\Schedulable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	public function get_name(): string
	{
		global $txt;
		return $txt['scheduled_task_clean_exports'];
	}

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	public function get_description(): string
	{
		global $txt;
		return $txt['scheduled_task_desc_clean_exports'];
	}

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
		$request = $smcFunc['db']->query('', '
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
		while ($row = $smcFunc['db']->fetch_assoc($request))
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
		$smcFunc['db']->free_result($request);

		// Clean up dangling entries.
		if (!empty($exports))
		{
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}user_exports
				WHERE id_export IN ({array_int:exports})',
				[
					'exports' => $exports,
				]
			);
		}
		if (!empty($attachments))
		{
			$smcFunc['db']->query('', '
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
