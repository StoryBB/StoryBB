<?php

/**
 * Support functions for managing files.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

use RuntimeException;
use StoryBB\Database\DatabaseAdapter;
use StoryBB\Dependency\Database;

class SiteSettings
{
	use Database;

	protected $settings = null;

	public function __get($key)
	{
		if ($this->settings === null)
		{
			$this->settings = $this->load();
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

	protected function load(): array
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

		// Force overrides.
		$overrides = [
			'browser_cache' => '?100a1' . 1627150671,
		];
		foreach ($overrides as $key => $value)
		{
			$settings[$key] = $value;
		}

		return $settings;
	}

	public function save(array $changes): void
	{
		if (empty($changes))
		{
			return;
		}

		// Go check if there are any settings to be removed.
		$to_remove = [];
		foreach ($changes as $k => $v)
		{
			if ($v === null)
			{
				// Found some, remove them from the original array and add them to ours.
				unset($changes[$k]);
				$to_remove[] = $k;
			}
		}

		// Proceed with the deletion.
		if (!empty($to_remove))
		{
			$this->db()->query('', '
				DELETE FROM {db_prefix}settings
				WHERE variable IN ({array_string:remove})',
				[
					'remove' => $to_remove,
				]
			);
		}

		$replaceArray = [];
		foreach ($changes as $variable => $value)
		{
			// If it's already that value, leave it as is.
			if (isset($this->settings[$variable]) && $this->settings[$variable] == $value)
			{
				continue;
			}

			// If the variable isn't set, but would only be set to nothing'ness, then don't bother setting it.
			elseif (!isset($this->settings[$variable]) && empty($value))
			{
				continue;
			}

			$replaceArray[] = [$variable, $value];
			$this->settings[$variable] = $value;
		}

		if (empty($replaceArray))
		{
			return;
		}

		$this->db()->insert(DatabaseAdapter::INSERT_REPLACE,
			'{db_prefix}settings',
			['variable' => 'string-255', 'value' => 'string-65534'],
			$replaceArray,
			['variable']
		);
	}
}
