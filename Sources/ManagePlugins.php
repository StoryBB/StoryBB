<?php

/**
 * This file has all the main functions in it that relate to, well, everything.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Plugin\Manager;

function PluginsHome()
{
	global $txt, $context;

	// General stuff
	loadLanguage('ManagePlugins');

	// Because our good friend the generic menu complains otherwise.
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['plugin_manager'],
		'description' => $txt['plugin_manager_desc'],
		'tabs' => [
			'plugins' => [
			],
		],
	];

	$subActions = [
		'list' => 'PluginsList',
		'action' => 'PluginAction',
	];

	// By default do the basic settings.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : key($subActions);
	$context['sub_action'] = $_REQUEST['sa'];
	$subActions[$_REQUEST['sa']]();
}

function PluginsList()
{
	global $txt, $context, $boarddir, $smcFunc, $scripturl;

	// 1. Some initial setup, and getting the list of enabled plugins.
	$context['sub_template'] = 'admin_plugins_list';
	$context['page_title'] = $txt['plugin_manager'];

	$available_plugins = Manager::get_available_plugins();

	// 2. Deal with any filtering. We have to do it here, rather than earlier, simply because we need to have processed everything beforehand.
	$context['filter_plugins'] = [
		'all' => 0,
		'enabled' => 0,
		'disabled' => 0,
		'install_errors' => 0,
	];
	$context['current_filter'] = isset($_GET['filter']) && isset($context['filter_plugins'][$_GET['filter']]) ? $_GET['filter'] : 'all';
	foreach ($available_plugins as $id => $plugin)
	{
		$type = 'disabled';
		if ($plugin->enabled())
		{
			$type = 'enabled';
		}
		elseif (!$plugin->installable())
		{
			$type = 'install_errors';
		}

		$context['filter_plugins']['all']++;
		$context['filter_plugins'][$type]++;

		if ($context['current_filter'] === 'all' || $context['current_filter'] === $type)
		{
			$context['available_plugins'][] = [
				'name' => $plugin->name(),
				'id' => $plugin->folder(),
				'author' => $plugin->author(),
				'description' => $plugin->description(),
				'status' => $type,
				'install_errors' => $type === 'install_errors' ? $plugin->install_errors() : [],
			];
		}
	}

	$context['form_action'] = $scripturl . '?action=admin;area=plugins;sa=action';

	createToken('admin-plugin');
}

function PluginAction()
{
	global $txt, $context;

	$available_plugins = Manager::get_available_plugins();

	checkSession();
	validateToken('admin-plugin');

	if (isset($_POST['remove']))
	{

		redirectexit('action=admin;area=plugins');
	}

	if (isset($_POST['enable']))
	{
		if (isset($available_plugins[$_POST['enable']]))
		{
			$plugin = $available_plugins[$_POST['enable']];
			if ($plugin->installable())
			{
				Manager::enable_plugin($plugin);
			}
		}
		redirectexit('action=admin;area=plugins');
	}

	if (isset($_POST['disable']))
	{
		if (isset($available_plugins[$_POST['disable']]))
		{
			$plugin = $available_plugins[$_POST['disable']];
			if ($plugin->enabled())
			{
				Manager::disable_plugin($plugin);
			}
		}
		redirectexit('action=admin;area=plugins');
	}
}
