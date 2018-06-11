<?php
/**
 * This file contains background notification code for any create post action
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;
use StoryBB\Task;
use ZipArchive;
use Exception;

/**
 * Notify moderators that a post needs to be approved.
 */
class ExportData extends \StoryBB\Task\Adhoc
{
	/**
	 * This executes the task - works out which substep we're on of data export and sets off appropriately.
	 * @return bool Always returns true
	 */
	public function execute()
	{
		$steps = [
			'init_export',
			'finalise_export',
		];

		// If we don't have a current step, it's the first one we need to task.
		if (!isset($this->_details['current_step']))
		{
			$this->_details['current_step'] = $steps[0];
		}

		// If it doesn't exist, abort and return true to remove this from the queue.
		if (!in_array($this->_details['current_step'], $steps))
		{
			return true;
		}

		// Set up for exporting.
		if (!isset($this->_details['export_id']))
		{
			$this->_details['export_id'] = 0;
		}

		// Dispatch to the appropriate method.
		try {
			$method = $this->_details['current_step'];
			$this->$method();
		}
		catch (Exception $e)
		{
			// Something went wrong, log it and cancel the whole process, deleting what may have been built already.
			log_error($e->getMessage(), 'critical');

			// @todo Clean DB.
			if (!empty($this->_details['id_attach']))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}attachments
					WHERE id_attach = {int:id_attach}
					LIMIT 1',
					[
						'id_attach' => $this->_details['id_attach'],
					]
				);
			}
			if (!empty($this->_details['export_id']))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}user_exports
					WHERE id_export = {int:export_id}
					LIMIT 1',
					[
						'id_export' => $this->_details['export_id'],
					]
				);
			}

			// Clean file.
			if (!empty($this->_details['filehash']))
			{
				@unlink($this->_details['attach_folder'] . '/' . $this->_details['id_attach'] . '_' . $this->_details['filehash'] . '.dat');
			}
		}

		// Is there a next step to go to? If so, queue that up in the adhoc task list.
		$position = array_search($this->_details['current_step'], $steps);
		if (isset($steps[$position + 1]))
		{
			$options = $this->_details;
			$options['current_step'] = $steps[$position + 1];
			$options['export_id'] = $this->_details['export_id'];
			Task::queue_adhoc('StoryBB\\Task\\Adhoc\\ExportData', $options);
		}
		return true;
	}

	protected function init_export()
	{
		global $modSettings, $smcFunc;

		// Make the zip file.
		$zip = new ZipArchive;
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = smf_json_decode($modSettings['attachmentUploadDir'], true);

		$this->_details['attach_folder_id'] = $modSettings['currentAttachmentUploadDir'];
		$this->_details['attach_folder'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
		$this->_details['filename'] = 'export_data_' . $this->_details['id_member'] . '.zip';
		$this->_details['filehash'] = sha1(md5($this->_details['filename'] . time()) . mt_rand());

		// Create the attachment we're going to store this as.
		$char_id = 0;
		$request = $smcFunc['db_query']('', '
			SELECT id_character
			FROM {db_prefix}characters
			WHERE id_member = {int:member}
				AND is_main = 1',
			[
				'member' => $this->_details['id_member'],
			]
		);
		if ($smcFunc['db_num_rows']($request))
		{
			list ($char_id) = $smcFunc['db_fetch_row']($request);
		}
		$smcFunc['db_free_result']($request);

		$this->_details['id_attach'] = $smcFunc['db_insert']('',
			'{db_prefix}attachments',
			[
				'id_folder' => 'int', 'id_msg' => 'int', 'id_character' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40',
				'fileext' => 'string-8', 'size' => 'int', 'width' => 'int', 'height' => 'int', 'mime_type' => 'string-20', 'approved' => 'int',
			],
			[
				$this->_details['attach_folder_id'], 0, $char_id, $this->_details['filename'], $this->_details['filehash'],
				'.zip', 0, 0, 0, 'application/zip', 0,
			],
			['id_attach'],
			1
		);

		// Create the entry in the export table.
		$this->_details['export_id'] = $smcFunc['db_insert']('',
			'{db_prefix}user_exports',
			[
				'id_attach' => 'int', 'id_member' => 'int', 'id_requester' => 'int', 'requested_on' => 'int',
			],
			[
				$this->_details['id_attach'], $this->_details['id_member'], $this->_details['id_requester'], time(),
			],
			['id_export'],
			1
		);

		$this->_details['zipfile'] = $this->_details['attach_folder'] . '/' . $this->_details['id_attach'] . '_' . $this->_details['filehash'] . '.dat';

		if ($zip->open($this->_details['zipfile'], ZipArchive::CREATE) !== true)
		{
			throw new Exception("Could not create export data for user " . $this->_details['id_member'] . ". Permissions for the attachments folder may need to be checked.");
		}

		$zip->addEmptyDir('posts');
		$zip->addEmptyDir('account_and_characters');
		$zip->close();
	}

	protected function finalise_export()
	{
		global $smcFunc;

		// Get the file size so we can add it to the table.
		$size = @filesize($this->_details['zipfile']);

		// Update the export to indicate it is done.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}attachments
			SET approved = 1,
				size = {int:size}
			WHERE id_attach = {int:id_attach}',
			[
				'id_attach' => $this->_details['id_attach'],
				'size' => $size,
			]
		);

		// // Issue an alert to the owner to indicate they're good to go.
		// $alert_rows[] = [
		// 	'alert_time' => time(),
		// 	'id_member' => $member,
		// 	'id_member_started' => $posterOptions['id'],
		// 	'member_name' => $posterOptions['name'],
		// 	'content_type' => 'unapproved',
		// 	'content_id' => $topicOptions['id'],
		// 	'content_action' => $type,
		// 	'is_read' => 0,
		// 	'extra' => json_encode(array(
		// 		'topic' => $topicOptions['id'],
		// 		'board' => $topicOptions['board'],
		// 		'content_subject' => $msgOptions['subject'],
		// 		'content_link' => $scripturl . '?topic=' . $topicOptions['id'] . '.new;topicseen#new',
		// 	)),
		// ];

		// // Issue an alert to the requester if they aren't the owner to indicate they're good to go.
		// if ($this->_details['id_requester'] != $this->_details['id_member'])
		// {

		// }

		// // Add the alerts.
		// $smcFunc['db_insert']('',
		// 	'{db_prefix}user_alerts',
		// 	array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
		// 		'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
		// 	$alert_rows,
		// 	array()
		// );
	}
}
