<?php

/**
 * Displays the drafts page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

class ShowDrafts extends AbstractProfileController
{
	/**
	 * Loads in a group of drafts for the user of a given type (0/posts, 1/pm's)
	 * loads a specific draft for forum use if selected.
	 * Used in the posting screens to allow draft selection
	 * Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id ID of the member to show drafts for
	 * @param boolean|integer If $type is 1, this can be set to only load drafts for posts in the specific topic
	 * @param int $draft_type The type of drafts to show - 0 for post drafts, 1 for PM drafts
	 * @return boolean False if the drafts couldn't be loaded, nothing otherwise
	 */
	public function display_action()
	{
		global $txt, $scripturl, $modSettings, $context, $smcFunc, $options;

		loadLanguage('Drafts');

		$memID = $this->params['u'];

		// Some initial context.
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['current_member'] = $memID;

		// If just deleting a draft, do it and then redirect back.
		if (!empty($_REQUEST['delete']))
		{
			checkSession('get');
			$id_delete = (int) $_REQUEST['delete'];

			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}user_drafts
				WHERE id_draft = {int:id_draft}
					AND id_member = {int:id_member}
					AND type = {int:draft_type}
				LIMIT 1',
				[
					'id_draft' => $id_delete,
					'id_member' => $memID,
					'draft_type' => 0,
				]
			);

			redirectexit('action=profile;u=' . $memID . ';area=drafts;start=' . $context['start']);
		}

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
			$_REQUEST['viewscount'] = 10;

		// Get the count of applicable drafts on the boards they can (still) see ...
		// @todo .. should we just let them see their drafts even if they have lost board access ?
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(id_draft)
			FROM {db_prefix}user_drafts AS ud
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = ud.id_board AND {query_see_board})
			WHERE id_member = {int:id_member}
				AND type={int:draft_type}' . (!empty($modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : ''),
			[
				'id_member' => $memID,
				'draft_type' => 0,
				'time' => (!empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0),
			]
		);
		list ($msgCount) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		$maxIndex = $maxPerPage;

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=drafts', $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the pages for better performance.
		$start = $context['start'];
		$reverse = $_REQUEST['start'] > $msgCount / 2;
		if ($reverse)
		{
			$maxIndex = $msgCount < $context['start'] + $maxPerPage + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : $maxPerPage;
			$start = $msgCount < $context['start'] + $maxPerPage + 1 || $msgCount < $context['start'] + $maxPerPage ? 0 : $msgCount - $context['start'] - $maxPerPage;
		}

		// Find this user's drafts for the boards they can access
		// @todo ... do we want to do this?  If they were able to create a draft, do we remove thier access to said draft if they loose
		//           access to the board or if the topic moves to a board they can not see?
		$request = $smcFunc['db']->query('', '
			SELECT
				b.id_board, b.name AS bname,
				ud.id_member, ud.id_draft, ud.body, ud.smileys_enabled, ud.subject, ud.poster_time, ud.id_topic, ud.locked, ud.is_sticky
			FROM {db_prefix}user_drafts AS ud
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = ud.id_board AND {query_see_board})
			WHERE ud.id_member = {int:current_member}
				AND type = {int:draft_type}' . (!empty($modSettings['drafts_keep_days']) ? '
				AND poster_time > {int:time}' : '') . '
			ORDER BY ud.id_draft ' . ($reverse ? 'ASC' : 'DESC') . '
			LIMIT {int:start}, {int:max}',
			[
				'current_member' => $memID,
				'draft_type' => 0,
				'time' => (!empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0),
				'start' => $start,
				'max' => $maxIndex,
			]
		);

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Censor....
			if (empty($row['body']))
				$row['body'] = '';

			$row['subject'] = StringLibrary::htmltrim($row['subject']);
			if (empty($row['subject']))
				$row['subject'] = $txt['no_subject'];

			censorText($row['body']);
			censorText($row['subject']);

			// BBC-ilize the message.
			$row['body'] = Parser::parse_bbc($row['body'], $row['smileys_enabled'], 'draft' . $row['id_draft']);

			// And the array...
			$context['drafts'][$counter += $reverse ? -1 : 1] = [
				'body' => $row['body'],
				'counter' => $counter,
				'board' => [
					'name' => $row['bname'],
					'id' => $row['id_board']
				],
				'topic' => [
					'id' => $row['id_topic'],
					'link' => empty($row['id']) ? $row['subject'] : '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
				],
				'subject' => $row['subject'],
				'time' => timeformat($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'id_draft' => $row['id_draft'],
				'locked' => (bool) $row['locked'],
				'sticky' => (bool) $row['is_sticky'],
			];
		}
		$smcFunc['db']->free_result($request);

		// If the drafts were retrieved in reverse order, get them right again.
		if ($reverse)
		{
			$context['drafts'] = array_reverse($context['drafts'], true);
		}

		$context['sub_template'] = 'profile_show_drafts';
	}
}
