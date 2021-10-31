<?php

/**
 * This class handles currency.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles currency.
 */
class Currency
{
	protected static $enabled = null;

	protected static $display = null;

	public static function is_enabled(): bool
	{
		global $modSettings;

		if (static::$enabled === null)
		{
			static::$enabled = !empty($modSettings['currency_enabled']);
		}

		return static::$enabled;
	}

	public static function display(string $area): bool
	{
		global $modSettings;

		if (static::$display === null)
		{
			static::$display = [
				'topic' => false,
				'profile' => false,
			];

			if (!empty($modSettings['currency_display']))
			{
				$display = @json_decode($modSettings['currency_display'], true);
				if (!empty($display) && is_array($display))
				{
					foreach ($display as $area => $value)
					{
						if (isset(static::$display[$area]))
						{
							static::$display[$area] = $value;
						}
					}
				}
			}
		}

		return static::$display[$area] ?? false;
	}

	public static function process(int $currency): array
	{
		global $modSettings;

		$return = [
			'amount' => $currency,
		];

		return $return;
	}
}
