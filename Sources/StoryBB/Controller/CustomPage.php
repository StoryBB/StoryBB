<?php

/**
 * The help page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use StoryBB\App;
use StoryBB\Dependency\CurrentUser;
use StoryBB\Dependency\Database;
use StoryBB\Dependency\Page;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\Helper\Parser;
use StoryBB\Phrase;
use StoryBB\Routing\Behaviours\Routable;
use StoryBB\StringLibrary;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use StoryBB\Routing\Exception\ApplicationException;
use StoryBB\Routing\RenderResponse;

class CustomPage implements Routable
{
	use CurrentUser;
	use Database;
	use Page;
	use UrlGenerator;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('pages', new Route('/pages/{page}', ['_controller' => [static::class, 'view_page']]));
	}

	public function view_page(string $page)
	{
		$scripturl = App::get_global_config_item('boardurl') . '/index.php';

		$page_name = $page;

		$db = $this->db();
		$url = $this->urlgenerator();
		$current_page = $this->page();

		$request = $db->query('', '
			SELECT p.id_page, p.page_name, p.page_title, p.page_content, p.show_help, p.show_custom_field, p.custom_field_filter, cf.field_name, cf.in_character, cf.bbc AS cf_bbc
			FROM {db_prefix}page AS p
				LEFT JOIN {db_prefix}custom_fields AS cf ON (p.show_custom_field = cf.id_field)
			WHERE page_name = {string:page_name}',
			[
				'page_name' => $page_name,
			]
		);
		$page = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($page))
		{
			throw new ApplicationException(new Phrase('no_access'), 404);
		}

		$this->assertPageVisible((int) $page['id_page']);

		$current_page->addLinktree($page['page_title'], $url->generate('pages', ['page' => $page['page_name']]));
		$current_page->setCanonical($url->generate('pages', ['page' => $page['page_name']]));

		$page['page_content'] = Parser::parse_bbc($page['page_content'], true, 'page-' . $page_name);

		if (!empty($page['page_content']))
		{
			$current_page->addMetaName('description', shorten_subject(strip_tags(preg_replace('/<br ?\/?>/i', "\n", $page['page_content'])), 500));
		}

		if (!empty($page['field_name']))
		{
			$page['custom_fields'] = [];
			$characters_loaded = [];

			$request = $db->query('', '
				SELECT cfv.value, cfv.id_character, chars.character_name, mem.id_member, mem.real_name, chars.avatar AS avatar_url, a.filename AS avatar_filename
				FROM {db_prefix}custom_field_values AS cfv
					INNER JOIN {db_prefix}characters AS chars ON (cfv.id_character = chars.id_character)
					INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_character = cfv.id_character AND a.attachment_type = 1)
				WHERE cfv.id_field = {int:field}
				ORDER BY cfv.value',
				[
					'field' => $page['show_custom_field'],
				]
			);
			while ($row = $db->fetch_assoc($request))
			{
				$row['value'] = trim($row['value']);
				if (empty($row['value']))
				{
					continue;
				}
				if ($page['cf_bbc'])
				{
					$row['value'] = Parser::parse_bbc($row['value']);
				}
				$field = html_entity_decode(strip_tags($row['value']));
				preg_match('/([a-z0-9])/i', $field, $matches);
				$index = !empty($matches[1]) ? StringLibrary::toUpper($matches[1]) : ' ';

				$row['avatar'] = set_avatar_data([
					'avatar' => $row['avatar_url'],
					'filename' => $row['avatar_filename'],
				]);

				$row['account_link'] = $scripturl . '?action=profile;u=' . $row['id_member'];
				$row['character_link'] = $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=characters;char=' . $row['id_character'];

				$characters_loaded[$row['id_character']] = 0;

				$page['custom_fields'][$index][$row['id_character']] = $row;
			}
			$db->free_result($request);

			if (!empty($characters_loaded) && !empty($page['custom_field_filter']))
			{
				// Values correspond to:
				// 0 = 'No checks; always display'
				// 1 = 'Must have posted in the last month'
				// 2 = 'Must have posted in the last three months'
				// 3 = 'Must have posted in the last six months'
				// 4 = 'Must have posted at least once'
				// Now we need to find, of the characters in question, which had their last posts when.
				$request = $db->query('', '
					SELECT id_character, MAX(poster_time) AS most_recent
					FROM {db_prefix}messages
					WHERE id_character IN ({array_int:characters})
					GROUP BY id_character',
					[
						'characters' => array_keys($characters_loaded)
					]
				);
				while ($row = $db->fetch_assoc($request))
				{
					$characters_loaded[$row['id_character']] = (int) $row['most_recent'];
				}
				$db->free_result($request);

				$removals = [];
				$min_age = [
					1 => strtotime('-1 month'),
					2 => strtotime('-3 months'),
					3 => strtotime('-6 months'),
					4 => 1, // Just assert non-zero.
				];

				foreach ($characters_loaded as $id_character => $most_recent)
				{
					if ($most_recent < $min_age[$page['custom_field_filter']])
					{
						$removals[$id_character] = $id_character;
					}
				}

				if (!empty($removals))
				{
					foreach ($page['custom_fields'] as $index => $characters)
					{
						foreach (array_keys($characters) as $id_character)
						{
							if (isset($removals[$id_character]))
							{
								unset ($page['custom_fields'][$index][$id_character]);
							}
						}
					}

					foreach ($page['custom_fields'] as $index => $characters)
					{
						if (empty($characters))
						{
							unset ($page['custom_fields'][$index]);
						}
					}
				}

				if (!empty($page['custom_fields']))
				{
					ksort($page['custom_fields']);
				}
			}
		}

		return (App::make(RenderResponse::class))->render('page.twig', $page);
	}

	public function assertPageVisible($page_id)
	{
		$db = $this->db();
		$user = $this->currentuser();

		if ($user->can('admin_forum'))
		{
			return;
		}

		$groups = $user->get_groups();

		if (empty($groups))
		{
			throw new ApplicationException(new Phrase('no_access'), 404);
		}

		$access = 'x';
		$request = $db->query('', '
			SELECT id_group, allow_deny
			FROM {db_prefix}page_access
			WHERE id_page = {int:id_page}
				AND id_group IN ({array_int:groups})',
			[
				'id_page' => $page_id,
				'groups' => $groups,
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			if ($row['allow_deny'])
			{
				// If this is true, the result is a deny.
				$access = 'd';
			}
			elseif ($access != 'd')
			{
				// If we're here, we got an allow - but only if we haven't already had a deny.
				$access = 'a';
			}
		}
		$db->free_result($request);

		if ($access != 'a')
		{
			// @todo is_not_guest(); // It might improve if you are logged in, perhaps. But we're not going to confirm that for you.
			throw new ApplicationException(new Phrase('no_access'), 404);
		}
	}
}
