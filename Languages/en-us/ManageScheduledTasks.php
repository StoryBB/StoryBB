<?php

/**
 * This file contains language strings for the scheduled tasks system.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

$txt['scheduled_tasks_title'] = 'Scheduled Tasks';
$txt['scheduled_tasks_header'] = 'All Scheduled Tasks';
$txt['scheduled_tasks_name'] = 'Task Name';
$txt['scheduled_tasks_next_time'] = 'Next Due';
$txt['scheduled_tasks_regularity'] = 'Regularity';
$txt['scheduled_tasks_enabled'] = 'Enabled';
$txt['scheduled_tasks_run_now'] = 'Run Now';
$txt['scheduled_tasks_save_changes'] = 'Save Changes';

$txt['scheduled_tasks_ran_successfully'] = 'Task "%1$s" ran successfully.';
$txt['scheduled_tasks_ran_errors'] = 'Task "%1$s" encountered errors: %2$s';

$txt['scheduled_tasks_na'] = 'N/A';
$txt['scheduled_task_approval_notification'] = 'Approval Notifications';
$txt['scheduled_task_desc_approval_notification'] = 'Send out emails to all moderators summarizing posts awaiting approval.';
$txt['scheduled_task_daily_maintenance'] = 'Daily Maintenance';
$txt['scheduled_task_desc_daily_maintenance'] = 'Runs essential daily maintenance on the forum - should not be disabled.';
$txt['scheduled_task_daily_digest'] = 'Daily Notification Summary';
$txt['scheduled_task_desc_daily_digest'] = 'Emails out the daily digest for notification subscribers.';
$txt['scheduled_task_weekly_digest'] = 'Weekly Notification Summary';
$txt['scheduled_task_desc_weekly_digest'] = 'Emails out the weekly digest for notification subscribers.';
$txt['scheduled_task_fetchStoryBBfiles'] = 'Fetch StoryBB Files';
$txt['scheduled_task_desc_fetchStoryBBfiles'] = 'Retrieves JavaScript files containing notifications of updates and other information.';
$txt['scheduled_task_birthdayemails'] = 'Send Birthday Emails';
$txt['scheduled_task_desc_birthdayemails'] = 'Sends out emails wishing members a happy birthday.';
$txt['scheduled_task_weekly_maintenance'] = 'Weekly Maintenance';
$txt['scheduled_task_desc_weekly_maintenance'] = 'Runs essential weekly maintenance on the forum - should not be disabled.';
$txt['scheduled_task_paid_subscriptions'] = 'Paid Subscription Checks';
$txt['scheduled_task_desc_paid_subscriptions'] = 'Sends out any necessary paid subscription reminders and removes expired member subscriptions.';
$txt['scheduled_task_remove_topic_redirect'] = 'Remove MOVED: redirection topics';
$txt['scheduled_task_desc_remove_topic_redirect'] = 'Deletes "MOVED:" topic notifications as specified when the moved notice was created.';
$txt['scheduled_task_remove_temp_attachments'] = 'Remove temporary attachment files';
$txt['scheduled_task_desc_remove_temp_attachments'] = 'Deletes temporary files created while attaching a file to a post that for any reason weren\'t renamed or deleted before.';
$txt['scheduled_task_clean_exports'] = 'Clean data exports';
$txt['scheduled_task_desc_clean_exports'] = 'Removes archived data exports once they expire. Should not be disabled.';
$txt['scheduled_task_scrub_logs'] = 'Scrub logs for privacy';
$txt['scheduled_task_desc_scrub_logs'] = 'Removes privacy-related data from logs. Should not be disabled.';
$txt['scheduled_task_remove_unapproved_accts'] = 'Remove unapproved accounts';
$txt['scheduled_task_desc_remove_unapproved_accts'] = 'Removed accounts that have not been approved. Should not be disabled.';
$txt['scheduled_task_remove_old_alerts'] = 'Remove Old Alerts';
$txt['scheduled_task_desc_remove_old_alerts'] = 'Removes read alerts once they have been read at least a week. Should not be disabled.';

$txt['scheduled_task_reg_starting'] = 'Starting at %1$s';
$txt['scheduled_task_reg_repeating'] = 'repeating every %1$d %2$s';
$txt['scheduled_task_reg_unit_m'] = 'minute(s)';
$txt['scheduled_task_reg_unit_h'] = 'hour(s)';
$txt['scheduled_task_reg_unit_d'] = 'day(s)';
$txt['scheduled_task_reg_unit_w'] = 'week(s)';

$txt['scheduled_task_edit'] = 'Edit Scheduled Task';
$txt['scheduled_task_edit_repeat'] = 'Repeat task every';
$txt['scheduled_task_edit_interval'] = 'Interval';
$txt['scheduled_task_edit_start_time'] = 'Start Time';
$txt['scheduled_task_edit_start_time_desc'] = 'Time the first instance of the day should start (hours:minutes)';
$txt['scheduled_task_time_offset'] = 'Note the start time should be the offset against the current server time. Current server time is: %1$s';

$txt['scheduled_view_log'] = 'View Log';
$txt['scheduled_log_empty'] = 'There are currently no task log entries.';
$txt['scheduled_log_time_run'] = 'Time Run';
$txt['scheduled_log_time_taken'] = 'Time taken';
$txt['scheduled_log_time_taken_seconds'] = '%1$d seconds';
$txt['scheduled_log_empty_log'] = 'Clear Log';
$txt['scheduled_log_empty_log_confirm'] = 'Are you sure you want to completely clear the log?';

$txt['adhoc_log_empty_all_tasks'] = 'Clear all tasks';
$txt['adhoc_log_empty_all_tasks_confirm'] = 'Are you sure you want to completely clear all tasks?';
$txt['adhoc_log_empty_selected_tasks'] = 'Clear selected tasks';
$txt['adhoc_log_empty_selected_tasks_confirm'] = 'Are you sure you want to clear the selected tasks?';

$txt['scheduled_task_remove_old_drafts'] = 'Remove old drafts';
$txt['scheduled_task_desc_remove_old_drafts'] = 'Deletes drafts older than the number of days defined in the draft settings in the admin panel.';
