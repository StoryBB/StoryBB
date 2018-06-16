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
			'export_characters',
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

	protected function export_characters()
	{
		global $smcFunc, $language;

		$export = [];
		$main_char = 0;

		$request = $smcFunc['db_query']('', '
			SELECT chars.id_character, chars.character_name, chars.avatar, chars.signature, chars.id_theme,
				chars.posts, chars.age, chars.date_created, chars.last_active, chars.is_main,
				chars.main_char_group, chars.char_groups, a.id_attach, a.filename, a.attachment_type
			FROM {db_prefix}characters AS chars
			LEFT JOIN {db_prefix}attachments AS a ON (chars.id_character = a.id_character AND a.attachment_type = 1)
			WHERE chars.id_member = {int:member}',
			[
				'member' => $this->_details['id_member'],
			]
		);

		// @todo appropriate setlocale call here

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['is_main'])
			{
				$main_char = $row['id_character'];
			}

			$row['custom_fields'] = [];

			// Work out a suitable location for the files.
			$row['export_folder'] = iconv('UTF-8', 'ASCII//TRANSLIT', html_entity_decode($row['character_name'], ENT_QUOTES, 'UTF-8'));
			$row['export_folder'] = str_replace('"', "''", $row['export_folder']);
			$row['export_folder'] = preg_replace('/[^a-z0-9\'\- ]/i', '', $row['export_folder']);
			if (empty($row['export_folder']))
			{
				$row['export_folder'] = 'character_' . $row['id_character'];
			}
			else
			{
				$row['export_folder'] .= '_' . $row['id_character'];
			}
			$exports[$row['id_character']] = $row;
		}
		$smcFunc['db_free_result']($request);

		// Pull the rest of the stuff out of the members table.
		if (!empty($main_char))
		{
			$request = $smcFunc['db_query']('', '
				SELECT member_name, date_registered, immersive_mode, lngfile, last_login,
					email_address, birthdate, website_title, website_url, signature,
					member_ip, member_ip2, secret_question, total_time_logged_in, timezone
				FROM {db_prefix}members
				WHERE id_member = {int:member}',
				[
					'member' => $this->_details['id_member'],
				]
			);
			$row = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			$row['member_ip'] = inet_dtop($row['member_ip']);
			$row['member_ip2'] = inet_dtop($row['member_ip2']);
			$exports[$main_char] += $row;
		}

		// Add custom fields to the account entry.
		if (!empty($main_char))
		{
			$request = $smcFunc['db_query']('', '
				SELECT cf.field_name, th.value
				FROM {db_prefix}custom_fields AS cf
				INNER JOIN {db_prefix}themes AS th ON (th.id_member = {int:member} AND th.variable = cf.col_name)
				WHERE cf.private < {int:admin_only}
				ORDER BY cf.field_order',
				[
					'member' => $this->_details['id_member'],
					'admin_only' => 3,
				]
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$exports[$main_char]['custom_fields'][$row['field_name']] = $row['value'];
			}
		}

		$zip = new ZipArchive;
		if ($zip->open($this->_details['zipfile']) !== true)
		{
			throw new Exception("Could not open export data for user " . $this->_details['id_member'] . ". Permissions for the attachments folder may need to be checked.");
		}

		// Export the data we have to the archive.
		foreach ($exports as $id_character => $character)
		{
			$zip->addEmptyDir('account_and_characters/' . $character['export_folder']);

			$details = [];
			if ($character['is_main'])
			{
				$details[] = 'Username: ' . $character['member_name'];
				$details[] = 'Account name: ' . $character['character_name'];
				$details[] = 'Date registered: ' . date('j F Y, H:i:s', $character['date_registered']);
				$details[] = 'Email address: ' . $character['email_address'];
				if (!empty($character['signature'])) {
					$details[] = 'Signature: ' . str_replace("\n", "\r\n", $character['signature']);
				}
				$details[] = '';
				$details[] = 'Immersive mode: ' . ($character['immersive_mode'] ? 'Yes' : 'No');
				$details[] = 'Language: ' . (!empty($character['lngfile']) ? $character['lngfile'] : $language);
				$details[] = 'Last login: ' . date('j F Y, H:i:s', $character['last_login']);
				$details[] = 'Primary IP address: ' . $character['member_ip'];
				if ($character['member_ip'] != $character['member_ip2']) {
					$details[] = 'Secondary IP address: ' . $character['member_ip2'];
				}
				if (!empty($character['secret_question'])) {
					$details[] = 'Secret question: ' . $character['secret_question'];
				}
				if (!empty($character['website_url'])) {
					$details[] = 'Website: ' . $character['website_title'] . ' - ' . $character['website_url'];
				}
				$details[] = 'Timezone: ' . $character['timezone'];
				$total_time_logged_in = '';
				if ($character['total_time_logged_in'] > 86400) {
					$total_time_logged_in .= floor($character['total_time_logged_in'] / 86400) . ' days';
					$character['total_time_logged_in'] = $character['total_time_logged_in'] % 86400;
				}
				if ($character['total_time_logged_in'] > 3600) {
					$total_time_logged_in .= (!empty($total_time_logged_in) ? ', ' : '') . floor($character['total_time_logged_in'] / 3600) . ' hours';
					$character['total_time_logged_in'] = $character['total_time_logged_in'] % 3600;
				}
				$total_time_logged_in .= (!empty($total_time_logged_in) ? ', ' : '') . round($character['total_time_logged_in'] / 60) . ' minutes';
				$details[] = 'Total time logged in: ' . $total_time_logged_in;
			}
			else
			{
				$details[] = 'Character name: ' . $character['character_name'];
				$details[] = 'Created on: ' . date('j F Y, H:i:s', $character['date_created']);
			}

			$zip->addFromString('account_and_characters/' . $character['export_folder'] . '/details.txt', implode("\r\n", $details));
		}

		// @todo Fetch character sheet versions, and export those too.

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
