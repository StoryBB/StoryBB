<?php
require(__DIR__ . '/../../vendor/autoload.php');
use LightnCandy\LightnCandy;
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */
 
 function custom_fields_helper($field, $options) {
 	return new \LightnCandy\SafeString('<td class="righttext">' . $options[$field] . '</td>');
 }

/**
 * Displays a sortable listing of all members registered on the forum.
 */
function template_main()
{
	global $context, $settings, $scripturl, $txt;
	
		$data = Array(
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'settings' => $settings
	);
	
	$template = file_get_contents(__DIR__ .  "/templates/memberlist_main.hbs");
	if (!$template) {
		die('Member template did not load!');
	}

	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_RUNTIMEPARTIAL,
	    'partials' => Array(
	    	'button_strip' => file_get_contents(__DIR__ .  "/partials/button_strip.hbs")
	    ),
	    'helpers' => Array(
	    	'custom_fields' => 'custom_fields_helper'
	    )
	));
	
	//var_dump($context['meta_tags']);die();
	$renderer = LightnCandy::prepare($phpStr);
	return $renderer($data);


	// Assuming there are members loop through each one displaying their data.
	if (!empty($context['members']))
	{
		foreach ($context['members'] as $member)
		{

		// Show custom fields marked to be shown here
		if (!empty($context['custom_profile_fields']['columns']))
		{
			foreach ($context['custom_profile_fields']['columns'] as $key => $column)
				echo '
					<td class="righttext">', $member['options'][$key], '</td>';
		}

		echo '
				</tr>';
		}
	}


}

/**
 * A page allowing people to search the member list.
 */
function template_search()
{
	global $context, $scripturl, $txt;

	// Start the submission form for the search!
	echo '
	<form action="', $scripturl, '?action=mlist;sa=search" method="post" accept-charset="', $context['character_set'], '">
		<div id="memberlist">
			<div class="pagesection">
				', template_button_strip($context['memberlist_buttons'], 'right'), '
			</div>
			<div class="cat_bar">
				<h3 class="catbg mlist">
					<span class="generic_icons filter"></span>', $txt['mlist_search'], '
				</h3>
			</div>
			<div id="advanced_search" class="roundframe noup">
				<dl id="mlist_search" class="settings">
					<dt>
						<label><strong>', $txt['search_for'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="search" value="', $context['old_search'], '" size="40" class="input_text">
					</dd>
					<dt>
						<label><strong>', $txt['mlist_search_filter'], ':</strong></label>
					</dt>
					<dd>
						<ul>';

	foreach ($context['search_fields'] as $id => $title)
	{
		echo '
							<li>
								<input type="checkbox" name="fields[]" id="fields-', $id, '" value="', $id, '"', in_array($id, $context['search_defaults']) ? ' checked' : '', ' class="input_check">
								<label for="fields-', $id, '">', $title, '</label>
							</li>';
	}

	echo '
						</ul>
					</dd>
				</dl>
				<input type="submit" name="submit" value="' . $txt['search'] . '" class="button_submit">
			</div>
		</div>
	</form>';
}

?>