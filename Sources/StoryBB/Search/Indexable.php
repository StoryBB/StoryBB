<?php

/**
 * Reflects an object that can be searched.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search;

class Indexable
{
	protected $content_type;
	protected $content_id;
	protected $title;
	protected $content;
	protected $metadata;
	protected $id_member;
	protected $id_character;
	protected $timestamp = 0;

	public function __construct(string $content_type, int $content_id)
	{
		$this->content_type = $content_type;
		$this->content_id = $content_id;
	}

	public function get_raw_title(): string
	{
		return $this->title;
	}

	public function get_title(): ?string
	{
		return !is_null($this->title) ? html_entity_decode($this->title) : $this->title;
	}

	public function set_title(string $title): Indexable
	{
		$this->title = $title;
		return $this;
	}

	public function get_raw_content(): ?string
	{
		return $this->content;
	}

	public function get_content(): ?string
	{
		if (is_null($this->content))
		{
			return null;
		}

		$content = preg_replace('/<br ?\/?>/i', ' ', $this->content);
		$content = str_replace(['</p>', '</div>'], ['</p> ', '</div> '], $content);
		$content = strip_tags($content);
		$content = html_entity_decode($content);
		$content = preg_replace('/\s+/iu', ' ', $content);
		return trim($content);
	}

	public function set_content(string $html): Indexable
	{
		$this->content = $html;
		return $this;
	}

	public function get_content_identifier(): array
	{
		return [
			'content_type' => $this->content_type,
			'content_id' => $this->content_id,
		];
	}

	public function get_raw_metadata(): ?array
	{
		return $this->metadata;
	}

	public function get_formatted_metadata(): string
	{
		$meta = '__m_content_' . $this->content_type;
		if ($this->id_member !== null)
		{
			$meta .= ' __m_id_member_' . $this->id_member;
		}
		if ($this->id_character !== null)
		{
			$meta .= ' __m_id_character_' . $this->id_character;
		}

		if (is_array($this->metadata))
		{
			foreach ($this->metadata as $key => $value)
			{
				$meta .= ' __m_' . $key . '_' . (string) $value;
			}
		}
		return $meta;
	}

	public function set_metadata(array $metadata): Indexable
	{
		$this->metadata = $metadata;
		return $this;
	}

	public function get_author(): array
	{
		return [
			'id_member' => $this->id_member,
			'id_character' => $this->id_character,
		];
	}

	public function set_author(int $id_member, int $id_character): Indexable
	{
		$this->id_member = $id_member;
		$this->id_character = $id_character;
		return $this;
	}

	public function get_timestamp(): int
	{
		return $this->timestamp;
	}

	public function set_timestamp(int $timestamp): Indexable
	{
		$this->timestamp = $timestamp;
		return $this;
	}
}
