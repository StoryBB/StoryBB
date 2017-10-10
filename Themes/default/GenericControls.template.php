<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * This template displays a verification form
 *
 * @param int|string $verify_id The verification control ID
 */
function template_control_verification($verify_id)
{
	global $context, $txt;

	$verify_context = &$context['controls']['verification'][$verify_id];

	// Loop through each item to show them.
	for ($i = 0; $i < $verify_context['total_items']; $i++)
	{
		echo '
			<div id="verification_control_', $i, '" class="verification_control">';

		// Display empty field, but only if we have one, and it's the first time.
		if ($verify_context['empty_field'] && empty($i))
			echo '
				<div class="smalltext vv_special">
					', $txt['visual_verification_hidden'], ':
					<input type="text" name="', $_SESSION[$verify_id . '_vv']['empty_field'], '" autocomplete="off" size="30" value="">
				</div>';

		// Do the actual stuff
		if ($i == 0 && ($verify_context['show_visual'] || $verify_context['can_recaptcha']))
		{
			if ($verify_context['show_visual'])
			{
				if ($context['use_graphic_library'])
					echo '
				<img src="', $verify_context['image_href'], '" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '">';
				else
					echo '
				<img src="', $verify_context['image_href'], ';letter=1" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_1">
				<img src="', $verify_context['image_href'], ';letter=2" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_2">
				<img src="', $verify_context['image_href'], ';letter=3" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_3">
				<img src="', $verify_context['image_href'], ';letter=4" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_4">
				<img src="', $verify_context['image_href'], ';letter=5" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_5">
				<img src="', $verify_context['image_href'], ';letter=6" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_6">';

				echo '
				<div class="smalltext" style="margin: 4px 0 8px 0;">
					<a href="', $verify_context['image_href'], ';sound" id="visual_verification_', $verify_id, '_sound" rel="nofollow">', $txt['visual_verification_sound'], '</a> / <a href="#visual_verification_', $verify_id, '_refresh" id="visual_verification_', $verify_id, '_refresh">', $txt['visual_verification_request_new'], '</a>', '<br>', '<br>
					', $txt['visual_verification_description'], ':', '<br>', '
					<input type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="input_text" required>
				</div>';
			}

			if ($verify_context['can_recaptcha'])
			{
				echo '
				<div class="g-recaptcha centertext" data-sitekey="' . $verify_context['recaptcha_site_key'] . '" data-theme="' . $verify_context['recaptcha_theme'] . '"></div><br>
				<script type="text/javascript" src="https://www.google.com/recaptcha/api.js"></script>';
			}
		}
		else
		{
			// Where in the question array is this question?
			$qIndex = $verify_context['show_visual'] ? $i - 1 : $i;

			echo '
				<div class="smalltext">
					', $verify_context['questions'][$qIndex]['q'], ':<br>
					<input type="text" name="', $verify_id, '_vv[q][', $verify_context['questions'][$qIndex]['id'], ']" size="30" value="', $verify_context['questions'][$qIndex]['a'], '" ', $verify_context['questions'][$qIndex]['is_error'] ? 'style="border: 1px red solid;"' : '', ' tabindex="', $context['tabindex']++, '" class="input_text" required>
				</div>';
		}

		echo '
			</div>';
	}
}

?>