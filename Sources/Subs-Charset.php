<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Fixes corrupted serialized strings after a character set conversion.
 */
function fix_serialized_columns()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_action, extra
		FROM {db_prefix}log_actions
		WHERE action IN ({string:remove}, {string:delete})',
		array(
			'remove' => 'remove',
			'delete' => 'delete',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (safe_unserialize($row['extra']) === false && preg_match('~^(a:3:{s:5:"topic";i:\d+;s:7:"subject";s:)(\d+):"(.+)"(;s:6:"member";s:5:"\d+";})$~', $row['extra'], $matches) === 1)
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_actions
				SET extra = {string:extra}
				WHERE id_action = {int:current_action}',
				array(
					'current_action' => $row['id_action'],
					'extra' => $matches[1] . strlen($matches[3]) . ':"' . $matches[3] . '"' . $matches[4],
				)
			);
	}
	$smcFunc['db_free_result']($request);

	// Refresh some cached data.
	updateSettings(array(
		'memberlist_updated' => time(),
	));

}

?>