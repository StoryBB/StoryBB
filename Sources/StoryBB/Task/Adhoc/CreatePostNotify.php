<?php
/**
 * This file contains background notification code for any create post action.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;

use StoryBB\Helper\Mentions;
use StoryBB\Helper\Mail;

/**
 * This file contains background notification code for any create post action.
 */
class CreatePostNotify extends \StoryBB\Task\Adhoc
{
	/**
	 * This handles notifications when a new post is created - new topic, reply, quotes and mentions.
	 * @return bool Always returns true
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $scripturl, $language, $modSettings;

		require_once($sourcedir . '/Subs-Post.php');
		require_once($sourcedir . '/Subs-Notify.php');

		$msgOptions = $this->_details['msgOptions'];
		$topicOptions = $this->_details['topicOptions'];
		$posterOptions = $this->_details['posterOptions'];
		$type = $this->_details['type'];

		$members = [];
		$quotedMembers = [];
		$done_members = [];
		$alert_rows = [];

		if ($type == 'reply' || $type == 'topic')
		{
			$quotedMembers = self::getQuotedMembers($msgOptions, $posterOptions);
			$members = array_keys($quotedMembers);
		}

		// Insert the post mentions
		if (!empty($msgOptions['mentioned_members']))
		{
			Mentions::insertMentions('msg', $msgOptions['id'], $msgOptions['mentioned_members'], $posterOptions['id'], !empty($posterOptions['char_id']) ? $posterOptions['char_id'] : 0);
			foreach ($msgOptions['mentioned_members'] as $member)
			{
				$members[] = $member['id_member'];
			}
		}

		// Find the people interested in receiving notifications for this topic
		$request = $smcFunc['db']->query('', '
			SELECT mem.id_member, ln.id_topic, ln.id_board, ln.sent, mem.email_address, b.member_groups,
				mem.id_group, mem.additional_groups, t.id_member_started, mem.pm_ignore_list,
				t.id_member_updated
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}members AS mem ON (ln.id_member = mem.id_member)
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board OR b.id_board = t.id_board)
			WHERE ln.id_topic = {int:topic}
				OR ln.id_board = {int:board}',
			[
				'topic' => $topicOptions['id'],
				'board' => $topicOptions['board'],
			]
		);

		$watched = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$groups = array_merge([$row['id_group']], (empty($row['additional_groups']) ? [] : explode(',', $row['additional_groups'])));
			if (!in_array(1, $groups) && count(array_intersect($groups, explode(',', $row['member_groups']))) == 0)
				continue;

			$members[] = $row['id_member'];
			$watched[$row['id_member']] = $row;
		}

		$smcFunc['db']->free_result($request);

		// If this is an edit notification make sure we don't include the author in it if the author is editing their own post.
		if ($type == 'edit' && !empty($msgOptions['edit_by_self']))
		{
			$members = array_diff($members, [$posterOptions['id']]);
		}

		if (empty($members))
			return true;

		$members = array_unique($members);
		$prefs = getNotifyPrefs($members, '', true);

		// Do we have anyone to notify via mention? Handle them first and cross them off the list
		if (!empty($msgOptions['mentioned_members']))
		{
			$mentioned_members = Mentions::getMentionsByContent('msg', $msgOptions['id'], array_keys($msgOptions['mentioned_members']));
			self::handleMentionedNotifications($msgOptions, $mentioned_members, $prefs, $done_members, $alert_rows);
		}

		// Notify members which might've been quoted
		self::handleQuoteNotifications($msgOptions, $posterOptions, $quotedMembers, $prefs, $done_members, $alert_rows);

		// Handle rest of the notifications for watched topics and boards
		foreach ($watched as $member => $data)
		{
			$frequency = !empty($prefs[$member]['msg_notify_pref']) ? $prefs[$member]['msg_notify_pref'] : 1;
			$notify_types = !empty($prefs[$member]['msg_notify_type']) ? $prefs[$member]['msg_notify_type'] : 1;

			// Don't send a notification if the watching member ignored the member who made the action.
			if (!empty($data['pm_ignore_list']) && in_array($data['id_member_updated'], explode(',', $data['pm_ignore_list'])))
				continue;
			if (!in_array($type, ['reply', 'topic']) && $notify_types == 2 && $member != $data['id_member_started'])
				continue;
			elseif (in_array($type, ['reply', 'topic']) && $member == $posterOptions['id'])
				continue;
			elseif (!in_array($type, ['reply', 'topic']) && $notify_types == 3)
				continue;
			elseif ($notify_types == 4)
				continue;

			if ($frequency > 2 || (!empty($frequency) && $data['sent']) || in_array($member, $done_members)
				|| (!empty($this->_details['members_only']) && !in_array($member, $this->_details['members_only'])))
				continue;

			// Watched topic?
			if (!empty($data['id_topic']) && $type != 'topic' && !empty($prefs[$member]))
			{
				$pref = !empty($prefs[$member]['topic_notify_' . $topicOptions['id']]) ? $prefs[$member]['topic_notify_' . $topicOptions['id']] : (!empty($prefs[$member]['topic_notify']) ? $prefs[$member]['topic_notify'] : 0);
				$message_type = 'notification_' . $type;

				if ($type == 'reply')
				{
					if (!empty($prefs[$member]['msg_receive_body']))
						$message_type .= '_body';
					if (!empty($frequency))
						$message_type .= '_once';
				}

				$content_type = 'topic';
			}
			// A new topic in a watched board then?
			elseif ($type == 'topic')
			{
				$pref = !empty($prefs[$member]['board_notify_' . $topicOptions['board']]) ? $prefs[$member]['board_notify_' . $topicOptions['board']] : (!empty($prefs[$member]['board_notify']) ? $prefs[$member]['board_notify'] : 0);

				$content_type = 'board';

				$message_type = !empty($frequency) ? 'notify_boards_once' : 'notify_boards';
				if (!empty($prefs[$member]['msg_receive_body']))
					$message_type .= '_body';
			}
			// If neither of the above, this might be a redundent row due to the OR clause in our SQL query, skip
			else
				continue;

			if ($pref & 0x02)
			{
				$replacements = [
					'TOPICSUBJECT' => $msgOptions['subject'],
					'POSTERNAME' => un_htmlspecialchars($posterOptions['name']),
					'TOPICLINK' => $scripturl . '?topic=' . $topicOptions['id'] . '.new#new',
					'MESSAGE' => $msgOptions['body'],
					'UNSUBSCRIBELINK' => $scripturl . '?action=notifyboard;board=' . $topicOptions['board'] . '.0',
				];

				$emaildata = loadEmailTemplate($message_type, $replacements, empty($data['lngfile']) || empty($modSettings['userLanguage']) ? $language : $data['lngfile']);
				Mail::send($data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicOptions['id'], $emaildata['is_html']);
			}

			if ($pref & 0x01)
			{
				$alert = [
					'alert_time' => time(),
					'id_member' => $member,
					// Only tell sender's information for new topics and replies
					'id_member_started' => in_array($type, ['topic', 'reply']) ? $posterOptions['id'] : 0,
					'member_name' => in_array($type, ['topic', 'reply']) ? $posterOptions['name'] : '',
					'chars_src' => !empty($posterOptions['char_id']) ? $posterOptions['char_id'] : 0,
					'chars_dest' => 0,
					'content_type' => $content_type,
					'content_id' => $topicOptions['id'],
					'content_action' => $type,
					'is_read' => 0,
					'extra' => [
						'topic' => $topicOptions['id'],
						'board' => $topicOptions['board'],
						'content_subject' => $msgOptions['subject'],
						'content_link' => $scripturl . '?topic=' . $topicOptions['id'] . '.new;topicseen#new',
					],
				];
				$alert['extra'] = json_encode($alert['extra']);
				$alert_rows[] = $alert;
			}

			$smcFunc['db']->query('', '
				UPDATE {db_prefix}log_notify
				SET sent = {int:is_sent}
				WHERE (id_topic = {int:topic} OR id_board = {int:board})
					AND id_member = {int:member}',
				[
					'topic' => $topicOptions['id'],
					'board' => $topicOptions['board'],
					'member' => $member,
					'is_sent' => 1,
				]
			);
		}

		// Insert it into the digest for daily/weekly notifications
		$smcFunc['db']->insert('',
			'{db_prefix}log_digest',
			[
				'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
			],
			[$topicOptions['id'], $msgOptions['id'], $type, $posterOptions['id']],
			[]
		);

		// Insert the alerts if any
		if (!empty($alert_rows))
		{
			$smcFunc['db']->insert('',
				'{db_prefix}user_alerts',
				['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string', 'chars_src' => 'int', 'chars_dest' => 'int',
					'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'],
				$alert_rows,
				[]
			);

			$members = [];
			foreach ($alert_rows as $alert)
			{
				$members[] = $alert['id_member'];
			}
			$members = array_unique($members);
			updateMemberData($members, ['alerts' => '+']);
		}

		return true;
	}

	/**
	 * Send notifications to people who have been quoted in a post.
	 * This assumes a new message is being posted, and other notifications to possible recipients have been handled.
	 *
	 * @param array $msgOptions The message being posted (as from Model\Post::create)
	 * @param array $posterOptions The person making the post
	 * @param array $quotedMembers A list of people (id -> details) that were quoted in this post
	 * @param array $prefs The preferences previously loaded for these people
	 * @param array $done_members Members previously handled by this round of notifications
	 * @param array $alert_rows The rows to be inserted into the database for this round of alerts
	 */
	protected static function handleQuoteNotifications($msgOptions, $posterOptions, $quotedMembers, $prefs, &$done_members, &$alert_rows)
	{
		global $modSettings, $language, $scripturl;

		foreach ($quotedMembers as $id => $member)
		{
			if (!isset($prefs[$id]) || $id == $posterOptions['id'] || empty($prefs[$id]['msg_quote']))
				continue;

			$done_members[] = $id;

			if ($prefs[$id]['msg_quote'] & 0x02)
			{
				$replacements = [
					'CONTENTSUBJECT' => $msgOptions['subject'],
					'QUOTENAME' => $posterOptions['name'],
					'MEMBERNAME' => $member['real_name'],
					'CONTENTLINK' => $scripturl . '?msg=' . $msgOptions['id'],
				];

				$emaildata = loadEmailTemplate('msg_quote', $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
				Mail::send($member['email_address'], $emaildata['subject'], $emaildata['body'], null, 'msg_quote_' . $msgOptions['id'], $emaildata['is_html'], 2);
			}

			if ($prefs[$id]['msg_quote'] & 0x01)
			{
				$this_alert = [
					'alert_time' => time(),
					'id_member' => $member['id_member'],
					'id_member_started' => $posterOptions['id'],
					'member_name' => $posterOptions['name'],
					'chars_src' => 0,
					'chars_dest' => 0,
					'content_type' => 'msg',
					'content_id' => $msgOptions['id'],
					'content_action' => 'quote',
					'is_read' => 0,
					'extra' => [
						'content_subject' => $msgOptions['subject'],
						'content_link' => $scripturl . '?msg=' . $msgOptions['id'],
					],
				];

				if (!empty($member['msgs']))
				{
					$quoted_msg = reset($member['msgs']);
					if (!$quoted_msg['is_main'])
					{
						$this_alert['chars_src'] = $posterOptions['char_id'];
						$this_alert['chars_dest'] = $quoted_msg['id_character'];
					}
				}
				$this_alert['extra'] = json_encode($this_alert['extra']);
				$alert_rows[] = $this_alert;

				updateMemberData($member['id_member'], ['alerts' => '+']);
			}
		}
	}

	/**
	 * From a message currently being posted, that contains quotes (with ids), identify which members were quoted in this post.
	 *
	 * @param array $msgOptions The message being posted (as from Model\Post::create)
	 * @param array $posterOptions The person making the post
	 * @return array An array of all the people being quoted, which messages of theirs are quoted, and which characters are relevant
	 */
	protected static function getQuotedMembers($msgOptions, $posterOptions)
	{
		global $smcFunc;

		$blocks = preg_split('/(\[quote.*?\]|\[\/quote\])/i', $msgOptions['body'], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		$quote_level = 0;
		$message = '';

		foreach ($blocks as $block)
		{
			if (preg_match('/\[quote(.*)?\]/i', $block, $matches))
			{
				if ($quote_level == 0)
					$message .= '[quote' . $matches[1] . ']';
				$quote_level++;
			}
			elseif (preg_match('/\[\/quote\]/i', $block))
			{
				if ($quote_level <= 1)
					$message .= '[/quote]';
				if ($quote_level >= 1)
				{
					$quote_level--;
					$message .= "\n";
				}
			}
			elseif ($quote_level <= 1)
				$message .= $block;
		}

		preg_match_all('/\[quote.*?link=msg=([0-9]+).*?\]/i', $message, $matches);

		$id_msgs = $matches[1];
		foreach ($id_msgs as $k => $id_msg)
			$id_msgs[$k] = (int) $id_msg;

		if (empty($id_msgs))
			return [];

		// Get the messages
		$request = $smcFunc['db']->query('', '
			SELECT m.id_msg, m.id_member, chars.id_character, chars.is_main,
				mem.email_address, mem.lngfile, mem.real_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				INNER JOIN {db_prefix}characters AS chars ON (chars.id_character = m.id_character AND chars.retired = 0)
			WHERE id_msg IN ({array_int:msgs})',
			[
				'msgs' => $id_msgs,
			]
		);

		$members = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if ($posterOptions['id'] == $row['id_member'])
				continue;

			if (!isset($members[$row['id_member']]))
			{
				$members[$row['id_member']] = [
					'id_member' => $row['id_member'],
					'email_address' => $row['email_address'],
					'lngfile' => $row['lngfile'],
					'real_name' => $row['real_name'],
				];
			}
			$members[$row['id_member']]['msgs'][$row['id_msg']] = [
				'id_character' => $row['id_character'],
				'is_main' => $row['is_main'],
			];
		}

		return $members;
	}

	/**
	 * Send notifications to people who have been mentioned in a post.
	 * This assumes a new message is being posted, and other notifications to possible recipients have been handled.
	 *
	 * @param array $msgOptions The message being posted (as from Model\Post::create)
	 * @param array $members The members that were mentioned in this post
	 * @param array $prefs The preferences previously loaded for these people
	 * @param array $done_members Members previously handled by this round of notifications
	 * @param array $alert_rows The rows to be inserted into the database for this round of alerts
	 */
	protected static function handleMentionedNotifications($msgOptions, $members, $prefs, &$done_members, &$alert_rows)
	{
		global $scripturl, $language, $modSettings;

		foreach ($members as $member)
		{
			$id = $member['id_member'];
			if ($member['retired_chr'])
				continue;

			if (!empty($prefs[$id]['msg_mention']))
				$done_members[] = $id;
			else
				continue;

			// Alerts' emails are always instant
			if ($prefs[$id]['msg_mention'] & 0x02)
			{
				$replacements = [
					'CONTENTSUBJECT' => $msgOptions['subject'],
					'MENTIONNAME' => $member['mentioned_by']['name'],
					'MEMBERNAME' => $member['real_name'],
					'CONTENTLINK' => $scripturl . '?msg=' . $msgOptions['id'],
				];

				$emaildata = loadEmailTemplate('msg_mention', $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
				Mail::send($member['email_address'], $emaildata['subject'], $emaildata['body'], null, 'msg_mention_' . $msgOptions['id'], $emaildata['is_html'], 2);
			}

			if ($prefs[$id]['msg_mention'] & 0x01)
			{
				$extra = [
					'content_subject' => $msgOptions['subject'],
					'content_link' => $scripturl . '?msg=' . $msgOptions['id'],
				];

				$alert_rows[] = [
					'alert_time' => time(),
					'id_member' => $member['id_member'],
					'id_member_started' => $member['mentioned_by']['id'],
					'member_name' => $member['mentioned_by']['name'],
					'chars_src' => !empty($member['dest_chr']) && empty($member['dest_is_main']) ? $member['dest_chr'] : 0,
					'chars_dest' => !empty($member['mentioned_by']['source_chr']) ? $member['mentioned_by']['source_chr'] : 0,
					'content_type' => 'msg',
					'content_id' => $msgOptions['id'],
					'content_action' => 'mention',
					'is_read' => 0,
					'extra' => json_encode($extra),
				];

				updateMemberData($member['id_member'], ['alerts' => '+']);
			}
		}
	}
}
