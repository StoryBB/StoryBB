<?php
/**
 * Perform the standard checks on expiring/near expiring subscriptions.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Perform the standard checks on expiring/near expiring subscriptions.
 */
class UpdatePaidSubs extends \StoryBB\Task\Schedulable
{
	/**
	 * Perform the standard checks on expiring/near expiring subscriptions.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $sourcedir, $scripturl, $smcFunc, $modSettings, $language;

		require_once($sourcedir . '/ManagePaid.php');
		require_once($sourcedir . '/Subs-Post.php');

		// Start off by checking for removed subscriptions.
		$request = $smcFunc['db_query']('', '
			SELECT id_subscribe, id_member
			FROM {db_prefix}log_subscribed
			WHERE status = {int:is_active}
				AND end_time < {int:time_now}',
			array(
				'is_active' => 1,
				'time_now' => time(),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			removeSubscription($row['id_subscribe'], $row['id_member']);
		}
		$smcFunc['db_free_result']($request);

		// Get all those about to expire that have not had a reminder sent.
		$request = $smcFunc['db_query']('', '
			SELECT ls.id_sublog, m.id_member, m.member_name, m.email_address, m.lngfile, s.name, ls.end_time
			FROM {db_prefix}log_subscribed AS ls
				INNER JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
				INNER JOIN {db_prefix}members AS m ON (m.id_member = ls.id_member)
			WHERE ls.status = {int:is_active}
				AND ls.reminder_sent = {int:reminder_sent}
				AND s.reminder > {int:reminder_wanted}
				AND ls.end_time < ({int:time_now} + s.reminder * 86400)',
			array(
				'is_active' => 1,
				'reminder_sent' => 0,
				'reminder_wanted' => 0,
				'time_now' => time(),
			)
		);
		$subs_reminded = [];
		$members = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If this is the first one load the important bits.
			if (empty($subs_reminded))
			{
				// Need the below for loadLanguage to work!
				loadEssentialThemeData();
			}

			$subs_reminded[] = $row['id_sublog'];
			$members[$row['id_member']] = $row;
		}
		$smcFunc['db_free_result']($request);

		// Load alert preferences
		require_once($sourcedir . '/Subs-Notify.php');
		$notifyPrefs = getNotifyPrefs(array_keys($members), 'paidsubs_expiring', true);
		$alert_rows = [];
		foreach ($members as $row)
		{
			$replacements = array(
				'PROFILE_LINK' => $scripturl . '?action=profile;area=subscriptions;u=' . $row['id_member'],
				'REALNAME' => $row['member_name'],
				'SUBSCRIPTION' => $row['name'],
				'END_DATE' => strip_tags(timeformat($row['end_time'])),
			);

			$emaildata = loadEmailTemplate('paid_subscription_reminder', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			// Send the actual email.
			if ($notifyPrefs[$row['id_member']] & 0x02)
				StoryBB\Helper\Mail::send($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'paid_sub_remind', $emaildata['is_html'], 2);

			if ($notifyPrefs[$row['id_member']] & 0x01)
			{
				$alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $row['id_member'],
					'id_member_started' => $row['id_member'],
					'member_name' => $row['member_name'],
					'content_type' => 'paidsubs',
					'content_id' => $row['id_sublog'],
					'content_action' => 'expiring',
					'is_read' => 0,
					'extra' => json_encode(array(
						'subscription_name' => $row['name'],
						'end_time' => strip_tags(timeformat($row['end_time'])),
					)),
				);
				updateMemberData($row['id_member'], array('alerts' => '+'));
			}
		}

		// Insert the alerts if any
		if (!empty($alert_rows))
			$smcFunc['db_insert']('',
				'{db_prefix}user_alerts',
				array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
					'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
				$alert_rows,
				[]
			);

		// Mark the reminder as sent.
		if (!empty($subs_reminded))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_subscribed
				SET reminder_sent = {int:reminder_sent}
				WHERE id_sublog IN ({array_int:subscription_list})',
				array(
					'subscription_list' => $subs_reminded,
					'reminder_sent' => 1,
				)
			);

		return true;
	}
}
