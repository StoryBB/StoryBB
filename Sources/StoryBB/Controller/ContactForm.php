<?php

/**
 * A contact form.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller;

use StoryBB\App;
use StoryBB\Helper\Verification;
use StoryBB\Phrase;
use StoryBB\Routing\Behaviours\Routable;
use StoryBB\StringLibrary;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ContactForm implements Routable
{
	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add('contact', new Route('/contact-us', ['_function' => [static::class, 'contact_form']]));
	}

	/**
	 * Display and process the contact form.
	 * @return void
	 */
	public static function contact_form()
	{
		global $context, $txt, $sourcedir, $smcFunc;

		$context['page_title'] = $txt['contact_us'];
		$context['sub_template'] = 'contact_form';

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
			loadLanguage('Errors');

			if (!$context['user']['is_guest'])
			{
				// Override the inputs with things we know we'll have.
				$_POST['name'] = un_htmlspecialchars($context['user']['name']);
				$_POST['email'] = $context['user']['email'];
			}

			// Did they put something in the fields they needed to put in?
			foreach (['name', 'email', 'subject', 'message'] as $item)
			{
				$context['contact'][$item] = isset($_POST[$item]) ? StringLibrary::escape(trim($_POST[$item]), ENT_QUOTES) : '';
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
				$context['require_verification'] = Verification::get('contact')->verify();
				if (is_array($context['require_verification']))
				{
					loadLanguage('Errors');
					foreach ($context['require_verification'] as $error)
					{
						$context['contact']['errors'][] = isset($txt['error_' . $error]) ? $txt['error_' . $error] : $error;
					}
				}

				require_once($sourcedir . '/Subs-Members.php');
				if (isReservedName($_POST['name'], 0, true, false))
				{
					$context['contact']['errors'][] = $txt['error_contact_no_name'];
				}
			}

			if (empty($context['contact']['errors']))
			{
				// No errors, that means we're good here. Save the result and go home.
				$message = $smcFunc['db']->insert('insert',
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
				require_once($sourcedir . '/Subs-Members.php');
				$admins = membersAllowedTo('admin_forum');

				$alert_rows = [];
				foreach ($admins as $id_member)
				{
					$alert_rows[] = [
						'alert_time' => time(),
						'id_member' => $id_member,
						'id_member_started' => $context['user']['id'],
						'member_name' => $context['contact']['name'],
						'content_type' => 'contactform',
						'content_id' => $message,
						'content_action' => 'received',
						'is_read' => 0,
						'extra' => json_encode(['contact_link' => '?action=admin;area=contactform;sa=viewcontact;msg=' . $message]),
					];
				}

				if (!empty($alert_rows))
				{
					$smcFunc['db']->insert('',
						'{db_prefix}user_alerts',
						['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
							'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'],
						$alert_rows,
						[]
					);
					updateMemberData($admins, ['alerts' => '+']);
				}

				// And send users on their way.
				$context['sub_template'] = 'contact_form_success';
				return;
			}
		}

		$context['visual_verification'] = '';
		if ($context['user']['is_guest'])
		{
			$context['visual_verification'] = Verification::get('contact')->id();
		}

		createToken('contact');
	}
}
