<?php

/**
 * Handles activating account changes.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Hook\Observable;

class ActivateAccount extends AbstractProfileController
{
	public function display_action()
	{
		global $sourcedir, $context, $user_profile, $modSettings;

		isAllowedTo('moderate_forum');

		$memID = $this->params['u'];

		checkSession('get');
		validateToken('profile-aa' . $memID, 'get');

		if (isset($_REQUEST['save']) && isset($user_profile[$memID]['is_activated']) && $user_profile[$memID]['is_activated'] != 1)
		{
			// If we are approving the deletion of an account, we do something special ;)
			if ($user_profile[$memID]['is_activated'] == 4)
			{
				require_once($sourcedir . '/Subs-Members.php');
				deleteMembers($context['id_member']);
				redirectexit();
			}

			// Let the integrations know of the activation.
			(new Observable\Account\Activated($user_profile[$memID]['member_name'], $memID))->execute();

			// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
			updateMemberData($context['id_member'], ['is_activated' => $user_profile[$memID]['is_activated'] >= 10 ? 11 : 1, 'validation_code' => '']);

			// Log what we did?
			require_once($sourcedir . '/Logging.php');
			logAction('approve_member', ['member' => $memID], 'admin');

			// If we are doing approval, update the stats for the member just in case.
			if (in_array($user_profile[$memID]['is_activated'], [3, 4, 5, 13, 14, 15]))
				updateSettings(['unapprovedMembers' => ($modSettings['unapprovedMembers'] > 1 ? $modSettings['unapprovedMembers'] - 1 : 0)]);

			// Make sure we update the stats too.
			updateStats('member', false);
		}

		// Leave it be...
		redirectexit('action=profile;area=summary;u=' . $memID);
	}
}
