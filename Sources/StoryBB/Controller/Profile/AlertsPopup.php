<?php

/**
 * Displays the alerts popup page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\Alert;
use StoryBB\Template;

class AlertsPopup extends AbstractProfileController
{
	public function display_action()
	{
		global $context, $sourcedir, $db_show_debug, $cur_profile, $smcFunc;

		$memID = $this->params['u'];

		// Load the Alerts language file.
		loadLanguage('Alerts');

		// We do not want to output debug information here.
		$db_show_debug = false;

		// We only want to output our little layer here.
		Template::remove_all_layers();
		Template::set_layout('raw');

		if (isset($_GET['markread']))
		{
			checkSession('get');

			$alert = 0;
			if (isset($_GET['alert']))
			{
				$alert = (int) $_GET['alert'];
			}

			// Assuming we're here, mark everything as read and head back.
			// We only spit back the little layer because this should be called AJAXively.
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}user_alerts
				SET is_read = {int:now}
				WHERE id_member = {int:current_member}' . ($alert ? '
					AND id_alert = {int:alert}' : '') . '
					AND is_read = 0',
				[
					'now' => time(),
					'current_member' => $memID,
					'alert' => $alert,
				]
			);
			if ($alert)
			{
				// If we managed a specific ID, we need to process that a little differently.
				if ($smcFunc['db']->affected_rows())
				{
					updateMemberData($memID, ['alerts' => '-']);
				}
			}
			else
			{
				// We marked everything read.
				updateMemberData($memID, ['alerts' => 0]);
			}
		}

		$context['unread_alerts'] = [];
		if (empty($_REQUEST['counter']) || (int) $_REQUEST['counter'] < $cur_profile['alerts'])
		{
			$context['unread_alerts'] = Alert::fetch_alerts($memID, false, $cur_profile['alerts'] - (!empty($_REQUEST['counter']) ? (int) $_REQUEST['counter'] : 0));
		}

		$context['sub_template'] = 'alerts_popup';
	}
}
