<?php

/**
 * Abstract PM controller (hybrid style)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\PM;

use StoryBB\StringLibrary;
use StoryBB\Helper\Parser;
use StoryBB\Template;

class Search extends AbstractPMController
{
	public function display_action()
	{
		global $context, $txt, $scripturl;

		if (isset($_GET['results']))
		{
			unset($_GET['results'], $_REQUEST['results']);
			return $this->post_action();
		}

		if (isset($_REQUEST['params']))
		{
			$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], [' ' => '+'])));
			$context['search_params'] = [];
			foreach ($temp_params as $i => $data)
			{
				@list ($k, $v) = explode('|\'|', $data);
				$context['search_params'][$k] = $v;
			}
		}
		if (isset($_REQUEST['search']))
			$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);

		if (isset($context['search_params']['search']))
			$context['search_params']['search'] = StringLibrary::escape($context['search_params']['search']);
		if (isset($context['search_params']['userspec']))
			$context['search_params']['userspec'] = StringLibrary::escape($context['search_params']['userspec']);

		if (!empty($context['search_params']['searchtype']))
			$context['search_params']['searchtype'] = 2;

		if (!empty($context['search_params']['minage']))
			$context['search_params']['minage'] = (int) $context['search_params']['minage'];

		if (!empty($context['search_params']['maxage']))
			$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

		$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);
		$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);

		// Create the array of labels to be searched.
		$context['search_labels'] = [];
		$searchedLabels = isset($context['search_params']['labels']) && $context['search_params']['labels'] != '' ? explode(',', $context['search_params']['labels']) : [];
		foreach ($context['labels'] as $label)
		{
			$context['search_labels'][] = [
				'id' => $label['id'],
				'name' => $label['name'],
				'checked' => !empty($searchedLabels) ? in_array($label['id'], $searchedLabels) : true,
			];
		}

		// Are all the labels checked?
		$context['check_all'] = empty($searchedLabels) || count($context['search_labels']) == count($searchedLabels);

		// Load the error text strings if there were errors in the search.
		if (!empty($context['search_errors']))
		{
			loadLanguage('Errors');
			$context['search_errors']['messages'] = [];
			foreach ($context['search_errors'] as $search_error => $dummy)
			{
				if ($search_error == 'messages')
					continue;

				$context['search_errors']['messages'][] = $txt['error_' . $search_error];
			}
		}

		$context['page_title'] = $txt['pm_search_title'];
		$context['sub_template'] = 'personal_message_search';
		$context['linktree'][] = [
			'url' => $scripturl . '?action=pm;sa=search',
			'name' => $txt['pm_search_bar_title'],
		];
	}

	public function post_action()
	{
		global $scripturl, $modSettings, $user_info, $context, $txt;
		global $memberContext, $smcFunc;

		check_load_avg('search');

		/**
		 * @todo For the moment force the folder to the inbox.
		 * @todo Maybe set the inbox based on a cookie or theme setting?
		 */
		$context['folder'] = 'inbox';

		// Some useful general permissions.
		$context['can_send_pm'] = allowedTo('pm_send');

		// Some hardcoded veriables that can be tweaked if required.
		$maxMembersToSearch = 500;

		// Extract all the search parameters.
		$search_params = [];
		if (isset($_REQUEST['params']))
		{
			$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], [' ' => '+'])));
			foreach ($temp_params as $i => $data)
			{
				@list ($k, $v) = explode('|\'|', $data);
				$search_params[$k] = $v;
			}
		}

		$context['start'] = isset($_GET['start']) ? (int) $_GET['start'] : 0;

		// Store whether simple search was used (needed if the user wants to do another query).
		if (!isset($search_params['advanced']))
			$search_params['advanced'] = empty($_REQUEST['advanced']) ? 0 : 1;

		// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
		if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
			$search_params['searchtype'] = 2;

		// Minimum age of messages. Default to zero (don't set param in that case).
		if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
			$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];

		// Maximum age of messages. Default to infinite (9999 days: param not set).
		if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] != 9999))
			$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

		$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);
		$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);

		// Default the user name to a wildcard matching every user (*).
		if (!empty($search_params['user_spec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
			$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

		// This will be full of all kinds of parameters!
		$searchq_parameters = [];

		// If there's no specific user, then don't mention it in the main query.
		if (empty($search_params['userspec']))
			$userQuery = '';
		else
		{
			$userString = strtr(StringLibrary::escape($search_params['userspec'], ENT_QUOTES), ['&quot;' => '"']);
			$userString = strtr($userString, ['%' => '\%', '_' => '\_', '*' => '%', '?' => '_']);

			preg_match_all('~"([^"]+)"~', $userString, $matches);
			$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

			for ($k = 0, $n = count($possible_users); $k < $n; $k++)
			{
				$possible_users[$k] = trim($possible_users[$k]);

				if (strlen($possible_users[$k]) == 0)
					unset($possible_users[$k]);
			}

			if (!empty($possible_users))
			{
				// We need to bring this into the query and do it nice and cleanly.
				$where_params = [];
				$where_clause = [];
				foreach ($possible_users as $k => $v)
				{
					$where_params['name_' . $k] = $v;
					$where_clause[] = '{raw:real_name} LIKE {string:name_' . $k . '}';
					if (!isset($where_params['real_name']))
						$where_params['real_name'] = $smcFunc['db']->is_case_sensitive() ? 'LOWER(real_name)' : 'real_name';
				}

				// Who matches those criteria?
				// @todo This doesn't support sent item searching.
				$request = $smcFunc['db']->query('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE ' . implode(' OR ', $where_clause),
					$where_params
				);

				// Simply do nothing if there're too many members matching the criteria.
				if ($smcFunc['db']->num_rows($request) > $maxMembersToSearch)
					$userQuery = '';
				elseif ($smcFunc['db']->num_rows($request) == 0)
				{
					$userQuery = 'AND pm.id_member_from = 0 AND ({raw:pm_from_name} LIKE {raw:guest_user_name_implode})';
					$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR ' . ($smcFunc['db']->is_case_sensitive() ? 'LOWER(pm.from_name)' : 'pm.from_name') . ' LIKE \'', $possible_users) . '\'';
					$searchq_parameters['pm_from_name'] = $smcFunc['db']->is_case_sensitive() ? 'LOWER(pm.from_name)' : 'pm.from_name';
				}
				else
				{
					$memberlist = [];
					while ($row = $smcFunc['db']->fetch_assoc($request))
						$memberlist[] = $row['id_member'];
					$userQuery = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND ({raw:pm_from_name} LIKE {raw:guest_user_name_implode})))';
					$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR ' . ($smcFunc['db']->is_case_sensitive() ? 'LOWER(pm.from_name)' : 'pm.from_name') . ' LIKE \'', $possible_users) . '\'';
					$searchq_parameters['member_list'] = $memberlist;
					$searchq_parameters['pm_from_name'] = $smcFunc['db']->is_case_sensitive() ? 'LOWER(pm.from_name)' : 'pm.from_name';
				}
				$smcFunc['db']->free_result($request);
			}
			else
				$userQuery = '';
		}

		// Setup the sorting variables...
		// @todo Add more in here!
		$sort_columns = [
			'pm.id_pm',
		];
		if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
			list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
		$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'pm.id_pm';
		$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

		// Sort out any labels we may be searching by.
		$labelQuery = '';
		$labelJoin = '';
		if ($context['folder'] == 'inbox' && !empty($search_params['advanced']) && $context['currently_using_labels'])
		{
			// Came here from pagination?  Put them back into $_REQUEST for sanitization.
			if (isset($search_params['labels']))
				$_REQUEST['searchlabel'] = explode(',', $search_params['labels']);

			// Assuming we have some labels - make them all integers.
			if (!empty($_REQUEST['searchlabel']) && is_array($_REQUEST['searchlabel']))
			{
				foreach ($_REQUEST['searchlabel'] as $key => $id)
					$_REQUEST['searchlabel'][$key] = (int) $id;
			}
			else
				$_REQUEST['searchlabel'] = [];

			// Now that everything is cleaned up a bit, make the labels a param.
			$search_params['labels'] = implode(',', $_REQUEST['searchlabel']);

			// No labels selected? That must be an error!
			if (empty($_REQUEST['searchlabel']))
				$context['search_errors']['no_labels_selected'] = true;
			// Otherwise prepare the query!
			elseif (count($_REQUEST['searchlabel']) != count($context['labels']))
			{
				// Special case here... "inbox" isn't a real label anymore...
				if (in_array(-1, $_REQUEST['searchlabel']))
				{
					$labelQuery = '	AND pmr.in_inbox = {int:in_inbox}';
					$searchq_parameters['in_inbox'] = 1;

					// Now we get rid of that...
					$temp = array_diff($_REQUEST['searchlabel'], [-1]);
					$_REQUEST['searchlabel'] = $temp;
				}

				// Still have something?
				if (!empty($_REQUEST['searchlabel']))
				{
					if ($labelQuery == '')
					{
						// Not searching the inbox - PM must be labeled
						$labelQuery = ' AND pml.id_label IN ({array_int:labels})';
						$labelJoin = ' INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)';
					}
					else
					{
						// Searching the inbox - PM doesn't have to be labeled
						$labelQuery = ' AND (' . substr($labelQuery, 5) . ' OR pml.id_label IN ({array_int:labels}))';
						$labelJoin = ' LEFT JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)';
					}

					$searchq_parameters['labels'] = $_REQUEST['searchlabel'];
				}
			}
		}

		// What are we actually searching for?
		$search_params['search'] = !empty($search_params['search']) ? $search_params['search'] : (isset($_REQUEST['search']) ? $_REQUEST['search'] : '');
		// If we ain't got nothing - we should error!
		if (!isset($search_params['search']) || $search_params['search'] == '')
			$context['search_errors']['invalid_search_string'] = true;

		// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
		preg_match_all('~(?:^|\s)([-]?)"([^"]+)"(?:$|\s)~u', $search_params['search'], $matches, PREG_PATTERN_ORDER);
		$searchArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$tempSearch = explode(' ', preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $search_params['search']));

		// A minus sign in front of a word excludes the word.... so...
		$excludedWords = [];

		// .. first, we check for things like -"some words", but not "-some words".
		foreach ($matches[1] as $index => $word)
			if ($word == '-')
			{
				$word = StringLibrary::toLower(trim($searchArray[$index]));
				if (strlen($word) > 0)
					$excludedWords[] = $word;
				unset($searchArray[$index]);
			}

		// Now we look for -test, etc.... normaller.
		foreach ($tempSearch as $index => $word)
		{
			if (strpos(trim($word), '-') === 0)
			{
				$word = substr(StringLibrary::toLower($word), 1);
				if (strlen($word) > 0)
					$excludedWords[] = $word;
				unset($tempSearch[$index]);
			}
		}

		$searchArray = array_merge($searchArray, $tempSearch);

		// Trim everything and make sure there are no words that are the same.
		foreach ($searchArray as $index => $value)
		{
			$searchArray[$index] = StringLibrary::toLower(trim($value));
			if ($searchArray[$index] == '')
				unset($searchArray[$index]);
			else
			{
				// Sort out entities first.
				$searchArray[$index] = StringLibrary::escape($searchArray[$index]);
			}
		}
		$searchArray = array_unique($searchArray);

		// Create an array of replacements for highlighting.
		$context['mark'] = [];
		foreach ($searchArray as $word)
			$context['mark'][$word] = '<strong class="highlight">' . $word . '</strong>';

		// This contains *everything*
		$searchWords = array_merge($searchArray, $excludedWords);

		// Make sure at least one word is being searched for.
		if (empty($searchArray))
			$context['search_errors']['invalid_search_string'] = true;

		// Sort out the search query so the user can edit it - if they want.
		$context['search_params'] = $search_params;
		if (isset($context['search_params']['search']))
			$context['search_params']['search'] = StringLibrary::escape($context['search_params']['search']);
		if (isset($context['search_params']['userspec']))
			$context['search_params']['userspec'] = StringLibrary::escape($context['search_params']['userspec']);

		// Now we have all the parameters, combine them together for pagination and the like...
		$context['params'] = [];
		foreach ($search_params as $k => $v)
			$context['params'][] = $k . '|\'|' . $v;
		$context['params'] = base64_encode(implode('|"|', $context['params']));

		// Compile the subject query part.
		$andQueryParts = [];

		foreach ($searchWords as $index => $word)
		{
			if ($word == '')
				continue;

			if ($search_params['subject_only'])
				$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
			else
				$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';
			$searchq_parameters['search_' . $index] = '%' . strtr($word, ['_' => '\\_', '%' => '\\%']) . '%';
		}

		$searchQuery = ' 1=1';
		if (!empty($andQueryParts))
			$searchQuery = implode(!empty($search_params['searchtype']) && $search_params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);

		// Age limits?
		$timeQuery = '';
		if (!empty($search_params['minage']))
			$timeQuery .= ' AND pm.msgtime < ' . (time() - $search_params['minage'] * 86400);
		if (!empty($search_params['maxage']))
			$timeQuery .= ' AND pm.msgtime > ' . (time() - $search_params['maxage'] * 86400);

		// If we have errors - return back to the first screen...
		if (!empty($context['search_errors']))
		{
			$_REQUEST['params'] = $context['params'];
			return $this->display_action();
		}

		// Get the amount of results.
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
				' . $labelJoin . '
			WHERE ' . ($context['folder'] == 'inbox' ? '
				pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}' : '
				pm.id_member_from = {int:current_member}
				AND pm.deleted_by_sender = {int:not_deleted}') . '
				' . $userQuery . $labelQuery . $timeQuery . '
				AND (' . $searchQuery . ')',
			array_merge($searchq_parameters, [
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
			])
		);
		list ($numResults) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		// Get all the matching messages... using standard search only (No caching and the like!)
		// @todo This doesn't support sent item searching yet.
		$request = $smcFunc['db']->query('', '
			SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
				' . $labelJoin . '
			WHERE ' . ($context['folder'] == 'inbox' ? '
				pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}' : '
				pm.id_member_from = {int:current_member}
				AND pm.deleted_by_sender = {int:not_deleted}') . '
				' . $userQuery . $labelQuery . $timeQuery . '
				AND (' . $searchQuery . ')
			ORDER BY {raw:sort} {raw:sort_dir}
			LIMIT {int:start}, {int:max}',
			array_merge($searchq_parameters, [
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'sort' => $search_params['sort'],
				'sort_dir' => $search_params['sort_dir'],
				'start' => $context['start'],
				'max' => $modSettings['search_results_per_page'],
			])
		);
		$foundMessages = [];
		$posters = [];
		$head_pms = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$foundMessages[] = $row['id_pm'];
			$posters[] = $row['id_member_from'];
			$head_pms[$row['id_pm']] = $row['id_pm_head'];
		}
		$smcFunc['db']->free_result($request);

		// Find the real head pms!
		if (!empty($head_pms))
		{
			$request = $smcFunc['db']->query('', '
				SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				WHERE pm.id_pm_head IN ({array_int:head_pms})
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:not_deleted}
				GROUP BY pm.id_pm_head
				LIMIT {int:limit}',
				[
					'head_pms' => array_unique($head_pms),
					'current_member' => $user_info['id'],
					'not_deleted' => 0,
					'limit' => count($head_pms),
				]
			);
			$real_pm_ids = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$real_pm_ids[$row['id_pm_head']] = $row['id_pm'];
			$smcFunc['db']->free_result($request);
		}

		// Load the users...
		loadMemberData($posters);

		// Sort out the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=search;results;params=' . $context['params'], $_GET['start'], $numResults, $modSettings['search_results_per_page'], false);

		$context['message_labels'] = [];
		$context['message_replied'] = [];
		$context['personal_messages'] = [];

		if (!empty($foundMessages))
		{
			// Now get recipients (but don't include bcc-recipients for your inbox, you're not supposed to know :P!)
			$request = $smcFunc['db']->query('', '
				SELECT
					pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name,
					pmr.bcc, pmr.in_inbox, pmr.is_read
				FROM {db_prefix}pm_recipients AS pmr
					LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
				WHERE pmr.id_pm IN ({array_int:message_list})',
				[
					'message_list' => $foundMessages,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				if ($context['folder'] == 'sent' || empty($row['bcc']))
					$recipients[$row['id_pm']][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

				if ($row['id_member_to'] == $user_info['id'] && $context['folder'] != 'sent')
				{
					$context['message_replied'][$row['id_pm']] = $row['is_read'] & 2;

					$row['labels'] = '';

					// Get the labels for this PM
					$request2 = $smcFunc['db']->query('', '
						SELECT id_label
						FROM {db_prefix}pm_labeled_messages
						WHERE id_pm = {int:current_pm}',
						[
							'current_pm' => $row['id_pm'],
						]
					);

					while ($row2 = $smcFunc['db']->fetch_assoc($request2))
					{
						$l_id = $row2['id_label'];
						if (isset($context['labels'][$l_id]))
							$context['message_labels'][$row['id_pm']][$l_id] = ['id' => $l_id, 'name' => $context['labels'][$l_id]['name']];

						// Here we find the first label on a message - for linking to posts in results
						if (!isset($context['first_label'][$row['id_pm']]) && $row['in_inbox'] != 1)
							$context['first_label'][$row['id_pm']] = $l_id;
					}

					$smcFunc['db']->free_result($request2);

					// Is this in the inbox as well?
					if ($row['in_inbox'] == 1)
					{
						$context['message_labels'][$row['id_pm']][-1] = ['id' => -1, 'name' => $context['labels'][-1]['name']];
					}

					$row['labels'] = $row['labels'] == '' ? [] : explode(',', $row['labels']);
				}
			}

			// Prepare the query for the callback!
			$request = $smcFunc['db']->query('', '
				SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
				FROM {db_prefix}personal_messages AS pm
				WHERE pm.id_pm IN ({array_int:message_list})
				ORDER BY {raw:sort} {raw:sort_dir}
				LIMIT {int:limit}',
				[
					'message_list' => $foundMessages,
					'limit' => count($foundMessages),
					'sort' => $search_params['sort'],
					'sort_dir' => $search_params['sort_dir'],
				]
			);
			$counter = 0;
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				// If there's no message subject, use the default.
				$row['subject'] = $row['subject'] == '' ? $txt['no_subject'] : $row['subject'];

				// Load this posters context info, if it ain't there then fill in the essentials...
				if (!loadMemberContext($row['id_member_from'], true))
				{
					$memberContext[$row['id_member_from']]['name'] = $row['from_name'];
					$memberContext[$row['id_member_from']]['id'] = 0;
					$memberContext[$row['id_member_from']]['group'] = $txt['guest_title'];
					$memberContext[$row['id_member_from']]['link'] = $row['from_name'];
					$memberContext[$row['id_member_from']]['email'] = '';
					$memberContext[$row['id_member_from']]['is_guest'] = true;
				}

				// Censor anything we don't want to see...
				censorText($row['body']);
				censorText($row['subject']);

				// Parse out any BBC...
				$row['body'] = Parser::parse_bbc($row['body'], true, 'pm' . $row['id_pm']);

				$href = $scripturl . '?action=pm;f=' . $context['folder'] . (isset($context['first_label'][$row['id_pm']]) ? ';l=' . $context['first_label'][$row['id_pm']] : '') . ';pmid=' . (isset($real_pm_ids[$head_pms[$row['id_pm']]]) ? $real_pm_ids[$head_pms[$row['id_pm']]] : $row['id_pm']) . '#msg' . $row['id_pm'];
				$context['personal_messages'][] = [
					'id' => $row['id_pm'],
					'member' => &$memberContext[$row['id_member_from']],
					'subject' => $row['subject'],
					'body' => $row['body'],
					'time' => timeformat($row['msgtime']),
					'recipients' => &$recipients[$row['id_pm']],
					'labels' => &$context['message_labels'][$row['id_pm']],
					'fully_labeled' => count($context['message_labels'][$row['id_pm']]) == count($context['labels']),
					'is_replied_to' => &$context['message_replied'][$row['id_pm']],
					'href' => $href,
					'link' => '<a href="' . $href . '">' . $row['subject'] . '</a>',
					'counter' => ++$counter,
				];
			}
			$smcFunc['db']->free_result($request);
		}

		call_integration_hook('integrate_search_pm_context');


		//We need a helper
		Template::add_helper([
			'create_button' => 'create_button'
		]);
		
		// Finish off the context.
		$context['page_title'] = $txt['pm_search_title'];
		$context['sub_template'] = 'personal_message_search_results';
		$context['linktree'][] = [
			'url' => $scripturl . '?action=pm;sa=search',
			'name' => $txt['pm_search_bar_title'],
		];
	}
}
