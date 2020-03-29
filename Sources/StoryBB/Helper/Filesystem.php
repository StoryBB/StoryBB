<?php

/**
 * Support functions for managing files.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use RuntimeException;
use StoryBB\App;
use StoryBB\Dependency\Database;
use StoryBB\Routing\NotFoundResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Support functions for managing files.
 */
class Filesystem
{
	use Database;

	public function get_file_details(string $handler, int $content_id): array
	{
		$db = $this->db();

		$query = $db->query('', '
			SELECT id, handler, content_id, filename, filehash, mimetype, size, id_owner,
				timemodified
			FROM {db_prefix}files
			WHERE handler = {string:handler}
			AND content_id = {int:content_id}',
			[
				'handler' => $handler,
				'content_id' => $content_id,
			]
		);

		$record = $db->fetch_assoc($query);
		$db->free_result($query);

		if (!$record)
		{
			throw new RuntimeException('File missing');
		}

		return $record;
	}

	public function delete_file(string $handler, int $content_id): void
	{
		$file = $this->get_file_details($handler, $content_id);

		$physical_path = $this->get_physical_file_location($file);
		@unlink($physical_path);

		$this->db()->query('', '
			DELETE FROM {db_prefix}files
			WHERE handler = {string:handler}
			AND content_id = {int:content_id}',
			[
				'handler' => $handler,
				'content_id' => $content_id,
			]
		);
	}

	public function get_physical_file_location(array $file): string
	{
		$filedir = App::get_root_path() . '/cache/files';
		return $filedir . '/' . substr($file['filehash'], 0, 2) . '/' . substr($file['filehash'], 2, 2) . '/' . $file['filehash'] . '.dat';
	}

	public function physical_file_is_readable(string $filepath): bool
	{
		return is_readable($filepath);
	}

	public function serve(array $file): Response
	{
		$physical_path = $this->get_physical_file_location($file);
		if (!$this->physical_file_is_readable($physical_path))
		{
			return new NotFoundResponse;
		}

		$response = new Response;
		$response->setContent(file_get_contents($physical_path));
		$response->headers->set('Content-Type', $file['mimetype']);

		return $response;
	}

	public function copy_physical_file(string $physical_path, string $filename, string $mimetype, string $handler, ?int $content_id)
	{
		$filehash = $this->get_file_hash_from_physical($filename);
		$this->ensure_folders_exist($filehash);
		copy($physical_path, $this->get_physical_file_location(['filehash' => $filehash]));
		return $this->create_file_record($filehash, $filename, $mimetype, $handler, $content_id);
	}

	public function upload_physical_file(string $physical_path, string $filename, string $mimetype, string $handler, ?int $content_id)
	{
		if (!is_uploaded_file($physical_path))
		{
			throw new RuntimeException('File is not an upload');
		}

		$filehash = $this->get_file_hash_from_physical($filename);

		$this->ensure_folders_exist($filehash);

		if (!move_uploaded_file($physical_path, $this->get_physical_file_location(['filehash' => $filehash])))
		{
			throw new RuntimeException('Could not move upload');
		}
		return $this->create_file_record($filehash, $filename, $mimetype, $handler, $content_id);
	}

	public function get_file_hash_from_physical($filename): string
	{
		return sha1(md5($filename . microtime(true)) . mt_rand());
	}

	protected function create_file_record(string $filehash, string $filename, string $mimetype, string $handler, ?int $content_id, ?int $size = null, ?int $id_owner = null)
	{
		if (is_null($size))
		{
			$size = filesize($this->get_physical_file_location(['filehash' => $filehash]));
		}

		return $this->db()->insert(
			'insert',
			'{db_prefix}files',
			[
				'handler' => 'string', 'content_id' => 'int', 'filename' => 'string', 'filehash' => 'string',
				'mimetype' => 'string', 'size' => 'int', 'id_owner' => 'int', 'timemodified' => 'int',
			],
			[
				$handler, $content_id ?? 0, $filename, $filehash,
				$mimetype, $size, $id_owner ?? 0, time(),
			],
			['id'],
			1
		);
	}

	protected function ensure_folders_exist(string $filehash)
	{
		$filedir = App::get_root_path() . '/cache/files';

		if (!file_exists($filedir . '/' . substr($filehash, 0, 2)))
		{
			mkdir($filedir . '/' . substr($filehash, 0, 2));
		}
		if (!file_exists($filedir . '/' . substr($filehash, 0, 2) . '/' . substr($filehash, 2, 2)))
		{
			mkdir($filedir . '/' . substr($filehash, 0, 2) . '/' . substr($filehash, 2, 2));
		}
	}
}
