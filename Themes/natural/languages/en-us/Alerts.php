<?php

/**
 * This file contains language strings for the alerts system.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

// Load Alerts strings
$txt['topic_na'] = '(private topic)';
$txt['board_na'] = '(private board)';

$txt['all_alerts'] = 'All alerts';
$txt['mark_alerts_read'] = 'Mark read';
$txt['alert_settings'] = 'Settings';
$txt['alerts_no_unread'] = 'No unread alerts.';

$txt['alert_topic_reply'] = '{char_link} replied to the topic {topic_msg}';
$txt['alert_topic_move'] = 'The topic {topic_msg} has been moved to {board_msg}';
$txt['alert_topic_remove'] = 'The topic {topic_msg} has been deleted';
$txt['alert_topic_unlock'] = 'The topic {topic_msg} has been unlocked';
$txt['alert_topic_lock'] = 'The topic {topic_msg} has been locked';
$txt['alert_topic_split'] = 'The topic {topic_msg} has been split';
$txt['alert_topic_merge'] = 'One or more topics have been merged into {topic_msg}';
$txt['alert_topic_sticky'] = 'The topic {topic_msg} has been stickied';
$txt['alert_board_topic'] = '{char_link} started a new topic {topic_msg} in {board_msg}';
$txt['alert_unapproved_topic'] = '{char_link} started a new unapproved topic {topic_msg} in {board_msg}';
$txt['alert_unapproved_post'] = '{char_link} made a new unapproved post {topic_msg} in {board_msg}';
$txt['alert_unapproved_reply'] = '{char_link} replied to your unapproved topic {topic_msg} in {board_msg}';
$txt['alert_msg_quote'] = '{member_link} quoted you in the post {msg_msg}';
$txt['alert_msg_mention'] = '{member_link} mentioned you in the post {msg_msg}';
$txt['alert_msg_like'] = '{member_link} liked your post {msg_msg}';
$txt['alert_msg_report'] = '{member_link} <a href="{scripturl}{report_link}">reported a post</a> - {msg_msg}';
$txt['alert_msg_report_reply'] = '{member_link} replied to <a href="{scripturl}{report_link}">the report</a> about {msg_msg}';
$txt['alert_profile_report'] = '{member_link} <a href="{scripturl}{report_link}">reported</a> the profile of {profile_msg}';
$txt['alert_profile_report_reply'] = '{member_link} replied to <a href="{scripturl}{report_link}">the report</a> about the profile of {profile_msg}';
$txt['alert_member_register_standard'] = '{member_link} just signed up';
$txt['alert_member_register_approval'] = '{member_link} just signed up (account requires approval)';
$txt['alert_member_register_activation'] = '{member_link} just signed up (account requires activation)';
$txt['alert_member_group_request'] = '{member_link} has <a href="{scripturl}?action=moderate;area=groups;sa=requests">requested</a> to join {group_name}';
$txt['alert_groupr_approved'] = 'Your request to join {group_name} has been approved';
$txt['alert_groupr_rejected'] = 'Your request to join {group_name} has been rejected{reason}';
$txt['alert_paidsubs_expiring'] = 'Your subscription to <a href="{scripturl}?action=profile;area=subscriptions">{subscription_name}</a> is about to expire at {end_time}';
$txt['alert_buddy_buddy_request'] = '{member_link} added you as their buddy';
$txt['alert_birthday_msg'] = '{happy_birthday}';
$txt['alerts_none'] = 'You have no alerts.';
$txt['alert_msg_quotechr'] = '{char_link} quoted {your_chr} in the post {msg_msg}';
$txt['alert_msg_mentionchr'] = '{char_link} mentioned {your_chr} in the post {msg_msg}';
$txt['alert_msg_likechr'] = '{member_link} liked {your_chr}\'s post {msg_msg}';
$txt['alert_member_char_sheet_approval'] = '{char_link}\'s <a href="#{char_sheet_link}">character sheet</a> is awaiting approval.';
$txt['alert_member_char_sheet_approvedchr'] = '{your_chr}\'s character sheet was approved.';
$txt['alert_member_export_complete'] = 'An export of your posts and attachments is <a href="{scripturl}{export_link}">ready for you</a>.';
$txt['alert_member_export_complete_admin'] = 'An export of posts and attachments for {member_link} is now <a href="{scripturl}{export_link}">ready for download</a>.';
$txt['alert_contactform_received'] = '<a href="{scripturl}{contact_link}">A new message</a> has been received via the contact form.';
