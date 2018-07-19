<?php

/**
 * Any autocomplete handlers must implement this interface.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Helper\Autocomplete;

abstract class AbstractCompletable implements Completable
{
	protected $term = null;

	public function set_search_term(string $term)
	{
		$this->term = $term;
	}

	protected function escape_term(string $term): string
	{
		global $smcFunc;
		$term = trim($smcFunc['strtolower']($term)) . '*';
		return strtr($term, [
			'%' => '\%',
			'_' => '\_',
			'*' => '%',
			'?' => '_',
			'&#038;' => '&amp;',
		]);
	}

	public function can_paginate(): bool
	{
		return false;
	}

	public function get_count(): int
	{
		return 0;
	}

	abstract public function get_results(int $start = null, int $limit = null): array;

	public function get_js(): string
	{
		return '';
	}
}
