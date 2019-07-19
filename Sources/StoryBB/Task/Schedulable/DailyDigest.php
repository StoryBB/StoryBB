<?php
/**
 * Send out a daily email of all subscribed topics.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Send out a daily email of all subscribed topics.
 */
class DailyDigest extends \StoryBB\Task\Schedulable
{
	/** @var int $is_weekly Whether this digest is running a daily (0) or weekly (1) since logic is almost identical */
	protected $is_weekly = 0;

	/** @var string $subject_line Which entry in $txt to use as the subject for digest emails */
	protected $subject_line = 'digest_subject_daily';

	/** @var string $intro_line Which entry in $txt to use as the intro text for digest emails */
	protected $intro_line = 'digest_intro_daily';

	/**
	 * Send out a daily email of all subscribed topics.
	 *
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $txt, $mbname, $scripturl, $sourcedir, $smcFunc, $modSettings;

		// We'll want this...
		require_once($sourcedir . '/Subs-Post.php');
		loadEssentialThemeData();

		// Right - get all the notification data FIRST.
		$request = $smcFunc['db_query']('', '
			SELECT ln.id_topic, COALESCE(t.id_board, ln.id_board) AS id_board, mem.email_address, mem.member_name,
				mem.lngfile, mem.id_member
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
				LEFT JOIN {db_prefix}topics AS t ON (ln.id_topic != {int:empty_topic} AND t.id_topic = ln.id_topic)
			WHERE mem.is_activated = {int:is_activated}',
			array(
				'empty_topic' => 0,
				'is_activated' => 1,
			)
		);
		$members = [];
		$langs = [];
		$notify = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($members[$row['id_member']]))
			{
				$members[$row['id_member']] = array(
					'email' => $row['email_address'],
					'name' => $row['member_name'],
					'id' => $row['id_member'],
					'lang' => $row['lngfile'],
				);
				$langs[$row['lngfile']] = $row['lngfile'];
			}

			// Store this useful data!
			$boards[$row['id_board']] = $row['id_board'];
			if ($row['id_topic'])
				$notify['topics'][$row['id_topic']][] = $row['id_member'];
			else
				$notify['boards'][$row['id_board']][] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		if (empty($boards))
			return true;

		// Just get the board names.
		$request = $smcFunc['db_query']('', '
			SELECT id_board, name
			FROM {db_prefix}boards
			WHERE id_board IN ({array_int:board_list})',
			array(
				'board_list' => $boards,
			)
		);
		$boards = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards[$row['id_board']] = $row['name'];
		$smcFunc['db_free_result']($request);

		if (empty($boards))
			return true;

		// Get the actual topics...
		$request = $smcFunc['db_query']('', '
			SELECT ld.note_type, t.id_topic, t.id_board, t.id_member_started, m.id_msg, m.subject,
				b.name AS board_name
			FROM {db_prefix}log_digest AS ld
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ld.id_topic
					AND t.id_board IN ({array_int:board_list}))
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . ($this->is_weekly ? 'ld.daily != {int:daily_value}' : 'ld.daily IN (0, 2)'),
			array(
				'board_list' => array_keys($boards),
				'daily_value' => 2,
			)
		);
		$types = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($types[$row['note_type']][$row['id_board']]))
				$types[$row['note_type']][$row['id_board']] = array(
					'lines' => [],
					'name' => $row['board_name'],
					'id' => $row['id_board'],
				);

			if ($row['note_type'] == 'reply')
			{
				if (isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['count']++;
				else
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
						'id' => $row['id_topic'],
						'subject' => un_htmlspecialchars($row['subject']),
						'count' => 1,
					);
			}
			elseif ($row['note_type'] == 'topic')
			{
				if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
						'id' => $row['id_topic'],
						'subject' => un_htmlspecialchars($row['subject']),
					);
			}
			else
			{
				if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
						'id' => $row['id_topic'],
						'subject' => un_htmlspecialchars($row['subject']),
						'starter' => $row['id_member_started'],
					);
			}

			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = [];
			if (!empty($notify['topics'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['topics'][$row['id_topic']]);
			if (!empty($notify['boards'][$row['id_board']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['boards'][$row['id_board']]);
		}
		$smcFunc['db_free_result']($request);

		if (empty($types))
			return true;

		// Let's load all the languages into a cache thingy.
		$langtxt = [];
		foreach ($langs as $lang)
		{
			loadLanguage('Post', $lang);
			loadLanguage('General', $lang);
			loadLanguage('EmailTemplates', $lang);
			$langtxt[$lang] = array(
				'subject' => $txt[$this->subject_line],
				'intro' => sprintf($txt[$this->intro_line], $mbname),
				'new_topics' => $txt['digest_new_topics'],
				'topic_lines' => $txt['digest_new_topics_line'],
				'new_replies' => $txt['digest_new_replies'],
				'mod_actions' => $txt['digest_mod_actions'],
				'replies_one' => $txt['digest_new_replies_one'],
				'replies_many' => $txt['digest_new_replies_many'],
				'sticky' => $txt['digest_mod_act_sticky'],
				'lock' => $txt['digest_mod_act_lock'],
				'unlock' => $txt['digest_mod_act_unlock'],
				'remove' => $txt['digest_mod_act_remove'],
				'move' => $txt['digest_mod_act_move'],
				'merge' => $txt['digest_mod_act_merge'],
				'split' => $txt['digest_mod_act_split'],
				'bye' => str_replace('{forum_name}', $mbname, $txt['regards_team']),
			);
		}

		// The preferred way...
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs(array_keys($members), array('msg_notify_type', 'msg_notify_pref'), true);

		// Right - send out the silly things - this will take quite some space!
		foreach ($members as $mid => $member)
		{
			$frequency = !empty($prefs[$mid]['msg_notify_pref']) ? $prefs[$mid]['msg_notify_pref'] : 1;
			$notify_types = !empty($prefs[$mid]['msg_notify_type']) ? $prefs[$mid]['msg_notify_type'] : 1;

			// Did they not elect to choose this?
			if ($frequency == 4 && !$this->is_weekly || $frequency == 3 && $this->is_weekly || $notify_types == 4)
				continue;

			// Do the start stuff!
			$email = array(
				'subject' => $mbname . ' - ' . $langtxt[$lang]['subject'],
				'body' => $member['name'] . ',' . "\n\n" . $langtxt[$lang]['intro'] . "\n" . $scripturl . '?action=profile;area=notification;u=' . $member['id'] . "\n",
				'email' => $member['email'],
			);

			// All new topics?
			if (isset($types['topic']))
			{
				$titled = false;
				foreach ($types['topic'] as $id => $board)
					foreach ($board['lines'] as $topic)
						if (in_array($mid, $topic['members']))
						{
							if (!$titled)
							{
								$email['body'] .= "\n" . $langtxt[$lang]['new_topics'] . ':' . "\n" . '-----------------------------------------------';
								$titled = true;
							}
							$email['body'] .= "\n" . sprintf($langtxt[$lang]['topic_lines'], $topic['subject'], $board['name']);
						}
				if ($titled)
					$email['body'] .= "\n";
			}

			// What about replies?
			if (isset($types['reply']))
			{
				$titled = false;
				foreach ($types['reply'] as $id => $board)
					foreach ($board['lines'] as $topic)
						if (in_array($mid, $topic['members']))
						{
							if (!$titled)
							{
								$email['body'] .= "\n" . $langtxt[$lang]['new_replies'] . ':' . "\n" . '-----------------------------------------------';
								$titled = true;
							}
							$email['body'] .= "\n" . ($topic['count'] == 1 ? sprintf($langtxt[$lang]['replies_one'], $topic['subject']) : sprintf($langtxt[$lang]['replies_many'], $topic['count'], $topic['subject']));
						}

				if ($titled)
					$email['body'] .= "\n";
			}

			// Finally, moderation actions!
			if ($notify_types < 3)
			{
				$titled = false;
				foreach ($types as $note_type => $type)
				{
					if ($note_type == 'topic' || $note_type == 'reply')
						continue;

					foreach ($type as $id => $board)
						foreach ($board['lines'] as $topic)
							if (in_array($mid, $topic['members']))
							{
								if (!$titled)
								{
									$email['body'] .= "\n" . $langtxt[$lang]['mod_actions'] . ':' . "\n" . '-----------------------------------------------';
									$titled = true;
								}
								$email['body'] .= "\n" . sprintf($langtxt[$lang][$note_type], $topic['subject']);
							}
				}
			}
			if ($titled)
				$email['body'] .= "\n";

			// Then just say our goodbyes!
			$email['body'] .= "\n\n" . str_replace('{forum_name}', $mbname, $txt['regards_team']);

			// Send it - low priority!
			StoryBB\Helper\Mail::send($email['email'], $email['subject'], $email['body'], null, 'digest', false, 4);
		}

		// Clean up...
		$this->mark_done();

		// Just in case the member changes their settings mark this as sent.
		$members = array_keys($members);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $members,
				'is_sent' => 1,
			)
		);

		// Log we've done it...
		return true;
	}

	/**
	 * Mark all current items in the digest log as having been sent.
	 */
	protected function mark_done()
	{
		global $smcFunc;

		// Clear any only weekly ones, and stop us from sending daily again.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_digest
			WHERE daily = {int:daily_value}',
			array(
				'daily_value' => 2,
			)
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_digest
			SET daily = {int:both_value}
			WHERE daily = {int:no_value}',
			array(
				'both_value' => 1,
				'no_value' => 0,
			)
		);
	}
}
