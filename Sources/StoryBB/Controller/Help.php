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
use StoryBB\Dependency\Page;
use StoryBB\Dependency\UrlGenerator;
use StoryBB\Helper\Parser;
use StoryBB\Phrase;
use StoryBB\Routing\Behaviours\Routable;
use StoryBB\Routing\LegacyTemplateResponse;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Help implements Routable
{
	use Page;
	use UrlGenerator;

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('help', new Route('/help', ['_function' => [static::class, 'help_index']]));
		$routes->add('help_smileys', new Route('/help/smileys', ['_controller' => [static::class, 'smileys']]));
		$routes->add('help_policy', new Route('/help/{policy}', ['_function' => [static::class, 'policy']]));
	}

	/**
	 * Displays the main help index.
	 */
	public static function help_index()
	{
		global $context, $txt, $scripturl, $smcFunc, $user_info, $language;

		$url = App::container()->get('urlgenerator');

		$context['manual_sections'] = [
			'smileys' => [
				'link' => $url->generate('help_smileys'),
				'title' => new Phrase('Manual:manual_smileys'),
				'desc' => new Phrase('Manual:manual_smileys_desc'),
			],
		];

		$policies = static::get_help_policies();
		foreach ($policies as $policy_type => $policy)
		{
			$context['manual_sections'][$policy_type] = $policy;
		}

		// Now let's see if there are any pages for the user.
		$pages = static::get_custom_help_pages();
		if (!empty($pages))
		{
			foreach ($pages as $id_page => $page)
			{
				$context['manual_sections']['page_' . $id_page] = [
					'title' => $page['page_title'],
					'desc' => '',
					'link' => $url->generate('pages', ['page' => $page['page_name']]),
				];
			}
		}

		$context['canonical_url'] = $url->generate('help');

		// Build the link tree.
		$context['linktree'][] = [
			'url' => $context['canonical_url'],
			'name' => new Phrase('help'),
		];

		// Lastly, some minor template stuff.
		$context['page_title'] = new Phrase('Manual:manual_storybb_user_help');
		$context['sub_template'] = 'help_manual';
	}

	public function smileys(): Response
	{
		$container = \StoryBB\Container::instance();
		$smiley_helper = $container->get('smileys');

		$page = $this->page();
		$page->addLinktree(new Phrase('General:help'), $this->urlgenerator()->generate('help'));
		$page->addLinktree(new Phrase('Manual:manual_smileys'), $this->urlgenerator()->generate('help_smileys'));

		$smileys = [];
		foreach ($smiley_helper->get_smileys() as $smiley)
		{
			$smileys[] = [
				'text' => $smiley['description'],
				'code' => explode("\n", $smiley['code']),
				'image' => $smiley['url'],
			];
		}

		return ($container->instantiate(LegacyTemplateResponse::class))->render('help_smileys.twig', [
			'smileys' => $smileys,
			'forum_name' => $container->get('sitesettings')->forum_name,
		]);
	}

	public static function policy()
	{
		global $scripturl, $context, $txt, $smcFunc, $cookiename, $modSettings;

		$policies = static::get_help_policies();
		$url = App::container()->get('urlgenerator');

		if (!isset($context['routing']['policy']))
		{
			redirectexit($url->generate('help'));
		}


		$context['canonical_url'] = $url->generate('help_policy', ['policy' => $context['routing']['policy']]);

		// Build the link tree.
		$context['linktree'][] = [
			'url' => $url->generate('help'),
			'name' => $txt['help'],
		];
		$context['linktree'][] = [
			'url' => $context['canonical_url'],
			'name' => $policies[$context['routing']['policy']]['title'],
		];
		
		// We know if we're here the policy exists.
		$request = $smcFunc['db']->query('', '
			SELECT p.id_policy, pr.last_change, pr.revision_text
			FROM {db_prefix}policy_revision AS pr
				INNER JOIN {db_prefix}policy AS p ON (p.last_revision = pr.id_revision)
			WHERE p.id_policy = {int:policy}',
			[
				'policy' => $policies[$context['routing']['policy']]['id_policy'],
			]
		);
		$row = $smcFunc['db']->fetch_assoc($request);

		$context['policy_name'] = $policies[$context['routing']['policy']]['title'];

		// Replace out some of the placeholders in our text.
		$replacements = [
			'{$forum_name}' => $context['forum_name_html_safe'],
			'{$contact_url}' => $scripturl . '?action=contact',
			'{$cookiename}' => $cookiename,
			'{$age}' => $modSettings['minimum_age'],
			'{$cookiepolicy}' => $url->generate('help_policy', ['policy' => 'cookies']),
		];
		$context['policy_text'] = Parser::parse_bbc(strtr($row['revision_text'], $replacements), false);
		$context['last_updated'] = timeformat($row['last_change']);

		$context['page_title'] = $context['policy_name'];
		$context['sub_template'] = 'help_policy';
	}

	protected static function get_help_policies()
	{
		global $language, $user_info, $smcFunc, $scripturl;

		$url = App::container()->get('urlgenerator');

		$policies = [];

		$request = $smcFunc['db']->query('', '
			SELECT p.id_policy, pt.policy_type, p.language, p.title, p.description
			FROM {db_prefix}policy_types AS pt
				INNER JOIN {db_prefix}policy AS p ON (p.policy_type = pt.id_policy_type)
			WHERE pt.show_help = 1
			AND p.language IN ({array_string:languages})',
			[
				'languages' => [$language, $user_info['language']],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!isset($context['manual_sections'][$row['policy_type']]))
			{
				$policies[$row['policy_type']] = [
					'link' => $url->generate('help_policy', ['policy' => $row['policy_type']]),
					'title' => $row['title'],
					'desc' => $row['description'],
					'id_policy' => $row['id_policy'],
				];
			}
			elseif ($row['language'] == $user_info['language'])
			{
				// So we matched multiple, we previously had one (in site language) and now we have one for the user language, so use that.
				$policies[$row['policy_type']]['title'] = $row['title'];
				$policies[$row['policy_type']]['desc'] = $row['description'];
				$policies[$row['policy_type']]['id_policy'] = $row['id_policy'];
			}
		}
		$smcFunc['db']->free_result($request);

		return $policies;
	}

	protected static function get_custom_help_pages()
	{
		global $smcFunc, $user_info;

		$pages = [];

		$base_access = allowedTo('admin_forum') ? 'a' : 'x';

		$request = $smcFunc['db']->query('', '
			SELECT id_page, page_name, page_title
			FROM {db_prefix}page
			WHERE show_help = 1
			ORDER BY page_title');
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$row['access'] = $base_access;
			$pages[$row['id_page']] = $row;
		}
		$smcFunc['db']->free_result($request);

		if (empty($pages))
		{
			return [];
		}

		// Admins don't need to check.
		if (allowedTo('admin_forum'))
		{
			return $pages;
		}

		$request = $smcFunc['db']->query('', '
			SELECT id_page, MAX(allow_deny) AS access
			FROM {db_prefix}page_access
			WHERE id_page IN ({array_int:pages})
				AND id_group IN ({array_int:groups})
			GROUP BY id_page',
			[
				'pages' => array_keys($pages),
				'groups' => $user_info['groups'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$pages[$row['id_page']]['access'] = $row['access'] ? 'd' : 'a';
		}
		$smcFunc['db']->free_result($request);

		foreach ($pages as $id_page => $page)
		{
			if ($page['access'] != 'a')
			{
				unset($pages[$id_page]);
			}
		}

		return $pages;
	}
}
