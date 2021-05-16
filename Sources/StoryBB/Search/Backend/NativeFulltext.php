<?php

/**
 * Defines the methods required to be implemented by a search backend.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search\Backend;

use StoryBB\Database\DatabaseAdapter;
use StoryBB\Dependency\Database;
use StoryBB\Search\Indexable;
use StoryBB\Search\SearchAdapter;

class NativeFulltext implements SearchAdapter
{
	use Database;

	public function add_content(Indexable $indexable): bool
	{
		$db = $this->db();

		$identifier = $indexable->get_content_identifier();
		$author = $indexable->get_author();
		$insert = [
			$identifier['content_type'],
			$identifier['content_id'],
			$indexable->get_title() ?? '',
			$indexable->get_content() ?? '',
			$indexable->get_formatted_metadata() ?? '',
			$author['id_member'] ?? 0,
			$author['id_character'] ?? 0,
		];
		$db->insert(DatabaseAdapter::INSERT_INSERT,
			'{db_prefix}search_index',
			['content_type' => 'string', 'content_id' => 'string', 'title' => 'string', 'content' => 'string', 'meta' => 'string', 'id_member' => 'int', 'id_character' => 'int'],
			$insert,
			['content_type', 'content_id'],
			DatabaseAdapter::RETURN_NOTHING
		);

		return $db->affected_rows() > 0;
	}

	public function update_content(Indexable $indexable, bool $upsert = true): bool
	{
		$db = $this->db();
		$changes = [];

		$title = $indexable->get_title();
		if ($title !== null)
		{
			$changes['title'] = $title;
		}
		$content = $indexable->get_content();
		if ($content !== null)
		{
			$changes['content'] = $content;
		}
		$metadata = $indexable->get_raw_metadata();
		if ($metadata !== null)
		{
			$changes['meta'] = $indexable->get_formatted_metadata();
		}

		$author = $indexable->get_author();
		if ($author['id_member'] !== null)
		{
			$changes['id_member'] = $author['id_member'];
		}
		if ($author['id_character'] !== null)
		{
			$changes['id_character'] = $author['id_character'];
		}

		if (empty($changes))
		{
			header('X-Debug-fts: no changes');
			return false;
		}

		$clauses = [];
		foreach ($changes as $column => $value)
		{
			$clauses[] = $column . ' = {' . ($column == 'id_member' || $column == 'id_character' ? 'int' : 'string') . ':' . $column . '}';
		}

		$values = $indexable->get_content_identifier() + $changes;

		$db->query('', '
			UPDATE {db_prefix}search_index
			SET ' . implode(', ', $clauses) . '
			WHERE content_type = {string:content_type}
				AND content_id = {int:content_id}',
				$values
		);

		$affected = $db->affected_rows();
		// If we updated, we're all good.
		if ($affected > 0)
		{
			return true;
		}
		// If we didn't update we might want to backfill this.
		if ($upsert)
		{
			return $this->add_content($indexable);
		}
		// Guess we didn't update things.
		return false;
	}

	public function delete_content(Indexable $indexable): bool
	{
		$db = $this->db();
		$db->query('', '
			DELETE FROM {db_prefix}search_index
			WHERE content_type = {string:content_type}
			AND content_id = {int:content_id}',
			$indexable->get_content_identifier()
		);

		return $db->affected_rows() > 0;
	}
}
