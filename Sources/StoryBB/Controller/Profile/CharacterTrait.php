<?php

/**
 * Abstract profile controller (hybrid style)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\Parser;

trait CharacterTrait
{
	public function init_character()
	{
		global $user_profile, $context, $scripturl, $modSettings, $smcFunc, $txt, $user_info;

		$memID = $this->params['u'];

		$char_id = isset($_GET['char']) ? (int) $_GET['char'] : 0;
		if (!isset($user_profile[$memID]['characters'][$char_id])) {
			// character doesn't exist... bye.
			redirectexit('action=profile;u=' . $memID);
		}

		$context['character'] = $user_profile[$memID]['characters'][$char_id];
		$context['character']['editable'] = $context['user']['is_owner'] || allowedTo('admin_forum');
		$context['user']['can_admin'] = allowedTo('admin_forum');

		$context['character']['retire_eligible'] = !$context['character']['is_main'];
		if ($context['user']['is_owner'] && $user_info['id_character'] == $context['character']['id_character'])
		{
			$context['character']['retire_eligible'] = false; // Can't retire if you're logged in as them
		}

		return $char_id;
	}

	public function load_custom_fields($all = false, $in_character = true)
	{
		global $smcFunc, $context;

		// And load the custom fields.
		$request = $smcFunc['db']->query('', '
			SELECT c.id_field, c.col_name, c.field_name, c.field_desc, c.field_type, c.field_order, c.field_length, c.field_options, c.mask, c.show_reg,
			c.show_display, c.show_profile, c.private, c.active, c.bbc, c.can_search, c.default_value, c.enclose, c.placement
			FROM {db_prefix}custom_fields AS c
			WHERE in_character = {int:in_character}
				AND active = 1
			ORDER BY field_order',
			[
				'in_character' => $in_character ? 1 : 0,
			]
		);
		$context['character']['custom_fields'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// cfraw values are populated by loadMemberData for all characters on an account.
			if (!$all && empty($context['character']['cfraw'][$row['id_field']]))
			{
				continue;
			}

			$row['raw_value'] = $context['character']['cfraw'][$row['id_field']] ?? '';
			$row += $this->get_custom_field_html($row, $row['raw_value']);

			$context['character']['custom_fields'][] = $row;
		}
		$smcFunc['db']->free_result($request);
	}

	public function get_custom_field_html($custom, $value)
	{
		global $txt, $settings, $scripturl;

		// HTML for the input form.
		$output_html = $value;
		if ($custom['field_type'] == 'check')
		{
			$true = (!$exists && $custom['default_value']) || $value;
			$input_html = '<input type="checkbox" name="customfield[' . $custom['id_field'] . ']" id="customfield[' . $custom['id_field'] . ']"' . ($true ? ' checked' : '') . '>';
			$output_html = $true ? $txt['yes'] : $txt['no'];
		}
		elseif ($custom['field_type'] == 'select')
		{
			$input_html = '<select name="customfield[' . $custom['id_field'] . ']" id="customfield[' . $custom['id_field'] . ']"><option value="-1"></option>';
			$options = explode(',', $custom['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $custom['default_value'] == $v) || $value == $v;
				$input_html .= '<option value="' . $k . '"' . ($true ? ' selected' : '') . '>' . $v . '</option>';
				if ($true)
					$output_html = $v;
			}

			$input_html .= '</select>';
		}
		elseif ($custom['field_type'] == 'radio')
		{
			$input_html = '<fieldset>';
			$options = explode(',', $custom['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $custom['default_value'] == $v) || $value == $v;
				$input_html .= '<label for="customfield_' . $custom['id_field'] . '_' . $k . '"><input type="radio" name="customfield[' . $custom['id_field'] . ']" id="customfield_' . $custom['id_field'] . '_' . $k . '" value="' . $k . '"' . ($true ? ' checked' : '') . '>' . $v . '</label><br>';
				if ($true)
					$output_html = $v;
			}
			$input_html .= '</fieldset>';
		}
		elseif ($custom['field_type'] == 'text')
		{
			$input_html = '<input type="text" name="customfield[' . $custom['id_field'] . ']" id="customfield[' . $custom['id_field'] . ']"' . ($custom['field_length'] != 0 ? ' maxlength="' . $custom['field_length'] . '"' : '') . ' size="' . ($custom['field_length'] == 0 || $custom['field_length'] >= 50 ? 50 : ($custom['field_length'] > 30 ? 30 : ($custom['field_length'] > 10 ? 20 : 10))) . '" value="' . un_htmlspecialchars($value) . '"' . ($custom['show_reg'] == 2 ? ' required' : '') . '>';
		}
		else
		{
			@list ($rows, $cols) = @explode(',', $custom['default_value']);
			$input_html = '<textarea name="customfield[' . $custom['id_field'] . ']" id="customfield[' . $custom['id_field'] . ']"' . (!empty($rows) ? ' rows="' . $rows . '"' : '') . (!empty($cols) ? ' cols="' . $cols . '"' : '') . ($custom['show_reg'] == 2 ? ' required' : '') . '>' . un_htmlspecialchars($value) . '</textarea>';
		}

		// Parse BBCode
		if ($custom['bbc'])
			$output_html = Parser::parse_bbc($output_html);
		elseif ($custom['field_type'] == 'textarea')
			// Allow for newlines at least
			$output_html = strtr($output_html, ["\n" => '<br>']);

		// Enclosing the user input within some other text?
		if (!empty($custom['enclose']) && !empty($output_html))
			$output_html = strtr($custom['enclose'], [
				'{SCRIPTURL}' => $scripturl,
				'{IMAGES_URL}' => $settings['images_url'],
				'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
				'{INPUT}' => un_htmlspecialchars($output_html),
			]);

		return [
			'input_html' => $input_html,
			'output_html' => $output_html,
		];
	}
}
