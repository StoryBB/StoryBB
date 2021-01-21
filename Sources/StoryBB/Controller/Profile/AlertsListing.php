<?php

/**
 * Displays the alerts page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\StringLibrary;
use StoryBB\Model\Alert;

class AlertsListing extends AbstractProfileController
{
	public function display_action()
	{
		global $context, $txt, $sourcedir, $scripturl;

		$memID = $this->params['u'];

		// Prepare the pagination vars.
		$maxIndex = 10;
		$start = (int) isset($_REQUEST['start']) ? $_REQUEST['start'] : 0;
		$count = Alert::count_for_member($memID);

		// Get the alerts.
		$context['alerts'] = Alert::fetch_alerts($memID, true, false, ['start' => $start, 'maxIndex' => $maxIndex]);

		// Create the pagination.
		$context['pagination'] = constructPageIndex($scripturl . '?action=profile;area=alerts;u=' . $memID, $start, $count, $maxIndex, false);

		$context['sub_template'] = 'profile_alerts_list';

		// Set some JavaScript for checking all alerts at once.
		addInlineJavaScript('
		$(function(){
			$(\'#select_all\').on(\'change\', function() {
				var checkboxes = $(\'ul.quickbuttons\').find(\':checkbox\');
				if($(this).prop(\'checked\')) {
					checkboxes.prop(\'checked\', true);
				}
				else {
					checkboxes.prop(\'checked\', false);
				}
			});
		});', true);

		// Form handling (for bulk marking) is sent to post_action but GET requests are funnelled here.
		if (!empty($_GET['do']) && !empty($_GET['aid']))
		{
			$toMark = (int) $_GET['aid'];
			$action = StringLibrary::escape(StringLibrary::htmltrim($_GET['do']));

			checkSession('get');

			switch ($action)
			{
				case 'remove':
					Alert::delete($toMark, $memID);
					break;

				case 'read':
					Alert::change_read($memID, $toMark, true);
					break;

				case 'unread':
					Alert::change_read($memID, $toMark, false);
					break;
			}

			// Set a nice message and redirect.
			session_flash('success', $txt['profile_updated_own']);
			redirectexit('action=profile;area=alerts;u=' . $memID);
		}
	}

	public function post_action()
	{
		global $txt, $sourcedir;

		require_once($sourcedir . '/Profile-Modify.php');

		$memID = $this->params['u'];

		$toMark = false;
		$action = '';

		// Saving multiple changes?
		if (!empty($_POST['mark']))
		{
			// Get the values.
			$toMark = array_map('intval', $_POST['mark']);

			// Which action?
			$action = !empty($_POST['mark_as']) ? StringLibrary::escape(StringLibrary::htmltrim($_POST['mark_as'])) : '';
		}

		// Save the changes.
		if (!empty($toMark) && !empty($action))
		{
			switch ($action)
			{
				case 'remove':
					Alert::delete($toMark, $memID);
					break;

				case 'read':
					Alert::change_read($memID, $toMark, true);
					break;

				case 'unread':
					Alert::change_read($memID, $toMark, false);
					break;
			}

			// Set a nice message and redirect.
			session_flash('success', $txt['profile_updated_own']);
			redirectexit('action=profile;area=alerts;u=' . $memID);
		}
	}
}
