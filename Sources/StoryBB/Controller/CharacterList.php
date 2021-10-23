<?php

/**
 * Doer of bulk actions.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class CharacterList implements Routable
{
	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('characters', (new Route('/characters', ['_function' => [static::class, 'character_list']])));
	}

	public static function character_list()
	{
		global $txt, $context, $modSettings, $smcFunc, $scripturl, $user_info;

		isAllowedTo('view_mlist');

		loadLanguage('Profile');

		loadJavaScriptFile('jquery.filterizr.min.js', ['defer' => true], 'sbb_filterizr');

		$context['page_title'] = $txt['chars_menu_title'];
		$context['sub_template'] = 'character_list';
		$context['linktree'][] = [
			'name' => $txt['chars_menu_title'],
			'url' => $scripturl . '?action=characters',
		];

		$context['group_filters'] = [];
		$context['member_filters'] = [];

		$request = $smcFunc['db']->query('', '
			SELECT chars.id_character, chars.id_member, chars.character_name,
				a.filename, COALESCE(a.id_attach, 0) AS id_attach, chars.avatar, chars.posts, chars.date_created,
				chars.main_char_group, chars.char_groups, chars.char_sheet,
				chars.retired, mem.real_name AS played_by
			FROM {db_prefix}characters AS chars
			LEFT JOIN {db_prefix}attachments AS a ON (chars.id_character = a.id_character AND a.attachment_type = 1)
			LEFT JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
			WHERE chars.is_main = 0
			ORDER BY chars.character_name'
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$row['retired'] = !empty($row['retired']);

			$row['character_avatar'] = set_avatar_data([
				'filename' => $row['filename'],
				'avatar' => $row['avatar'],
			]);

			$timestamp = $row['date_created'] + ($user_info['time_offset'] + $modSettings['time_offset']);
			$year = date('Y', $timestamp);
			$month = date('m', $timestamp);
			$day = date('d', $timestamp);
			$row['date_created_format'] = dateformat((int) $year, (int) $month, (int) $day);

			$row['character_link'] = $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=characters;char=' . $row['id_character'];

			$row['character_sheet_link'] = '';
			if ($row['char_sheet'])
			{
				$row['character_sheet_link'] = $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=character_sheet;char=' . $row['id_character'];
			}

			$groups = !empty($row['main_char_group']) ? [$row['main_char_group']] : [];
			$groups = array_merge($groups, explode(',', $row['char_groups']));
			$details = get_labels_and_badges($groups);
			$row['group_title'] = $details['title'];
			$row['group_color'] = $details['color'];
			$row['group_badges'] = $details['badges'];

			$row['played_by_link'] = $scripturl . '?action=profile;u=' . $row['id_member'];

			// For the filtering we need to build some strings up that are comma separated filter numbers.
			// First, character has a sheet, 0 or 1.
			$filters = [];
			$filters[] = $row['char_sheet'] ? 1 : 0;

			// Second, starting at 1000, all the group ids.
			foreach ($groups as $group)
			{
				if (!empty($group))
				{
					$all_groups[$group] = true;
					$filters[] = 1000 + $group;
				}
			}

			// Third, owner ids starting at member id + 10000 (to allow for other things later)
			if (!empty($row['played_by']))
			{
				$context['member_filters'][10000 + $row['id_member']] = $row['played_by'];
				$filters[] = 10000 + $row['id_member'];
			}

			$row['filters'] = implode(',', $filters);

			$context['char_list'][] = $row;
		}
		$smcFunc['db']->free_result($request);

		if (!empty($all_groups))
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_group, group_name
				FROM {db_prefix}membergroups');
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				if (isset($all_groups[$row['id_group']]))
				{
					$context['group_filters'][1000 + $row['id_group']] = $row['group_name'];
				}
			}
			$smcFunc['db']->free_result($request);
		}

		uasort($context['member_filters'], function ($a, $b) {
			return strcasecmp($a, $b);
		});
		uasort($context['group_filters'], function ($a, $b) {
			return strcasecmp($a, $b);
		});
	}
}
