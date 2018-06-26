<?php

/**
 * A contact form.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * Display and process the contact form.
 * @return void
 */
function Contact()
{
	global $context, $txt, $sourcedir, $smcFunc;

	$context['page_title'] = $txt['contact_us'];
	$context['sub_template'] = 'contact_form';

	require_once($sourcedir . '/Subs-Editor.php');
	$verificationOptions = array(
		'id' => 'contact',
	);

	$context['contact'] = [
		'name' => '',
		'email' => '',
		'subject' => '',
		'message' => '',
		'errors' => [],
	];

	if (!empty($_POST['save']))
	{
		checkSession();
		validateToken('contact');

		if (!$context['user']['is_guest'])
		{
			// Override the inputs with things we know we'll have.
			$_POST['name'] = $context['user']['name'];
			$_POST['email'] = $context['user']['email'];
		}

		// Did they put something in the fields they needed to put in?
		foreach (['name', 'email', 'subject', 'message'] as $item)
		{
			$context['contact'][$item] = isset($_POST[$item]) ? $smcFunc['htmlspecialchars'](trim($_POST[$item]), ENT_QUOTES) : '';
			if (empty($context['contact'][$item]))
			{
				loadLanguage('Errors');
				$context['contact']['errors'][] = $txt['error_contact_no_' . $item];
			}
		}
		// Was that email address valid?
		if (!empty($context['contact']['email']) && !filter_var($context['contact']['email'], FILTER_VALIDATE_EMAIL))
		{
			$context['contact']['errors'][] = $txt['error_contact_invalid_email'];
		}

		// What about CAPTCHA?
		if ($context['user']['is_guest'])
		{
			$context['require_verification'] = create_control_verification($verificationOptions, true);
			if (is_array($context['require_verification']))
			{
				loadLanguage('Errors');
				foreach ($context['require_verification'] as $error)
				{
					$context['contact']['errors'][] = $txt['error_' . $error];
				}
			}
		}

		if (empty($context['contact']['errors']))
		{
			// No errors, that means we're good here. Save the result and go home.
			$message = $smcFunc['db_insert']('insert',
				'{db_prefix}contact_form',
				[
					'id_member' => 'int', 'contact_name' => 'string-255', 'contact_email' => 'string-255',
					'subject' => 'string-255', 'message' => 'string', 'time_received' => 'int', 'status' => 'int'
				],
				[
					$context['user']['id'], $context['contact']['name'], $context['contact']['email'],
					$context['contact']['subject'], $context['contact']['message'], time(), 0
				],
				['id_message'],
				1
			);

			// Now we have the message, we can link to it for the admins.

			// And send users on their way.
			$context['sub_template'] = 'contact_form_success';
			return;
		}
	}

	$context['visual_verification_id'] = '';
	if ($context['user']['is_guest'])
	{
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	createToken('contact');
}
