<?php

/**
 * Merge accounts page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\Autocomplete;

class MergeAccount extends AbstractProfileController
{
	public function display_action()
	{
		global $context, $txt, $user_profile;

		$memID = $this->params['u'];

		// Some basic sanity checks.
		if ($context['user']['is_owner'])
			fatal_lang_error('cannot_merge_self', false);
		if ($user_profile[$memID]['id_group'] == 1 || in_array('1', explode(',', $user_profile[$memID]['additional_groups'])))
			fatal_lang_error('cannot_merge_admin', false);

		$context['page_title'] = $txt['merge_char_account'];
		$context['sub_template'] = 'profile_merge_account';
		Autocomplete::init('member', '#merge_acct');
	}

	public function post_action()
	{
		global $context, $sourcedir, $txt, $smcFunc, $user_profile;

		$memID = $this->params['u'];

		if (isset($_POST['merge_acct_id']))
		{
			require_once($sourcedir . '/Subs-Members.php');
			$result = mergeMembers($context['id_member'], $_POST['merge_acct_id']);

			if ($result !== true)
				fatal_lang_error('cannot_merge_' . $result, false);

			session_flash('success', sprintf($txt['merge_success'], $context['member']['name']));

			redirectexit('action=profile;u=' . $_POST['merge_acct_id']);
		}
		elseif (isset($_POST['merge_acct']))
		{
			// We picked an account to merge, let's see if we can find and if we can,
			// get its details so that we can check for sure it's what the user wants.
			$request = $smcFunc['db']->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}',
				[
					'id_member' => (int) $_POST['merge_acct'],
				]
			);
			if ($smcFunc['db']->num_rows($request) == 0)
				fatal_lang_error('cannot_merge_not_found', false);

			list ($dest) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			loadMemberData($dest);

			$context['merge_destination_id'] = $dest;
			$context['merge_destination'] = $user_profile[$dest];
			$context['sub_template'] = 'profile_merge_account_confirm';
		}
	}
}
