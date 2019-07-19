<?php
/**
 * This file contains background notification code for any create post action
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;

use StoryBB\Task;
use StoryBB\Task\Adhoc\Exception\NotCompleteException;
use ZipArchive;
use Exception;
use StoryBB\Model\Attachment;

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
			'export_posts',
			'export_attachments',
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
		catch (NotCompleteException $e)
		{
			// This isn't necessarily that something went wrong but that we need to not just move onto the next step.
			// This is mostly when we're tackling posts, we want to do them in batches. This way we keep going until we run out.
			$options = $this->_details;
			if (!empty($options['step_size']))
			{
				$options['start_from'] += $options['step_size'];
			}
			$options['export_id'] = $this->_details['export_id'];
			Task::queue_adhoc('StoryBB\\Task\\Adhoc\\ExportData', $options);
			return true;
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
			// Clear up from previous tasks and gear up for future ones.
			unset ($options['start_from'], $options['step_size']);
			$options['current_step'] = $steps[$position + 1];
			$options['export_id'] = $this->_details['export_id'];
			Task::queue_adhoc('StoryBB\\Task\\Adhoc\\ExportData', $options);
		}
		return true;
	}

	/**
	 * Export process part 1: establish some metadata for the user, add it to the places in the DB we care about, create the final zip file.
	 */
	protected function init_export()
	{
		global $modSettings, $smcFunc;

		// Make the zip file.
		$zip = new ZipArchive;
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = sbb_json_decode($modSettings['attachmentUploadDir'], true);

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
				'attachment_type' => 'int',
			],
			[
				$this->_details['attach_folder_id'], 0, $char_id, $this->_details['filename'], $this->_details['filehash'],
				'.zip', 0, 0, 0, 'application/zip', 0,
				Attachment::ATTACHMENT_EXPORT,
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

	/**
	 * Export process part 2: output the account and character details we have stored.
	 */
	protected function export_characters()
	{
		global $smcFunc, $language;

		$export = [];
		$main_char = 0;

		$request = $smcFunc['db_query']('', '
			SELECT chars.id_character, chars.character_name, chars.avatar, chars.signature,
				chars.posts, chars.age, chars.date_created, chars.last_active, chars.is_main,
				a.id_attach, a.filename, a.attachment_type
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
			$row['export_folder'] = $this->_exportable_character_name($row['character_name'], (int) $row['id_character']);
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

				$avatar = $this->_include_avatar($character, $zip);
				if (!empty($avatar))
				{
					$details[] = $avatar;
				}

				if (!empty($character['signature'])) {
					$details[] = 'Signature: ' . str_replace("\n", "\r\n", $character['signature']);
				}
				$details[] = '';

				if (!empty($character['custom_fields']))
				{
					$added = false;
					foreach ($character['custom_fields'] as $name => $value)
					{
						if ($value)
						{
							$added = true;
							$details[] = $name . ': ' . $value;
						}
					}
					if ($added)
						$details[] = '';
				}

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

				$avatar = $this->_include_avatar($character, $zip);
				if (!empty($avatar))
				{
					$details[] = $avatar;
				}

				if (!empty($character['signature'])) {
					$details[] = 'Signature: ' . str_replace("\n", "\r\n", $character['signature']);
				}
				$details[] = '';
				$details[] = 'Last active: ' . date('j F Y, H:i:s', $character['last_active']);

				$zip->addEmptyDir('account_and_characters/' . $character['export_folder'] . '/sheets');
			}

			$zip->addFromString('account_and_characters/' . $character['export_folder'] . '/details.txt', implode("\r\n", $details));
		}

		// Fetch character sheet versions, and export those too.
		$request = $smcFunc['db_query']('', '
			SELECT id_character, created_time, sheet_text
			FROM {db_prefix}character_sheet_versions
			WHERE id_member = {int:member}
			ORDER BY id_version',
			[
				'member' => $this->_details['id_member'],
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($exports[$row['id_character']]))
			{
				continue;
			}

			$row['sheet_text'] = str_replace("\n", "\r\n", $row['sheet_text']);
			$character_sheet_path = 'account_and_characters/' . $exports[$row['id_character']]['export_folder'] . '/sheets/';
			$character_sheet_filename = date('Y-m-d H-i-s', $row['created_time']) . '.txt';
			$zip->addFromString($character_sheet_path . $character_sheet_filename, $row['sheet_text']);
		}
		$smcFunc['db_free_result']($request);

		$zip->close();
	}

	/**
	 * Export process part 3: output posts in batches
	 */
	protected function export_posts()
	{
		global $smcFunc;

		// We want to move things in batches. This is how many to export in a single step. The main loop will handle this for us.
		// We primarily want to do it in batches because 100 posts is potentially a lot for the DB to chug at once.
		$this->_details['step_size'] = 100;
		if (!isset($this->_details['start_from']))
		{
			$this->_details['start_from'] = 0;
		}

		// Query for posts.
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.id_character, m.poster_time, m.id_topic, t.id_board, b.name AS board_name, m.subject, m.body,
				m.modified_time, m.modified_name, m.modified_reason, chars.character_name
			FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}characters AS chars ON (m.id_character = chars.id_character)
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			WHERE m.id_creator = {int:member}
			ORDER BY m.id_msg
			LIMIT {int:start}, {int:step_size}',
			[
				'member' => $this->_details['id_member'],
				'start' => $this->_details['start_from'],
				'step_size' => $this->_details['step_size'],
			]
		);
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			// Nothing to do?
			$smcFunc['db_free_result']($request);
			return true;
		}

		$zip = new ZipArchive;
		if ($zip->open($this->_details['zipfile']) !== true)
		{
			throw new Exception("Could not open export data for user " . $this->_details['id_member'] . ". Permissions for the attachments folder may need to be checked.");
		}

		// Now begin the export.
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$content = 'Board: ' . $row['board_name'] . "\r\n";
			$content .= 'Topic: ' . $row['subject'] . "\r\n";
			$content .= 'Posted by: ' . $row['character_name'] . "\r\n";
			$content .= 'Posted on: ' . date('j F Y, H:i:s', $row['poster_time']) . "\r\n";
			if (!empty($row['modified_time']))
			{
				$content .= 'Last modified on: ' . date('j F Y, H:i:s', $row['modified_time']);
				if (!empty($row['modified_name']))
				{
					$content .= ' by ' . $row['modified_name'];
				}
				if (!empty($row['modified_reason']))
				{
					$content .= ' (reason: ' . $row['modified_reason'] . ')';
				}
				$content .= "\r\n";
			}
			$content .= "\r\n";
			$content .= html_entity_decode(str_replace('<br>', "\r\n", $row['body']), ENT_QUOTES);

			$path = 'posts/';
			$path .= $this->_exportable_character_name($row['character_name'], (int) $row['id_character']) . '/';
			$path .= 'board_' . $row['id_board'] . '/';
			$path .= 'topic_' . $row['id_topic'] . '/';
			$path .= 'msg_' . $row['id_msg'] . '.txt';
			$zip->addFromString($path, $content);
		}
		$smcFunc['db_free_result']($request);

		// And back to the start.
		$zip->close();
		throw new NotCompleteException;
	}

	/**
	 * Export process part 4: output attachments in batches
	 */
	protected function export_attachments()
	{
		global $smcFunc, $modSettings;

		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = sbb_json_decode($modSettings['attachmentUploadDir'], true);

		// We want to move things in batches. This is how many to export in a single step. The main loop will handle this for us.
		// We primarily want to do it in batches because ZipArchive has hidden limits on moving files.
		$this->_details['step_size'] = 25;
		if (!isset($this->_details['start_from']))
		{
			$this->_details['start_from'] = 0;
		}

		// Query for posts.
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.id_character, m.id_topic, t.id_board, chars.character_name,
				a.id_attach, a.filename, a.file_hash, a.id_folder
			FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}characters AS chars ON (m.id_character = chars.id_character)
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
			INNER JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg)
			WHERE m.id_member = {int:member}
				AND a.attachment_type = {int:attachment}
			ORDER BY a.id_attach
			LIMIT {int:start}, {int:step_size}',
			$data = [
				'member' => $this->_details['id_member'],
				'attachment' => 0,
				'start' => $this->_details['start_from'],
				'step_size' => $this->_details['step_size'],
			]
		);
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			// Nothing to do?
			$smcFunc['db_free_result']($request);
			return true;
		}

		$zip = new ZipArchive;
		if ($zip->open($this->_details['zipfile']) !== true)
		{
			throw new Exception("Could not open export data for user " . $this->_details['id_member'] . ". Permissions for the attachments folder may need to be checked.");
		}

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$path = 'posts/';
			$path .= $this->_exportable_character_name($row['character_name'], (int) $row['id_character']) . '/';
			$path .= 'board_' . $row['id_board'] . '/';
			$path .= 'topic_' . $row['id_topic'] . '/';
			$path .= 'msg_' . $row['id_msg'] . '_' . $row['id_attach'] . '_' . iconv('UTF-8', 'ASCII//TRANSLIT', $row['filename']);

			if (!isset($modSettings['attachmentUploadDir'][$row['id_folder']]))
			{
				continue;
			}
			$sourcefile = $modSettings['attachmentUploadDir'][$row['id_folder']] . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.dat';

			$zip->addFile($sourcefile, $path);
		}
		$smcFunc['db_free_result']($request);

		// And back to the start.
		$zip->close();
		throw new NotCompleteException;
	}

	/**
	 * Given a character name, convert it to a zipfile-safe folder name.
	 * @param string $character_name The character name as raw UTF-8
	 * @param string $id_character The character ID for a fallback folder name, or as a disambiguator
	 * @return string Zipfile-safe folder name
	 */
	private function _exportable_character_name(string $character_name, int $id_character): string
	{
		$export_folder = iconv('UTF-8', 'ASCII//TRANSLIT', html_entity_decode($character_name, ENT_QUOTES, 'UTF-8'));
		$export_folder = str_replace('"', "''", $export_folder);
		$export_folder = preg_replace('/[^a-z0-9\'\- ]/i', '', $export_folder);
		if (empty($export_folder))
		{
			$export_folder = 'character_' . $id_character;
		}
		else
		{
			$export_folder .= '_' . $id_character;
		}
		return $export_folder;
	}

	/**
	 * Given an array of character details, process out the avatar, including it in the ZIP
	 * if appropriate, and return the string for the details.txt file.
	 * @param array $character The character currently being processed.
	 * @param ZipArchive $zip The zip object being manipulated.
	 * @return string The entry for the details.txt file, or empty string if no avatar.
	 */
	private function _include_avatar(array $character, ZipArchive $zip): string
	{
		global $modSettings;

		if (!empty($character['avatar']) && (stristr($character['avatar'], 'http://') || stristr($character['avatar'], 'https://')))
		{
			return 'Avatar is from a link: ' . $character['avatar'];
		}
		elseif (!empty($character['filename'])) {
			$zip->addFile($modSettings['custom_avatar_dir'] . '/' . $character['filename'], 'account_and_characters/' . $character['export_folder'] . '/' . $character['filename']);
			return 'Avatar was uploaded to the site, included here as ' . $character['filename'];
		}

		return '';
	}

	/**
	 * Data export last part: mark the attachment as ready to go, notify the owner that there is a new data export for them.
	 */
	protected function finalise_export()
	{
		global $smcFunc;

		// Get the file size so we can add it to the table.
		clearstatcache($this->_details['zipfile']);
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

		$export_link = '?action=profile;area=export_data;u=' . $this->_details['id_member'];

		$request = $smcFunc['db_query']('', '
			SELECT real_name
			FROM {db_prefix}members
			WHERE id_member = {int:member}',
			[
				'member' => $this->_details['id_member'],
			]
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Issue an alert to the owner to indicate they're good to go.
		$alert_rows[] = [
			'alert_time' => time(),
			'id_member' => $this->_details['id_member'],
			'id_member_started' => $this->_details['id_member'],
			'member_name' => $row['real_name'],
			'content_type' => 'member',
			'content_id' => $this->_details['id_member'],
			'content_action' => 'export_complete',
			'is_read' => 0,
			'extra' => json_encode(array(
				'export_link' => $export_link,
			)),
		];

		// Issue an alert to the requester if they aren't the owner to indicate they're good to go.
		if ($this->_details['id_requester'] != $this->_details['id_member'])
		{
			$alert_rows[] = [
				'alert_time' => time(),
				'id_member' => $this->_details['id_requester'],
				'id_member_started' => $this->_details['id_member'],
				'member_name' => $row['real_name'],
				'content_type' => 'member',
				'content_id' => $this->_details['id_member'],
				'content_action' => 'export_complete_admin',
				'is_read' => 0,
				'extra' => json_encode(array(
					'export_link' => $export_link,
				)),
			];
		}

		// Add the alerts.
		$smcFunc['db_insert']('',
			'{db_prefix}user_alerts',
			array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
			$alert_rows,
			[]
		);

		updateMemberData($this->_details['id_member'], array('alerts' => '+'));
		if ($this->_details['id_requester'] != $this->_details['id_member'])
			updateMemberData($this->_details['id_requester'], array('alerts' => '+'));
	}
}
