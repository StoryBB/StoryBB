<?php

/**
 * The admin home page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin;

use StoryBB\Phrase;
use StoryBB\Container;

trait AdminNavigation
{
	protected function get_navigation(string $selected_route): array
	{
		$admin = [
			'configuration' => [
				'icon' => 'fas fa-cogs',
				'label' => new Phrase('Admin:configuration'),
				'sections' => [
					'overview' => [
						'label' => new Phrase('Admin:overview'),
						'items' => [
							'dashboard' => ['dashboard', []],
							'configuration' => ['general_configuration'],
							'contact_form' => ['contact_form'],
							'affiliates' => ['affiliates'],
							'integrations' => ['integrations'],
						],
					],
				],
			],
			'appearance' => [
				'icon' => 'fas fa-paint-brush',
				'label' => new Phrase('Admin:appearance'),
				'sections' => [
					'sitedesign' => [
						'label' => new Phrase('Admin:site_design'),
						'items' => [
							'appearance/themes' => ['themes'],
							'appearance/icons' => ['icons_and_logos'],
						],
					],
					'contentediting' => [
						'label' => new Phrase('Admin:content_editing'),
						'items' => [
							'content/fonts' => ['fonts'],
							'content/smileys' => ['smileys'],
						],
					],
					'localization' => [
						'label' => new Phrase('Admin:localization'),
						'items' => [
							'localization/languages' => ['languages'],
						],
					],
				],
			],
			'accounts' => [
				'icon' => 'fas fa-users',
				'label' => new Phrase('Admin:accounts'),
				'sections' => [
					'accounts' => [
						'label' => new Phrase('Admin:accounts'),
						'items' => [
							'accounts/list' => ['accounts'],
							'accounts/iplookup' => ['ip_lookup'],
						],
					],
					'accountsettings' => [
						'label' => new Phrase('Admin:account_settings'),
						'items' => [
							'accounts/profile_fields' => ['profile_fields'],
							'accounts/notifications' => ['notifications'],
							'accounts/avatar_settings' => ['avatar_settings'],
							'accounts/signature_settings' => ['signature_settings'],
							'accounts/preferences' => ['preferences'],
						],
					],
					'registrations' => [
						'label' => new Phrase('Admin:registrations'),
						'items' => [
							'registration/settings' => ['registration_settings'],
							'registration/site_policies' => ['site_policies'],
						],
					],
				],
			],
			'forum' => [
				'icon' => 'far fa-comments',
				'label' => new Phrase('Admin:forum'),
				'sections' => [
					'forumorganization' => [
						'label' => new Phrase('Admin:forum_organization'),
						'items' => [
							'forum/board_structure' => ['board_structure'],
							'forum/settings' => ['board_settings'],
						],
					],
				],
			],
			'reports' => [
				'icon' => 'fas fa-chart-bar',
				'label' => new Phrase('Admin:reports'),
				'sections' => [
					'siteoverview' => [
						'label' => new Phrase('Admin:site_overview'),
						'items' => [
							'reports/site_stats' => ['site_stats'],
							'reports/membergroups' => ['membergroups'],
							'reports/membergroup_permissions' => ['membergroup_permissions'],
							'reports/boards' => ['boards'],
							'reports/board_permissions' => ['board_permissions'],
						],
					],
					'memberreports' => [
						'label' => new Phrase('Admin:member_reports'),
						'items' => [
							'reports/member_visits' => ['member_visits'],
							'reports/member_activity' => ['member_activity'],
							'reports/character_activity' => ['character_activity'],
						],
					],
					'logs' => [
						'label' => new Phrase('Admin:logs'),
						'items' => [
							'logs/errors' => ['error_log'],
							'logs/admin' => ['administration_log'],
							'logs/moderation' => ['moderation_log'],
							'logs/settings' => ['log_settings'],
						],
					],
				],
			],
			'system' => [
				'icon' => 'fas fa-wrench',
				'label' => new Phrase('Admin:system'),
				'sections' => [
					'systemsettings' => [
						'label' => new Phrase('Admin:system_settings'),
						'items' => [
							'system/security' => ['security_settings'],
							'system/phpinfo' => ['php_info'],
						],
					],
					'mail' => [
						'label' => new Phrase('Admin:mail_settings'),
						'items' => [
							'system/mail/settings' => ['mail_settings'],
							'system/mail/queue' => ['mail_queue'],
						],
					],
					'tasks' => [
						'label' => new Phrase('Admin:tasks'),
						'items' => [
							'system/tasks/scheduled' => ['scheduled_tasks'],
							'system/tasks/adhoc' => ['adhoc_tasks'],
						],
					],
				],
			],
		];

		// Reset dashboard to be visible to everything.
		$permissions = [];
		$url = Container::instance()->get('url');
		$currentuser = Container::instance()->get('currentuser');

		foreach ($admin as $toplevelid => $toplevelitem)
		{
			foreach ($toplevelitem['sections'] as $sectionid => $section)
			{
				foreach ($section['items'] as $route => $item)
				{
					if (count($item) == 1)
					{
						$item[] = ['admin_forum'];
					}
					if (count($item) != 2)
					{
						continue;
					}
					[$label, $perms] = $item;
					$permissions = array_merge($permissions, $perms);

					if ($route == $selected_route)
					{
						$foundroute = true;
						$admin[$toplevelid]['active'] = true;
					}

					$phrase = new Phrase('Admin:' . $item[0]);

					try
					{

						$admin[$toplevelid]['sections'][$sectionid]['items'][$route] = [
							'label' => $phrase,
							'permissions' => $item[1],
							'url' => $url->generate($route),
						];
					}
					catch (\Exception $e)
					{
						$admin[$toplevelid]['sections'][$sectionid]['items'][$route] = [
							'label' => $phrase,
							'permissions' => $item[1],
							'url' => '#',
						];
					}

					if ($route == $selected_route)
					{
						$admin[$toplevelid]['sections'][$sectionid]['items'][$route]['active'] = true;
					}
				}
			}
		}
		$admin['configuration']['sections']['overview']['items']['dashboard']['permissions'] = array_unique($permissions);

		foreach ($admin as $toplevelid => $toplevelitem)
		{
			foreach ($toplevelitem['sections'] as $sectionid => $section)
			{
				foreach ($section['items'] as $route => $item)
				{
					if (!$currentuser->can($item['permissions']))
					{
						unset($admin[$toplevelid]['sections'][$sectionid]['items'][$route]);
					}
				}

				if (empty($admin[$toplevelid]['sections'][$sectionid]['items']))
				{
					unset ($admin[$toplevelid]['sections'][$sectionid]);
				}
			}

			if (empty($admin[$toplevelid]['sections']))
			{
				unset($admin[$toplevelid]);
			}
		}

		return $admin;
	}
}
