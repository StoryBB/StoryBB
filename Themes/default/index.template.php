<?php

require_once(__DIR__ . '/helpers/logichelpers.php');
require_once(__DIR__ . '/helpers/mischelpers.php');
use LightnCandy\LightnCandy;
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/*	This template is, perhaps, the most important template in the theme. It
	contains the main template layer that displays the header and footer of
	the forum, namely with main_above and main_below. It also contains the
	menu sub template, which appropriately displays the menu; the init sub
	template, which is there to set the theme up; (init can be missing.) and
	the linktree sub template, which sorts out the link tree.

	The init sub template should load any data and set any hardcoded options.

	The main_above sub template is what is shown above the main content, and
	should contain anything that should be shown up there.

	The main_below sub template, conversely, is shown after the main content.
	It should probably contain the copyright statement and some other things.

	The linktree sub template should display the link tree, using the data
	in the $context['linktree'] variable.

	The menu sub template should display all the relevant buttons the user
	wants and or needs.

	For more information on the templating system, please see the site at:
	https://www.simplemachines.org/
*/

/**
 * Initialize the template... mainly little settings.
 */
function template_init()
{
	global $settings, $txt;

	/* $context, $options and $txt may be available for use, but may not be fully populated yet. */

	// The version this template/theme is for. This should probably be the version of SMF it was created for.
	$settings['theme_version'] = '3.0';

	// Use plain buttons - as opposed to text buttons?
	$settings['use_buttons'] = true;

	// Set the following variable to true if this theme requires the optional theme strings file to be loaded.
	$settings['require_theme_strings'] = false;

	// Set the following variable to true is this theme wants to display the avatar of the user that posted the last and the first post on the message index and recent pages.
	$settings['avatars_on_indexes'] = false;

	// Set the following variable to true is this theme wants to display the avatar of the user that posted the last post on the board index.
	$settings['avatars_on_boardIndex'] = false;

	// This defines the formatting for the page indexes used throughout the forum.
	$settings['page_index'] = array(
		'extra_before' => '<span class="pages">' . $txt['pages'] . '</span>',
		'previous_page' => '<span class="generic_icons previous_page"></span>',
		'current_page' => '<span class="current_page">%1$d</span> ',
		'page' => '<a class="navPages" href="{URL}">%2$s</a> ',
		'expand_pages' => '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>',
		'next_page' => '<span class="generic_icons next_page"></span>',
		'extra_after' => '',
	);

	// Allow css/js files to be disable for this specific theme.
	// Add the identifier as an array key. IE array('smf_script'); Some external files might not add identifiers, on those cases SMF uses its filename as reference.
	if (!isset($settings['disable_files']))
		$settings['disable_files'] = array();
}

function locale_helper($lang_locale) 
{
    return new \LightnCandy\SafeString(str_replace("_", "-", substr($lang_locale, 0, strcspn($lang_locale, "."))));
}

function login_helper($string, $guest_title, $forum_name, $scripturl, $login) 
{
    return new \LightnCandy\SafeString(sprintf($string,
	    $guest_title, 
	    $forum_name, 
	    $scripturl . '?action=login', 
	    'return reqOverlayDiv(this.href, ' . JavaScriptEscape($login) . ');', 
	    $scripturl . '?action=signup'
	));
}



function langName($lang) {
	return  str_replace('-utf8', '', $language['name']);
}


function render_page($content) {
	global $context, $settings, $scripturl, $txt, $modSettings, $maintenance;

	$data = Array(
		'content' => $content,
		'context' => $context,
		'txt' => $txt,
		'scripturl' => $scripturl,
		'settings' => $settings,
		'maintenance' => $maintenance,
		'modSettings' => $modSettings,
		'copyright' => theme_copyright(),
		'loadtime' => !empty($modSettings['timeLoadPageEnable']) ? sprintf($txt['page_created_full'], $context['load_time'], $context['load_queries']) : ''
	);
	
	$template = file_get_contents(__DIR__ .  "/layouts/default.hbs");
	if (!$template) {
		die('Template did not load!');
	}
	
	$phpStr = LightnCandy::compile($template, Array(
	    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RENDER_DEBUG,
	    'partials' => Array(
	    	'menu' => file_get_contents(__DIR__ .  "/partials/menu.hbs")
	    ),
	    'helpers' => Array(
	    	'locale' => 'locale_helper',
	        'login_helper' => 'login_helper',
	        'isSelected',
	        'langName'
	    )
	));
	
	$renderer = LightnCandy::prepare($phpStr);
	echo $renderer($data);
}
    


/**
 * Generate a strip of buttons.
 *
 * @param array $button_strip An array with info for displaying the strip
 * @param string $direction The direction
 * @param array $strip_options Options for the button strip
 */
function template_button_strip($button_strip, $direction = '', $strip_options = array())
{
	global $context, $txt;

	if (!is_array($strip_options))
		$strip_options = array();

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		// As of 2.1, the 'test' for each button happens while the array is being generated. The extra 'test' check here is deprecated but kept for backward compatibility (update your mods, folks!)
		if (!isset($value['test']) || !empty($context[$value['test']]))
		{
			if (!isset($value['id']))
				$value['id'] = $key;

			$button = '
				<a class="button button_strip_' . $key . (!empty($value['active']) ? ' active' : '') . (isset($value['class']) ? ' ' . $value['class'] : '') . '" ' . (!empty($value['url']) ? 'href="' . $value['url'] . '"' : '') . ' ' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a>';

			if (!empty($value['sub_buttons']))
			{
				$button .= '
					<div class="top_menu dropmenu ' . $key . '_dropdown">
						<div class="viewport">
							<div class="overview">';
				foreach ($value['sub_buttons'] as $element)
				{
					if (isset($element['test']) && empty($context[$element['test']]))
						continue;

					$button .= '
								<a href="' . $element['url'] . '"><strong>' . $txt[$element['text']] . '</strong>';
					if (isset($txt[$element['text'] . '_desc']))
						$button .= '<br /><span>' . $txt[$element['text'] . '_desc'] . '</span>';
					$button .= '</a>';
				}
				$button .= '
							</div>
						</div>
					</div>';
			}

			$buttons[] = $button;
		}
	}

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	echo '
		<div class="buttonlist', !empty($direction) ? ' float' . $direction : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"' : ''), '>
			',implode('', $buttons), '
		</div>';
}

/**
 * The upper part of the maintenance warning box
 */
function template_maint_warning_above()
{
	global $txt, $context, $scripturl;

	echo '
	<div class="errorbox" id="errors">
		<dl>
			<dt>
				<strong id="error_serious">', $txt['forum_in_maintenance'], '</strong>
			</dt>
			<dd class="error" id="error_list">
				', sprintf($txt['maintenance_page'], $scripturl . '?action=admin;area=serversettings;' . $context['session_var'] . '=' . $context['session_id']), '
			</dd>
		</dl>
	</div>';
}

/**
 * The lower part of the maintenance warning box.
 */
function template_maint_warning_below()
{

}

?>