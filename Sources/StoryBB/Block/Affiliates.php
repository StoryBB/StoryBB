<?php

/**
 * Affiliates block.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Block;

use StoryBB\Container;

/**
 * Affiliates block.
 */
class Affiliates extends AbstractBlock implements Block
{
	protected $config;
	protected $content;

	public function __construct($config = [])
	{
		$this->config = $config;
	}

	public function get_name(): string
	{
		global $txt;
		return $txt['affiliates_title'];
	}

	public function get_default_title(): string
	{
		return 'txt.affiliates_title';
	}

	public function get_block_content(): string
	{
		global $txt, $scripturl, $modSettings;

		if ($this->content !== null)
		{
			return $this->content;
		}
		elseif ($this->content === null)
		{
			$this->content = '';
		}

		$tiers = $this->get_affiliates();

		$this->content = $this->render('block_affiliates', [
			'tiers' => $tiers,
		]);
		return $this->content;
	}

	protected function get_affiliates(): array
	{
		global $smcFunc;

		$tiers = [];

		$request = $smcFunc['db']->query('', '
			SELECT id_tier, tier_name, sort_order, image_width, image_height
			FROM {db_prefix}affiliate_tier
			ORDER BY sort_order');
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$row['affiliates'] = [];
			$tiers[$row['id_tier']] = $row;
		}
		$smcFunc['db']->free_result($request);

		$container = Container::instance();
		$urlgenerator = $container->get('urlgenerator');

		$request = $smcFunc['db']->query('', '
			SELECT a.id_affiliate, a.affiliate_name, a.url, a.image_url, a.id_tier, f.timemodified
			FROM {db_prefix}affiliate AS a
				LEFT JOIN {db_prefix}files AS f ON (f.handler = {literal:affiliate} AND f.content_id = a.id_affiliate)
			WHERE a.enabled = {int:enabled}
			ORDER BY a.id_tier, a.sort_order',
			[
				'enabled' => 1,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!isset($tiers[$row['id_tier']]))
			{
				continue;
			}

			if ($row['image_url'])
			{
				$row['image'] = $row['image_url'];
			}
			elseif (!empty($row['timemodified']))
			{
				$row['image'] = $urlgenerator->generate('affiliate', ['id' => $row['id_affiliate'], 'timestamp' => $row['timemodified']]);
			}
			$tiers[$row['id_tier']]['affiliates'][$row['id_affiliate']] = $row;
		}
		$smcFunc['db']->free_result($request);

		// Prune any empty tiers.
		foreach ($tiers as $tier_id => $tier)
		{
			if (empty($tier['affiliates']))
			{
				unset($tiers[$tier_id]);
			}
			else
			{
				addInlineCss('.blocktype_affiliates .affiliate_tier_' . $tier_id . ' img { max-width: ' . $tier['image_width'] . 'px; max-height: ' . $tier['image_height'] . 'px }');
			}
		}

		return $tiers;
	}
}
