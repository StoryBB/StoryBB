<?php

/**
 * This class provides miscellaneous helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

/**
 * This class provides miscellaneous helpers for StoryBB's templates.
 */
class Misc
{
	/**
	 * List the different helpers available in this class.
	 * @return array Helpers, assocating name to method
	 */
	public static function _list()
	{
		return ([
			'timeformat' => 'StoryBB\\Template\\Helper\\Misc::timeformat',
			'comma' => 'StoryBB\\Template\\Helper\\Misc::comma',
			'dynamicpartial' => 'StoryBB\\Template\\Helper\\Misc::dynamicpartial',
			'json' => 'StoryBB\\Template\\Helper\\Misc::json',
			'breakRow' => 'StoryBB\\Template\\Helper\\Misc::breakrow',
			'is_numeric' => 'StoryBB\\Template\\Helper\\Misc::is_numeric',
			'block_region' => 'StoryBB\\Template\\Helper\\Misc::block_region',
		]);
	}

	/**
	 * Formats a timestamp according to user preferences
	 * @param int $timestamp The timestamp to output (Unix epoch etc)
	 * @return string The time suitably formatted
	 */
	public static function timeformat($timestamp)
	{
		return timeformat($timestamp); // See Subs.php
	}

	/**
	 * Accepts a number and converts it to include commas according to current locale settings.
	 * @param int|float $number A number to output
	 * @return string The number formatted with thousands and decimals separators
	 */
	public static function comma($number)
	{
		return comma_format($number); // See Subs.php
	}

	/**
	 * Loads and renders a partial by way of the name of the partial being supplied
	 * @param string $partial A partial to load (can be a dynamic expression result)
	 * @return $string The rendered partial
	 */
	public static function dynamicpartial($partial)
	{
		global $context, $txt, $scripturl, $settings, $modSettings, $options;
		$template = \StoryBB\Template::load_partial($partial);
		$phpStr = \StoryBB\Template::compile($template, [], 'dynamicpartial-' . $settings['theme_id'] . '-' . $partial);
		return \StoryBB\Template::prepare($phpStr, [
			'context' => $context,
			'txt' => $txt,
			'scripturl' => $scripturl,
			'settings' => $settings,
			'modSettings' => $modSettings,
			'options' => $options,
		]);
	}

	/**
	 * Exports arbitrary data in JSON format for templates
	 * @param mixed $data The data to export
	 * @return string JSON to be exported
	 */
	public static function json($data)
	{
		return json_encode($data instanceof \LightnCandy\SafeString ? (string) $data : $data);
	}

	/**
	 * Issues a separator to break a row after a number of items.
	 * @param int $index The current index from the loop of items
	 * @param int $perRow The number of items per row
	 * @param string $sep The separator HTML between rows
	 * @return string HTML, conditionally the separator if we're correctly between rows
	 */
	public static function breakrow($index, $perRow, $sep)
	{
		$perRow = (int) $perRow;
		if ($perRow == 0) {
			return '';
		}
		if ($index > 0 && $index % $perRow == 0) return $sep;
		return '';
	}

	/**
	 * Checks if a variable is numeric for template purposes.
	 * @param mixed $x Variable to check
	 * @return bool True if $x is numeric
	 */
	public static function is_numeric($x)
	{
		return is_numeric($x);
	}

	/**
	 * Outputs the blocks known to be relevant in the named section of the page.
	 *
	 * @param string $region The name of the block region
	 * @return string The HTML to be inserted into the block region
	 */
	public static function block_region($region)
	{
		global $context;

		if (empty($context['page_blocks'][$region]))
		{
			return '';
		}

		$block_context = [
			'region' => $region,
			'instances' => [],
		];

		$template_cache = [];
		$compiled_cache = [];

		foreach ($context['page_blocks'][$region] as $instance_id => $instance)
		{
			$partial_name = $instance->get_render_template();

			if (!isset($template_cache[$partial_name]))
			{
				$template_cache[$partial_name] = \StoryBB\Template::load_partial($partial_name);
			}
			$template = $template_cache[$partial_name];

			if (!isset($compiled_cache[$partial_name]))
			{
				$compiled_cache[$partial_name] = \StoryBB\Template::compile($template, [], $partial_name . \StoryBB\Template::get_theme_id('partials', $partial_name));
			}
			$phpStr = $compiled_cache[$partial_name];

			$block_context['instances'][] = \StoryBB\Template::prepare($phpStr, [
				'instance' => $instance_id,
				'title' => new \LightnCandy\SafeString($instance->get_block_title()),
				'content' => new \LightnCandy\SafeString($instance->get_block_content()),
				'blocktype' => strtolower(basename(get_class($instance))),
			]);
		}

		$template_region = \StoryBB\Template::load_partial('block_region');
		$phpStr = \StoryBB\Template::compile($template_region, [], 'block_region' . \StoryBB\Template::get_theme_id('partials', 'block_region'));

		return new \LightnCandy\SafeString(\StoryBB\Template::prepare($phpStr, [
			'region' => $region,
			'instances' => $block_context['instances'],
		]));
	}
}
