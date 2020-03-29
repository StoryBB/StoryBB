<?php

/**
 * A class for serving smileys.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Dependency;

use RuntimeException;
use StoryBB\Database\DatabaseAdapter;

trait Database
{
	protected $_db;

	public function _accept_database(DatabaseAdapter $db) : void
	{
		$this->_db = $db;
	}

	protected function db() : DatabaseAdapter
	{
		if (empty($this->_db))
		{
			throw new RuntimeException('Database not initialised');
		}
		return $this->_db;
	}
}
