<?php

/**
 * Support functions for handling smileys.
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
use StoryBB\Dependency\Filesystem;
use StoryBB\Dependency\UrlGenerator;

/**
 * Support functions for managing files.
 */
class Smiley
{
	use Database;
	use Filesystem;
	use UrlGenerator;

	const POSITION_POSTFORM = 0;
	const POSITION_HIDDEN = 1;
	const POSITION_POPUP = 2;

	protected $smileys;

	public function get_smileys()
	{
		if (!is_array($this->smileys))
		{
			$this->smileys = [];

			$db = $this->db();
			$urlgenerator = $this->urlgenerator();

			$request = $db->query('', '
				SELECT s.id_smiley, s.code, s.filename, s.description, s.smiley_row, s.smiley_order, s.hidden,
					f.timemodified
				FROM {db_prefix}smileys AS s
				LEFT JOIN {db_prefix}files AS f ON (f.handler = {literal:smiley} AND f.content_id = s.id_smiley)
				ORDER BY s.id_smiley');
			while ($row = $db->fetch_assoc($request))
			{
				$row['url'] = $urlgenerator->generate('smiley_with_timestamp', [
					'id' => $row['id_smiley'],
					'timestamp' => $row['timemodified'],
				]);
				$this->smileys[$row['id_smiley']] = $row;
			}
			$db->free_result($request);
		}

		return $this->smileys;
	}

	public function upload_smiley(array $codes, string $filename, string $description, int $hidden, string $physical_path): int
	{
		$db = $this->db();

		// Do some basic clean up of the codes array we've been given.
		$codes = array_map('trim', $codes);
		$codes = array_filter($codes, function ($code) {
			return !empty($code);
		});
		if (empty($codes))
		{
			throw new RuntimeException('Invalid smiley codes supplied');
		}
		$smileycode = implode("\n", $codes);

		// Work out the position we're going to add this smiley to - end of the list for postform/popup, n/a for hidden.
		$smiley_order = -1;
		if ($hidden != $this::POSITION_HIDDEN)
		{
			$currentsmileys = $this->get_smileys();
			foreach ($currentsmileys as $smiley)
			{
				if ($smiley['hidden'] != $this::POSITION_HIDDEN && $smiley['smiley_order'] > $smiley_order)
				{
					$smiley_order = $smiley['smiley_order'];
				}
			}
		}

		// Add the smiley to the table.
		$smiley_id = $db->insert(
			'insert',
			'{db_prefix}smileys',
			[
				'code' => 'string-30', 'filename' => 'string-48', 'description' => 'string-80', 'hidden' => 'int', 'smiley_order' => 'int',
			],
			[
				$smileycode, $filename, $description, $hidden, $smiley_order + 1,
			],
			['id_smiley'],
			$db::RETURN_LAST_ID
		);

		// @todo Fix this.
		$mimetypes = [
			'gif' => 'image/gif',
			'jpg' => 'image/jpeg',
			'png' => 'image/png',
			'svg' => 'image/svg+xml',
		];
		$ext = strtolower(substr(strrchr($filename, '.'), 1));
		$mimetype = $mimetypes[$ext];

		$this->filesystem()->upload_physical_file($physical_path, $filename, $mimetype, 'smiley', $smiley_id);

		$this->smileys = null;

		return (int) $smiley_id;
	}

	public function delete_smiley(int $id)
	{
		$this->db()->query('', '
			DELETE FROM {db_prefix}smileys
			WHERE id_smiley = {int:id}',
			[
				'id' => $id,
			]
		);

		$this->filesystem()->delete_file('smiley', $id);
	}

	public function is_unique_code(string $uniquecode): bool
	{
		foreach ($this->get_smileys() as $smiley)
		{
			$codes = explode("\n", $smiley['code']);
			foreach ($codes as $code)
			{
				if (trim($code) === $uniquecode)
				{
					return false;
				}
			}
		}

		return true;
	}
}
