<?php

/**
 * Return the value absolutely unfiltered for a generic list column.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\GenericList\Column;

class RawColumn extends AbstractColumn
{
	public function get_value(array $row, string $column_id)
	{
		return $row[$column_id] ?? '';
	}
}
