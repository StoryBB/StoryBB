<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * This template handles displaying a list
 *
 * @param string $list_id The list ID. If null, uses $context['default_list'].
 */
function template_show_list($list_id = null)
{
	global $context;
		// Get a shortcut to the current list.
	$list_id = $list_id === null ? (!empty($context['default_list']) ? $context['default_list'] : '') : $list_id;
	if (empty($list_id) || empty($context[$list_id]))
		return;
	$cur_list = &$context[$list_id];
	
	if (isset(
            $cur_list['list_menu'], 
            $cur_list['list_menu']['show_on']) && ($cur_list['list_menu']['show_on'] == 'both' || $cur_list['list_menu']['show_on'] == 'top'
            )
        )
		$menu = template_create_list_menu($cur_list['list_menu'], 'top');
	else 
		$menu = '';
	
	
	$data = Array(
		'context' => $context,
		'cur_list' => $cur_list,
		'top_menu' => $menu,
		'headerCount' => count($cur_list['headers'])
	);
	
	$template = file_get_contents(__DIR__ .  "/partials/generic_list.hbs");
	if (!$template) {
		die('Template did not load!');
	}
	
	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'partials' => Array(
	    	'list_additional_rows' => file_get_contents(__DIR__ .  "/partials/list_additional_rows.hbs")
	    ),
	    'helpers' => Array(
	    	'concat' => concat
	    )
	));
	
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);
}

/**
 * This template displays additional rows above or below the list.
 *
 * @param string $row_position The position ('top', 'bottom', etc.)
 * @param array $cur_list An array with the data for the current list
 */
function template_additional_rows($row_position, $cur_list)
{
	foreach ($cur_list['additional_rows'][$row_position] as $row)
		echo '
			<div class="additional_row', empty($row['class']) ? '' : ' ' . $row['class'], '"', empty($row['style']) ? '' : ' style="' . $row['style'] . '"', '>', $row['value'], '</div>';
}

/**
 * This function creates a menu
 *
 * @param array $list_menu An array of menu data
 * @param string $direction Which direction the items should go
 */
function template_create_list_menu($list_menu, $direction = 'top')
{
	global $context;

	/**
		// This is use if you want your generic lists to have tabs.
		$cur_list['list_menu'] = array(
			// This is the style to use.  Tabs or Buttons (Text 1 | Text 2).
			// By default tabs are selected if not set.
			// The main difference between tabs and buttons is that tabs get highlighted if selected.
			// If style is set to buttons and use tabs is disabled then we change the style to old styled tabs.
			'style' => 'tabs',
			// The position of the tabs/buttons.  Left or Right.  By default is set to left.
			'position' => 'left',
			// This is used by the old styled menu.  We *need* to know the total number of columns to span.
			'columns' => 0,
			// This gives you the option to show tabs only at the top, bottom or both.
			// By default they are just shown at the top.
			'show_on' => 'top',
			// Links.  This is the core of the array.  It has all the info that we need.
			'links' => array(
				'name' => array(
					// This will tell use were to go when they click it.
					'href' => $scripturl . '?action=theaction',
					// The name that you want to appear for the link.
					'label' => $txt['name'],
					// If we use tabs instead of buttons we highlight the current tab.
					// Must use conditions to determine if its selected or not.
					'is_selected' => isset($_REQUEST['name']),
				),
			),
		);
	*/

	// Are we using right-to-left orientation?
	$first = $context['right_to_left'] ? 'last' : 'first';
	$last = $context['right_to_left'] ? 'first' : 'last';

	if (!isset($list_menu['style']) || isset($list_menu['style']) && $list_menu['style'] == 'tabs')
	{
		echo '
		<table style="margin-', $list_menu['position'], ': 10px; width: 100%;">
			<tr>', $list_menu['position'] == 'right' ? '
				<td>&nbsp;</td>' : '', '
				<td class="', $list_menu['position'], 'text">
					<table>
						<tr>
							<td class="', $direction == 'top' ? 'mirror' : 'main', 'tab_', $first, '">&nbsp;</td>';

		foreach ($list_menu['links'] as $link)
		{
			if ($link['is_selected'])
				echo '
							<td class="', $direction == 'top' ? 'mirror' : 'main', 'tab_active_', $first, '">&nbsp;</td>
							<td class="', $direction == 'top' ? 'mirrortab' : 'maintab', '_active_back">
								<a href="', $link['href'], '">', $link['label'], '</a>
							</td>
							<td class="', $direction == 'top' ? 'mirror' : 'main', 'tab_active_', $last, '">&nbsp;</td>';
			else
				echo '
							<td class="', $direction == 'top' ? 'mirror' : 'main', 'tab_back">
								<a href="', $link['href'], '">', $link['label'], '</a>
							</td>';
		}

		echo '
							<td class="', $direction == 'top' ? 'mirror' : 'main', 'tab_', $last, '">&nbsp;</td>
						</tr>
					</table>
				</td>', $list_menu['position'] == 'left' ? '
				<td>&nbsp;</td>' : '', '
			</tr>
		</table>';
	}
	elseif (isset($list_menu['style']) && $list_menu['style'] == 'buttons')
	{
		$links = array();
		foreach ($list_menu['links'] as $link)
			$links[] = '<a href="' . $link['href'] . '">' . $link['label'] . '</a>';

		echo '
		<table style="margin-', $list_menu['position'], ': 10px; width: 100%;">
			<tr>', $list_menu['position'] == 'right' ? '
				<td>&nbsp;</td>' : '', '
				<td class="', $list_menu['position'], 'text">
					<table>
						<tr>
							<td class="', $direction == 'top' ? 'mirror' : 'main', 'tab_', $first, '">&nbsp;</td>
							<td class="', $direction == 'top' ? 'mirror' : 'main', 'tab_back">', implode(' &nbsp;|&nbsp; ', $links), '</td>
							<td class="', $direction == 'top' ? 'mirror' : 'main', 'tab_', $last, '">&nbsp;</td>
						</tr>
					</table>
				</td>', $list_menu['position'] == 'left' ? '
				<td>&nbsp;</td>' : '', '
			</tr>
		</table>';
	}
}

?>