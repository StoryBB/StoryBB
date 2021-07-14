<?php

/**
 * This class handles topic prefixes.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles topic prefixes.
 */
class TopicPrefix
{
	protected static function sanitise_ints(array $ints): array
	{
		return array_filter(array_map('intval', $ints), function ($x) {
			return $x > 0;
		});
	}

	public static function get_prefixes_for_topic(int $topic_id)
	{
		$prefixes = static::get_prefixes_for_topic_list([$topic_id]);
		return $prefixes[$topic_id] ?? [];
	}

	public static function get_prefixes_for_topic_list(array $topics): array
	{
		global $smcFunc;

		$prefixes = [];

		$topics = static::sanitise_ints($topics);

		if (empty($topics))
		{
			return [];
		}

		$request = $smcFunc['db']->query('', '
			SELECT tpp.id_topic, tp.id_prefix, tp.name, tp.css_class
			FROM {db_prefix}topic_prefixes tp
			INNER JOIN {db_prefix}topic_prefix_topics tpp ON (tpp.id_prefix = tp.id_prefix)
			WHERE tpp.id_topic IN ({array_int:topics})
			ORDER BY tpp.id_topic, tp.sort_order',
			[
				'topics' => $topics,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$row['css_class'] .= ' prefix-id-' . $row['id_prefix'];
			$prefixes[$row['id_topic']][$row['id_prefix']] = $row;
		}
		$smcFunc['db']->free_result($request);

		return $prefixes;
	}

	public static function delete_prefixes(array $prefixes): void
	{
		global $smcFunc;

		$prefixes = static::sanitise_ints($prefixes);

		if (empty($prefixes))
		{
			return;
		}

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}topic_prefix_topics
			WHERE id_prefix IN ({array_int:prefixes})',
			[
				'prefixes' => $prefixes,
			]
		);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}topic_prefix_groups
			WHERE id_prefix IN ({array_int:prefixes})',
			[
				'prefixes' => $prefixes,
			]
		);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}topic_prefix_boards
			WHERE id_prefix IN ({array_int:prefixes})',
			[
				'prefixes' => $prefixes,
			]
		);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}topic_prefixes
			WHERE id_prefix IN ({array_int:prefixes})',
			[
				'prefixes' => $prefixes,
			]
		);
	}

	public static function delete_topics(array $topics): void
	{
		global $smcFunc;

		$topics = static::sanitise_ints($topics);

		if (empty($topics))
		{
			return;
		}

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}topic_prefix_topics
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			]
		);
	}

	public static function get_prefixes(array $conditions = [], string $sort = '')
	{
		global $smcFunc;

		$select = ['tp.id_prefix', 'tp.name', 'tp.css_class', 'tp.selectable'];
		$joins = ['{db_prefix}topic_prefixes AS tp'];
		$sql = [];
		$params = [];
		$group_by = [];

		if (isset($conditions['boards']))
		{
			$conditions['boards'] = static::sanitise_ints($conditions['boards']);
			if (!empty($conditions['boards']))
			{
				$joins[] = '{db_prefix}topic_prefix_boards tpb ON (tp.id_prefix = tpb.id_prefix)';
				$sql[] = 'tpb.id_board IN ({array_int:boards})';
				$params['boards'] = $conditions['boards'];
			}
		}

		if (isset($conditions['prefixes']))
		{
			$conditions['prefixes'] = static::sanitise_ints($conditions['prefixes']);
			if (!empty($conditions['prefixes']))
			{
				$sql[] = 'tp.id_prefix IN ({array_int:prefixes})';
				$params['prefixes'] = $conditions['prefixes'];
			}
		}

		if (isset($conditions['groups']))
		{
			$conditions['groups'] = array_filter(array_map('intval', $conditions['groups']), function ($x) {
				return $x >= 0; // Regular Member is group id 0, so must account for that.
			});

			// Apply the condition for groups only if admin (group 1) isn't in the list.
			if (!empty($conditions['groups']) && !in_array(1, $conditions['groups']))
			{
				$group_by = array_merge($group_by, $select);
				$select[] = 'MAX(tpg.allow_deny) AS allow_deny';
				$joins[] = '{db_prefix}topic_prefix_groups tpg ON (tp.id_prefix = tpg.id_prefix)';
				$sql[] = 'tpg.id_group IN ({array_int:groups})';
				$params['groups'] = $conditions['groups'];
			}
		}

		if (isset($conditions['prefixes']))
		{
			$conditions['prefixes'] = static::sanitise_ints($conditions['prefixes']);
			if (!empty($conditions['prefixes']))
			{
				$sql[] = 'tp.id_prefix IN ({array_int:prefixes})';
				$params['prefixes'] = $conditions['prefixes'];
			}
		}

		if (isset($conditions['selectable']))
		{
			$sql[] = 'tp.selectable = {int:selectable}';
			$params['selectable'] = !empty($conditions['selectable']) ? 1 : 0;
		}

		if (in_array($sort, ['name', 'sort_order']))
		{
			$params['sort'] = 'tp.' . $sort;
		}
		else
		{
			$params['sort'] = 'tp.sort_order';
		}

		$query = '
			SELECT ' . implode(', ', $select) . '
			FROM ' . implode(' INNER JOIN ', $joins) . '
			' . (!empty($sql) ? 'WHERE ' . implode(' AND ', $sql) : '') . '
			' . (!empty($group_by) ? 'GROUP BY ' . implode(', ', $group_by) : '') . '
			ORDER BY {raw:sort}';

		$request = $smcFunc['db']->query('', $query, $params);
		$result = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!empty($row['allow_deny']))
			{
				continue;
			}

			$result[] = $row;
		}
		$smcFunc['db']->free_result($request);

		return $result;
	}

	public static function create_prefix(string $name, string $css_class = '', bool $selectable = true): int
	{
		global $smcFunc;

		$request = $smcFunc['db']->query('', '
			SELECT COALESCE(MAX(sort_order), 0) + 1
			FROM {db_prefix}topic_prefixes');
		[$sort_order] = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$prefix = $smcFunc['db']->insert('insert',
			'{db_prefix}topic_prefixes',
			['name' => 'string', 'css_class' => 'string', 'sort_order' => 'int', 'selectable' => 'int'],
			[$name, $css_class, $sort_order, $selectable ? 1 : 0],
			['id_prefix'],
			1
		);

		return (int) $prefix;
	}

	public static function update_prefix(int $prefix, array $vars): void
	{
		global $smcFunc;

		$sql = [];
		$updating = ['prefix' => $prefix];

		if (isset($vars['name']))
		{
			$sql[] = 'name = {string:name}';
			$updating['name'] = $vars['name'];
		}

		if (isset($vars['css_class']))
		{
			$sql[] = 'css_class = {string:css_class}';
			$updating['css_class'] = $vars['css_class'];
		}

		if (isset($vars['selectable']))
		{
			$sql[] = 'selectable = {int:selectable}';
			$updating['selectable'] = !empty($vars['selectable']) ? 1 : 0;
		}

		if (empty($sql))
		{
			return;
		}

		$smcFunc['db']->query('', '
			UPDATE {db_prefix}topic_prefixes
			SET ' . implode(', ', $sql) . '
			WHERE id_prefix = {int:prefix}',
			$updating);
	}

	public static function set_prefix_groups(int $prefix, array $allow, array $deny)
	{
		global $smcFunc;

		$allow = array_filter(array_map('intval', $allow), function ($x) {
			return $x >= 0; // Guest = -1, Regular Member is 0, so must account for that.
		});
		$deny = array_filter(array_map('intval', $deny), function ($x) {
			return $x >= 0; // Guest = -1, Regular Member is 0, so must account for that.
		});

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}topic_prefix_groups
			WHERE id_prefix = {int:prefix}',
			[
				'prefix' => $prefix,
			]
		);

		// Anything in deny must forcibly override anything matching in allow.
		$allow = array_diff($allow, $deny);

		$insert = [];
		foreach ($allow as $allowable)
		{
			$insert[] = [$prefix, $allowable, 0];
		}
		foreach ($deny as $deniable)
		{
			$insert[] = [$prefix, $deniable, 1];
		}

		if (!empty($insert))
		{
			$smcFunc['db']->insert('insert',
				'{db_prefix}topic_prefix_groups',
				['id_prefix' => 'int', 'id_group' => 'int', 'allow_deny' => 'int'],
				$insert,
				['id_prefix', 'id_group']
			);
		}
	}

	public static function set_prefix_boards(int $prefix, array $boards)
	{
		global $smcFunc;

		$boards = static::sanitise_ints($boards);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}topic_prefix_boards
			WHERE id_prefix = {int:prefix}',
			[
				'prefix' => $prefix,
			]
		);

		if (!empty($boards))
		{
			$insert = [];

			foreach ($boards as $board)
			{
				$insert[] = [$prefix, $board];
			}

			$smcFunc['db']->insert('insert',
				'{db_prefix}topic_prefix_boards',
				['id_prefix' => 'int', 'id_board' => 'int'],
				$insert,
				['id_prefixboard']
			);
		}
	}

	public static function set_prefix_topic(int $topic, array $prefixes)
	{
		global $smcFunc;

		$prefixes = static::sanitise_ints($prefixes);

		static::delete_topics([$topic]);

		$insert = [];
		foreach ($prefixes as $prefix)
		{
			$insert[] = [$prefix, $topic];
		}

		if (empty($insert))
		{
			return;
		}

		$smcFunc['db']->insert('insert',
			'{db_prefix}topic_prefix_topics',
			['id_prefix' => 'int', 'id_topic' => 'int'],
			$insert,
			['id_prefixtopic']
		);
	}
}
