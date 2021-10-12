<?php

/**
 * This file handles avatar and attachment requests. The whole point of this file is to reduce the loaded stuff to show an image.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\Model\Attachment;

/**
 * Downloads an avatar or attachment based on $_GET['attach'], and increments the download count.
 * It requires the view_attachments permission.
 * It disables the session parser, and clears any previous output.
 * It depends on the attachmentUploadDir setting being correct.
 * It is accessed via the query string ?action=dlattach.
 * Views to attachments do not increase hits and are not logged in the "Who's Online" log.
 *
 * @param mixed $force_attach If supplied, treat as an ID of a file to serve and bypass the usual checks (as is an internal request)
 */
function showAttachment($force_attach = false)
{
	global $smcFunc, $modSettings, $context;

	// An early hook to set up global vars, clean cache and other early process.
	call_integration_hook('integrate_pre_download_request');

	// This is done to clear any output that was made before now.
	ob_end_clean();

	ob_start();
	header('Content-Encoding: none');

	// Better handling.
	$attachId = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : (int) (isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0);
	if ($force_attach)
		$attachId = $force_attach;

	// We need a valid ID.
	if (empty($attachId))
	{
		header('HTTP/1.0 404 File Not Found');
		die('404 File Not Found');
	}

	// A thumbnail has been requested? madness! madness I say!
	if ($force_attach)
	{
		$preview = 0;
		$showThumb = false;
		$attachTopic = 0;
	}
	else
	{
		$preview = isset($_REQUEST['preview']) ? $_REQUEST['preview'] : (isset($_REQUEST['type']) && $_REQUEST['type'] == 'preview' ? $_REQUEST['type'] : 0);
		$showThumb = isset($_REQUEST['thumb']) || !empty($preview);
		$attachTopic = isset($_REQUEST['topic']) ? (int) $_REQUEST['topic'] : 0;
	}

	// No access in strict maintenance mode or you don't have permission to see attachments.
	if (App::in_hard_maintenance() || (!allowedTo('view_attachments') && !$force_attach))
	{
		header('HTTP/1.0 404 File Not Found');
		die('404 File Not Found');
	}

	// Use cache when possible.
	if (($cache = cache_get_data('attachment_lookup_id-' . $attachId)) != null)
		list($file, $thumbFile) = $cache;

	// Get the info from the DB.
	if (empty($file) || empty($thumbFile) && !empty($file['id_thumb']))
	{
		// Do we have a hook wanting to use our attachment system? We use $attachRequest to prevent accidental usage of $request.
		$attachRequest = null;
		call_integration_hook('integrate_download_request', [&$attachRequest]);
		if (!is_null($attachRequest) && $smcFunc['db']->is_query_result($attachRequest))
			$request = $attachRequest;

		else
		{
			// Make sure this attachment is on this board and load its info while we are at it.
			$request = $smcFunc['db']->query('', '
				SELECT id_folder, filename, file_hash, fileext, id_attach, id_thumb, attachment_type, mime_type, approved, id_msg
				FROM {db_prefix}attachments
				WHERE id_attach = {int:attach}
				LIMIT 1',
				[
					'attach' => $attachId,
				]
			);
		}

		// No attachment has been found.
		if ($smcFunc['db']->num_rows($request) == 0)
		{
			header('HTTP/1.0 404 File Not Found');
			die('404 File Not Found');
		}

		$file = $smcFunc['db']->fetch_assoc($request);
		$smcFunc['db']->free_result($request);

		// If theres a message ID stored, we NEED a topic ID.
		if (!empty($file['id_msg']) && empty($attachTopic) && empty($preview))
		{
			header('HTTP/1.0 404 File Not Found');
			die('404 File Not Found');
		}

		// Previews doesn't have this info.
		if (empty($preview) && !$force_attach)
		{
			$request2 = $smcFunc['db']->query('', '
				SELECT a.id_msg
				FROM {db_prefix}attachments AS a
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg AND m.id_topic = {int:current_topic})
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
				WHERE a.id_attach = {int:attach}
				LIMIT 1',
				[
					'attach' => $attachId,
					'current_topic' => $attachTopic,
				]
			);

			// The provided topic must match the one stored in the DB for this particular attachment, also.
			if ($smcFunc['db']->num_rows($request2) == 0)
			{
				header('HTTP/1.0 404 File Not Found');
				die('404 File Not Found');
			}

			$smcFunc['db']->free_result($request2);
		}

		// set filePath and ETag time
		$file['filePath'] = Attachment::get_filename($file['filename'], $attachId, $file['id_folder'], $file['file_hash']);
		// ensure variant attachment compatibility
		$filePath = pathinfo($file['filePath']);
		$file['filePath'] = !file_exists($file['filePath']) ? substr($file['filePath'], 0, -(strlen($filePath['extension']) + 1)) : $file['filePath'];
		$file['etag'] = '"' . md5_file($file['filePath']) . '"';

		// now get the thumbfile!
		$thumbFile = [];
		if (!empty($file['id_thumb']))
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_folder, filename, file_hash, fileext, id_attach, attachment_type, mime_type, approved, id_character
				FROM {db_prefix}attachments
				WHERE id_attach = {int:thumb_id}
				LIMIT 1',
				[
					'thumb_id' => $file['id_thumb'],
				]
			);

			$thumbFile = $smcFunc['db']->fetch_assoc($request);
			$smcFunc['db']->free_result($request);

			// Got something! replace the $file var with the thumbnail info.
			if ($thumbFile)
			{
				$attachId = $thumbFile['id_attach'];

				// set filePath and ETag time
				$thumbFile['filePath'] = Attachment::get_filename($thumbFile['filename'], $attachId, $thumbFile['id_folder'], $thumbFile['file_hash']);
				$thumbFile['etag'] = '"' . md5_file($thumbFile['filePath']) . '"';
			}
		}

		// Cache it.
		if (!empty($file) || !empty($thumbFile))
			cache_put_data('attachment_lookup_id-' . $file['id_attach'], [$file, $thumbFile], mt_rand(850, 900));
	}

	// Replace the normal file with its thumbnail if it has one!
	if (!empty($showThumb) && !empty($thumbFile))
		$file = $thumbFile;

	// No point in a nicer message, because this is supposed to be an attachment anyway...
	if (!file_exists($file['filePath']))
	{
		header((preg_match('~HTTP/1\.[01]~i', $_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 404 Not Found');
		header('Content-Type: text/plain; charset=UTF-8');

		// We need to die like this *before* we send any anti-caching headers as below.
		die('File not found.');
	}

	// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (strtotime($modified_since) >= filemtime($file['filePath']))
		{
			ob_end_clean();

			// Answer the question - no, it hasn't been modified ;).
			header('HTTP/1.1 304 Not Modified');
			exit;
		}
	}

	// Check whether the ETag was sent back, and cache based on that...
	$eTag = '"' . substr($attachId . $file['filePath'] . filemtime($file['filePath']), 0, 64) . '"';
	if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
	{
		ob_end_clean();

		header('HTTP/1.1 304 Not Modified');
		exit;
	}

	// If this is a partial download, we need to determine what data range to send
	$range = 0;
	$size = filesize($file['filePath']);
	if (isset($_SERVER['HTTP_RANGE']))
	{
		list($a, $range) = explode("=", $_SERVER['HTTP_RANGE'], 2);
		list($range) = explode(",", $range, 2);
		list($range, $range_end) = explode("-", $range);
		$range = intval($range);
		$range_end = !$range_end ? $size - 1 : intval($range_end);
		$new_length = $range_end - $range + 1;
	}

	// Update the download counter (unless it's a thumbnail or resuming an incomplete download).
	if ($file['attachment_type'] != Attachment::ATTACHMENT_THUMBNAIL && empty($showThumb) && $range === 0)
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}attachments
			SET downloads = downloads + 1
			WHERE id_attach = {int:id_attach}',
			[
				'id_attach' => $attachId,
			]
		);

	// Send the attachment headers.
	header('Pragma: ');

	if (!isBrowser('gecko'))
		header('Content-Transfer-Encoding: binary');

	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file['filePath'])) . ' GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('ETag: ' . $eTag);

	// Make sure the mime type warrants an inline display.
	if (isset($_REQUEST['image']) && !empty($file['mime_type']) && strpos($file['mime_type'], 'image/') !== 0)
		unset($_REQUEST['image']);

	// Does this have a mime type?
	elseif (!empty($file['mime_type']) && (isset($_REQUEST['image']) || !in_array($file['fileext'], ['jpg', 'gif', 'jpeg', 'x-ms-bmp', 'png', 'psd', 'tiff', 'iff'])))
		header('Content-Type: ' . strtr($file['mime_type'], ['image/bmp' => 'image/x-ms-bmp']));

	else
	{
		header('Content-Type: ' . (isBrowser('ie') || isBrowser('opera') ? 'application/octetstream' : 'application/octet-stream'));
		if (isset($_REQUEST['image']))
			unset($_REQUEST['image']);
	}

	// Convert the file to UTF-8, cuz most browsers dig that.
	$disposition = !isset($_REQUEST['image']) ? 'attachment' : 'inline';

	// Different browsers like different standards...
	if (isBrowser('firefox'))
		header('Content-Disposition: ' . $disposition . '; filename*=UTF-8\'\'' . rawurlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $file['filename'])));

	elseif (isBrowser('opera'))
		header('Content-Disposition: ' . $disposition . '; filename="' . preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $file['filename']) . '"');

	elseif (isBrowser('ie'))
		header('Content-Disposition: ' . $disposition . '; filename="' . urlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $file['filename'])) . '"');

	else
		header('Content-Disposition: ' . $disposition . '; filename="' . $file['filename'] . '"');

	// If this has an "image extension" - but isn't actually an image - then ensure it isn't cached cause of silly IE.
	if (!isset($_REQUEST['image']) && in_array($file['fileext'], ['gif', 'jpg', 'bmp', 'png', 'jpeg', 'tiff']))
		header('Cache-Control: no-cache');

	else
		header('Cache-Control: max-age=' . (525600 * 60) . ', private');

	// Multipart and resuming support
	if (isset($_SERVER['HTTP_RANGE']))
	{
		header("HTTP/1.1 206 Partial Content");
		header("Content-Length: $new_length");
		header("Content-Range: bytes $range-$range_end/$size");
	}
	else
		header("Content-Length: " . $size);


	// Try to buy some time...
	@set_time_limit(600);

	// For multipart/resumable downloads, send the requested chunk(s) of the file
	if (isset($_SERVER['HTTP_RANGE']))
	{
		while (@ob_get_level() > 0)
			@ob_end_clean();

		// 40 kilobytes is a good-ish amount
		$chunksize = 40 * 1024;
		$bytes_sent = 0;

		$fp = fopen($file['filePath'], 'rb');

		fseek($fp, $range);

		while (!feof($fp) && (!connection_aborted()) && ($bytes_sent < $new_length))
		{
			$buffer = fread($fp, $chunksize);
			echo($buffer);
			flush();
			$bytes_sent += strlen($buffer);
		}
		fclose($fp);
	}

	// Since we don't do output compression for files this large...
	elseif ($size > 4194304)
	{
		// Forcibly end any output buffering going on.
		while (@ob_get_level() > 0)
			@ob_end_clean();

		$fp = fopen($file['filePath'], 'rb');
		while (!feof($fp))
		{
			echo fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}

	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (@readfile($file['filePath']) === null)
		echo file_get_contents($file['filePath']);

	die();
}
