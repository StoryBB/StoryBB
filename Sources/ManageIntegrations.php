<?php

/**
 * Manages integrations.
 * @todo refactor as controller-model
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\ClassManager;
use StoryBB\Database\DatabaseAdapter;
use StoryBB\Hook\AbstractIntegratable;
use StoryBB\Integration\Integration;

function ManageIntegrations()
{
	global $context;

	loadLanguage('ManageIntegrations');

	isAllowedTo('admin_forum');

	$subActions = [
		'list_integrations' => 'ListIntegrations',
		'add_integration' => 'AddIntegration',
		'edit_integration' => 'EditIntegration',
	];

	$context['sub_action'] = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'list_integrations';
	$subActions[$context['sub_action']]();
}

function ListIntegrations()
{
	global $smcFunc, $context, $txt, $scripturl;

	$context['page_title'] = $txt['integrations'];

	$possible_integrations = get_possible_integrations();
	$possible_methods = [];
	foreach ($possible_integrations as $integration_class)
	{
		$integration = new $integration_class['class'];
		$possible_methods[$integration_class['class']] = get_possible_integration_methods($integration);
	}

	$context['integrations'] = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_integration, integratable, integration, active, options
		FROM {db_prefix}integrations
		ORDER BY id_integration');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($possible_integrations[$row['integration']]))
		{
			continue;
		}
		$methods = array_flip($possible_methods[$possible_integrations[$row['integration']]['class']]);
		if (!isset($methods[$row['integratable']]))
		{
			continue;
		}
		$context['integrations'][$row['id_integration']] = [
			'integration' => $possible_integrations[$row['integration']],
			'trigger_event' => $txt['integration_' . $methods[$row['integratable']]],
			'edit_link' => $scripturl . '?action=admin;area=integrations;sa=edit_integration;integration=' . $row['id_integration'],
			'active' => !empty($row['active']),
		];

		$options = @json_decode($row['options'], true) ?? [];
		$rules = [];
		if (isset($options['boards']) && is_array($options['boards']))
		{
			if (in_array('ic', $options['boards']))
			{
				$rules[] = $txt['in_all_character_boards'];
				$options['boards'] = array_diff($options['boards'], ['ic']);
			}
			if (in_array('ooc', $options['boards']))
			{
				$rules[] = $txt['in_all_ooc_boards'];
				$options['boards'] = array_diff($options['boards'], ['ooc']);
			}
			if (!empty($options['boards']))
			{
				$rules[] = numeric_context('in_x_boards', count($options['boards']));
			}
		}
		if (!empty($rules))
		{
			$context['integrations'][$row['id_integration']]['subtext'] = implode(', ', $rules);
		}
	}
	$smcFunc['db']->free_result($request);

	$context['add_integrations'] = get_possible_integrations();
	$context['sub_template'] = 'admin_integrations_list';
}

function AddIntegration()
{
	global $smcFunc, $context, $txt, $scripturl;

	if (isset($_GET['integration']) && isset($_GET['method']))
	{
		checkSession('get');

		$possible_integrations = get_possible_integrations();
		if (!isset($possible_integrations[$_GET['integration']]))
		{
			redirectexit('action=admin;area=integrations');
		}

		$integration = new $possible_integrations[$_GET['integration']]['class'];
		$possible_methods = get_possible_integration_methods($integration);

		if (!isset($possible_methods[$_GET['method']]))
		{
			redirectexit('action=admin;area=integrations');
		}

		// This is very straightforward, create an empty integration and redirect to the editing screen.
		$inserted_id = $smcFunc['db']->insert(DatabaseAdapter::INSERT_INSERT,
			'{db_prefix}integrations',
			['integratable' => 'string', 'integration' => 'string', 'active' => 'int', 'options' => 'string'],
			[$possible_methods[$_GET['method']], $_GET['integration'], 0, '[]'],
			['id_integration'],
			DatabaseAdapter::RETURN_LAST_ID
		);

		redirectexit('action=admin;area=integrations;sa=edit_integration;integration=' . $inserted_id);
	}

	if (isset($_GET['integration']))
	{
		$possible_integrations = get_possible_integrations();
		if (!isset($possible_integrations[$_GET['integration']]))
		{
			redirectexit('action=admin;area=integrations');
		}

		$integration = new $possible_integrations[$_GET['integration']]['class'];

		$context['add_integration_methods'] = [];
		foreach (get_possible_integration_methods($integration) as $method => $class)
		{
			$context['add_integration_methods'][] = [
				'url' => $scripturl . '?action=admin;area=integrations;sa=add_integration;integration=' . $_GET['integration'] . ';method=' . $method . ';' . $context['session_var'] . '=' . $context['session_id'],
				'label' => $txt['integration_' . $method],
			];
		}

		$context['page_title'] = sprintf($txt['add_integration_name'], $integration::NAME);
		$context['sub_template'] = 'admin_integrations_add';
		return;
	}

	redirectexit('action=admin;area=integrations');
}

function EditIntegration()
{
	global $smcFunc, $context, $txt, $scripturl;

	$integration_id = isset($_GET['integration']) ? (int) $_GET['integration'] : 0;

	$request = $smcFunc['db']->query('', '
		SELECT id_integration, integratable, integration, active, options
		FROM {db_prefix}integrations
		WHERE id_integration = {int:integration}',
		[
			'integration' => $integration_id,
		]
	);
	$row = $smcFunc['db']->fetch_assoc($request);
	$options = @json_decode($row['options'], true) ?? [];
	$smcFunc['db']->free_result($request);

	if (empty($row))
	{
		redirectexit('action=admin;area=integrations');
	}

	$possible_integrations = get_possible_integrations();

	$integration = new $possible_integrations[$row['integration']]['class'];
	$methods = array_flip(get_possible_integration_methods($integration));

	$context['integration'] = [
		'id' => $row['id_integration'],
		'integration' => $possible_integrations[$row['integration']],
		'triggers' => $txt['integration_' . $methods[$row['integratable']]],
		'configuration' => [
			'active' => [
				'label' => $txt['integration_is_active'],
				'default' => false,
				'current' => !empty($row['active']),
				'type' => 'boolean',
			],
		],
	];

	$settings_method = $methods[$row['integratable']] . '_settings';
	if (method_exists($integration, $settings_method))
	{
		foreach ($integration->$settings_method() as $setting => $config_opts)
		{
			$config_opts['current'] = $options[$setting] ?? $config_opts['default'];
			$context['integration']['configuration'][$setting] = $config_opts;
		}
	}

	$context['integration']['extra'] = [];
	$possible_boards = null;
	foreach ($context['integration']['configuration'] as $setting_name => $setting)
	{
		if ($setting['type'] == 'boards')
		{
			if ($possible_boards === null)
			{
				$possible_boards = integration_load_possible_boards();
			}
			$context['integration']['configuration'][$setting_name]['board_data'] = $possible_boards;

			if (in_array('ic', $setting['current']))
			{
				$context['integration']['configuration'][$setting_name]['board_data']['ic'] = true;
			}
			if (in_array('ooc', $setting['current']))
			{
				$context['integration']['configuration'][$setting_name]['board_data']['ooc'] = true;
			}
			foreach ($context['integration']['configuration'][$setting_name]['board_data']['boards_categories'] as $id_cat => $category)
			{
				foreach (array_keys($category['boards']) as $id_board)
				{
					if (in_array($id_board, $setting['current']))
					{
						$context['integration']['configuration'][$setting_name]['board_data']['boards_categories'][$id_cat]['boards'][$id_board]['active'] = true;
					}
				}
			}
			break;
		}
	}
	$context['errors'] = [];

	if (isset($_POST['save']))
	{
		checkSession();

		foreach ($context['integration']['configuration'] as $setting_name => $setting)
		{
			switch ($setting['type'])
			{
				case 'boolean':
					$context['integration']['configuration'][$setting_name]['current'] = !empty($_POST[$setting_name]);
					break;

				case 'color':
					$value = $_POST[$setting_name] ?? '';
					if (!empty($value))
					{
						$value = trim($value);
						$valid = false;
						if (preg_match('/^#[0-9a-f]{3}$/', $value))
						{
							$valid = true;
						}
						elseif (preg_match('/^#[0-9a-f]{6}$/', $value))
						{
							$valid = true;
						}
						if (!$valid)
						{
							$context['errors'][$setting_name] = $setting['label'] . ' - ' . $txt['please_enter_valid_color'];
						}
					}
					$context['integration']['configuration'][$setting_name]['current'] = $value;
					break;

				case 'boards':
					$value = isset($_POST[$setting_name]) && is_array($_POST[$setting_name]) ? $_POST[$setting_name] : [];
					$value = array_intersect(array_values($value), $setting['board_data']['valid_ids']);
					$value = array_map(function ($x) {
						return $x == 'ic' || $x == 'ooc' ? $x : (int) $x;
					}, $value);

					$context['integration']['configuration'][$setting_name]['board_data']['ic'] = in_array('ic', $value);
					$context['integration']['configuration'][$setting_name]['board_data']['ooc'] = in_array('ooc', $value);
					foreach ($context['integration']['configuration'][$setting_name]['board_data']['boards_categories'] as $id_cat => $category)
					{
						foreach (array_keys($category['boards']) as $id_board)
						{
							$context['integration']['configuration'][$setting_name]['board_data']['boards_categories'][$id_cat]['boards'][$id_board]['active'] = in_array($id_board, $value);
						}
					}
					$context['integration']['configuration'][$setting_name]['current'] = $value;
					break;

				case 'text':
					$value = $_POST[$setting_name] ?? '';
					$value = trim($value);
					if (empty($value) && !empty($setting['required']))
					{
						$context['errors'][$setting_name] = $setting['label'] . ' - ' . $txt['field_is_required'];
					}
					$context['integration']['configuration'][$setting_name]['current'] = $value;
					break;

				case 'url':
					$value = $_POST[$setting_name] ?? '';
					$value = trim($value);
					if (!empty($value))
					{
						$filter = filter_var($value, FILTER_VALIDATE_URL);
						if (empty($filter))
						{
							$context['errors'][$setting_name] = $setting['label'] . ' - ' . $txt['please_enter_valid_url'];
						}
					}
					elseif (!empty($setting['required']))
					{
						$context['errors'][$setting_name] = $setting['label'] . ' - ' . $txt['field_is_required'];
					}
					$context['integration']['configuration'][$setting_name]['current'] = $value;
					break;
			}
		}

		if (empty($context['errors']))
		{
			$active = $context['integration']['configuration']['active']['current'] ? 1 : 0;
			unset($context['integration']['configuration']['active']);

			$configuration = [];
			foreach ($context['integration']['configuration'] as $setting_name => $setting)
			{
				$configuration[$setting_name] = $setting['current'];
			}
			$options = json_encode($configuration);

			$smcFunc['db']->query('', '
				UPDATE {db_prefix}integrations
				SET active = {int:active},
					options = {string:options}
				WHERE id_integration = {int:integration}',
				[
					'active' => $active,
					'options' => $options,
					'integration' => $context['integration']['id'],
				]
			);
			redirectexit('action=admin;area=integrations');
		}
	}

	$context['page_title'] = sprintf($txt['edit_integration_name'], $possible_integrations[$row['integration']]['name']);
	$context['sub_template'] = 'admin_integrations_edit';
}

function get_possible_integrations(): array
{
	$connectors = [];
	foreach (ClassManager::get_classes_implementing('StoryBB\\Integration\\Integration') as $connector)
	{
		$classname = strtolower(substr(strrchr($connector, '\\'), 1));
		$connectors[$classname] = [
			'name' => $connector::NAME,
			'icon' => $connector::ICON,
			'big_icon' => 'fa-2x fa-fw ' . $connector::ICON,
			'class' => $connector,
		];
	}
	return $connectors;
}

function get_possible_integration_methods(Integration $integration)
{
	$integratables = [];

	foreach (ClassManager::get_classes_implementing('StoryBB\\Hook\\Integratable') as $integratable)
	{
		$method = AbstractIntegratable::get_method($integratable);
		if (method_exists($integration, $method))
		{
			$integratables[$method] = $integratable;
		}
	}

	return $integratables;
}

function integration_load_possible_boards()
{
	global $smcFunc;

	$possible_boards = [
		'valid_ids' => ['ic', 'ooc'],
		'ic' => false,
		'ooc' => false,
		'boards_categories' => [],
	];

	$request = $smcFunc['db']->query('', '
		SELECT b.id_board, b.name AS board_name, b.id_cat, c.name AS cat_name, b.child_level
		FROM {db_prefix}boards AS b
		INNER JOIN {db_prefix}categories AS c ON (b.id_cat = c.id_cat)
		ORDER BY board_order',
		[
			'prefix' => $context['prefix']['id_prefix'] ?? 0,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($possible_boards['boards_categories'][$row['id_cat']]))
		{
			$possible_boards['boards_categories'][$row['id_cat']] = [
				'id_cat' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => [],
			];
		}

		$possible_boards['boards_categories'][$row['id_cat']]['boards'][$row['id_board']] = [
			'id_board' => $row['id_board'],
			'name' => $row['board_name'],
			'child_level' => $row['child_level'],
			'active' => false,
		];

		$possible_boards['valid_ids'][] = $row['id_board'];
	}
	$smcFunc['db']->free_result($request);

	return $possible_boards;
}
