<?php

/**
 * Abstract generic list column.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\GenericList\Column;

use StoryBB\Phrase;

abstract class AbstractColumn
{
	protected $label;

	public function __construct(Phrase $label)
	{
		$this->label = $label;
	}

	public function get_label(): string
	{
		return (string) $this->label;
	}

	abstract public function get_value(array $row, string $column_id);
}
