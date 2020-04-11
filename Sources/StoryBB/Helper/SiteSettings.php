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
use StoryBB\Dependency\Database;

class SiteSettings
{
	use Database;

	protected $settings = null;

	public function __get($key)
	{
		if ($this->settings === null)
		{
			$this->settings = $this->load_settings();
		}

		if (isset($this->settings[$key]))
		{
			return $this->settings[$key];
		}

		return null;
	}

	/**
	 * Deprecated hack for $modSettings support.
	 */
	public function get_all(): array
	{
		if ($this->settings === null)
		{
			$this->settings = $this->load_settings();
		}
		return $this->settings;
	}

	protected function load_settings(): array
	{
		$db = $this->db();

		$settings = [];

		// Load the existing settings.
		$request = $db->query('', '
			SELECT variable, value
			FROM {db_prefix}settings',
			[]
		);

		if (!$request)
		{
			throw new RuntimeException('No database connection!');
		}
		while ($row = $db->fetch_row($request))
		{
			$settings[$row[0]] = $row[1];
		}
		$db->free_result($request);

		// Fix a few entries that might be invalid.
		$safe_values = [
			'defaultMaxTopics' => 20,
			'defaultMaxMessages' => 15,
			'defaultMaxMembers' => 30,
			'defaultMaxListItems' => 15,
		];
		foreach ($safe_values as $key => $value)
		{
			if (empty($settings[$key]) || $settings[$key] <= 0 || $settings[$key] > 999)
			{
				$settings[$key] = $value;
			}
		}

		return $settings;
	}
}
