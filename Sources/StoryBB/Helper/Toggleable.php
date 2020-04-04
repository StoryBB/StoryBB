<?php

/**
 * A library for setting up autocompletes.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper;

class Toggleable
{
	private static $instance = 0;
	private $config = [
		'enabled' => true,
		'animation' => true,
		'name' => '',
		'swappable_containers' => [],
		'image_toggles' => [],
		'link_toggles' => [],
	];

	public function __construct(string $name)
	{
		$this->config['name'] = $name;
	}

	public function enabled(bool $enabled): Toggleable
	{
		$this->config['enabled'] = $enabled;
		return $this;
	}

	public function animation(bool $animate): Toggleable
	{
		$this->config['animation'] = $animate;
		return $this;
	}

	public function addCollapsible(string $selector)
	{
		$this->config['swappable_containers'][] = $selector;
	}

	public function addImageToggle(string $selector, string $expanded = null, string $collapsed = null): Toggleable
	{
		global $txt;
		$this->config['image_toggles'][] = [
			'sId' => $selector,
			'altExpanded' => !empty($expanded) ? $expanded : sprintf($txt['hide_toggleable'], $this->config['name']),
			'altCollapsed' => !empty($collapsed) ? $collapsed : sprintf($txt['show_toggleable'], $this->config['name']),
		];
		return $this;
	}

	public function addLinkToggle(string $selector, string $expanded = null, string $collapsed = null): Toggleable
	{
		$this->config['link_toggles'][] = [
			'sId' => $selector,
			'msgExpanded' => !empty($expanded) ? $expanded : $this->config['name'],
			'msgCollapsed' => !empty($collapsed) ? $collapsed : $this->config['name'],
		];
		return $this;
	}

	public function userOption(string $identifier): Toggleable
	{
		$this->config['useroption'] = $identifier;
		return $this;
	}

	public function cookieName(string $cookiename): Toggleable
	{
		$this->config['cookiename'] = $cookiename;
		return $this;
	}

	public function currently_collapsed(): bool
	{
		global $user_info, $options;

		if (!$user_info['is_guest'] && isset($this->config['useroption']))
		{
			return !empty($options[$this->config['useroption']]);
		}
		if ($user_info['is_guest'] && isset($this->config['cookiename']))
		{
			return !empty($_COOKIE[$this->config['cookiename']]);
		}

		return false;
	}

	public function attach()
	{
		global $user_info, $context;

		if (!$this->config['enabled'] || empty($this->config['swappable_containers']))
		{
			return;
		}

		$config = [
			'bToggleEnabled' => $this->config['enabled'],
			'bCurrentlyCollapsed' => $this->currently_collapsed(),
			'bNoAnimate' => empty($this->config['animation']),
			'aSwappableContainers' => $this->config['swappable_containers'],
		];
		if (!empty($this->config['image_toggles']))
		{
			$config['aSwapImages'] = $this->config['image_toggles'];
		}
		if (!empty($this->config['link_toggles']))
		{
			$config['aSwapLinks'] = $this->config['link_toggles'];
		}

		if (!$user_info['is_guest'] && !empty($this->config['useroption']))
		{
			$config['oThemeOptions'] = [
				'bUseThemeSettings' => true,
				'sOptionName' => $this->config['useroption'],
				'sSessionId' => $context['session_id'],
				'sSessionVar' => $context['session_var'],
			];
		}
		if ($user_info['is_guest'] && !empty($this->config['cookiename']))
		{
			$config['oCookieOptions'] = [
				'bUseCookie' => true,
				'sCookieName' => $this->config['cookiename'],
			];
		}

		$id = 'sbbToggle' . (++static::$instance);
		addInlineJavaScript('var ' . $id . ' = new smc_Toggle(' . json_encode($config) . ');', true);
	}
}
