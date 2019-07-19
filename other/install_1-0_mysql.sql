#### ATTENTION: You do not need to run or use this file!  The install.php script does everything for you!
#### Install script for MySQL 4.0.18+

#
# Dumping data for table `admin_info_files`
#

INSERT INTO {$db_prefix}admin_info_files
  (id_file, filename, path, parameters, data, filetype)
VALUES
  (1, 'updates.json', '', '', '', 'application/json'),
  (2, 'versions.json', '', '', '', 'application/json');
# --------------------------------------------------------

#
# Dumping data for table `board_permissions`
#

INSERT INTO {$db_prefix}board_permissions
  (id_group, id_profile, permission)
VALUES (-1, 1, 'poll_view'),
  (0, 1, 'remove_own'),
  (0, 1, 'lock_own'),
  (0, 1, 'modify_own'),
  (0, 1, 'poll_add_own'),
  (0, 1, 'poll_edit_own'),
  (0, 1, 'poll_lock_own'),
  (0, 1, 'poll_post'),
  (0, 1, 'poll_view'),
  (0, 1, 'poll_vote'),
  (0, 1, 'post_attachment'),
  (0, 1, 'post_new'),
  (0, 1, 'post_draft'),
  (0, 1, 'post_reply_any'),
  (0, 1, 'post_reply_own'),
  (0, 1, 'post_unapproved_topics'),
  (0, 1, 'post_unapproved_replies_any'),
  (0, 1, 'post_unapproved_replies_own'),
  (0, 1, 'post_unapproved_attachments'),
  (0, 1, 'delete_own'),
  (0, 1, 'report_any'),
  (0, 1, 'view_attachments'),
  (2, 1, 'moderate_board'),
  (2, 1, 'post_new'),
  (2, 1, 'post_draft'),
  (2, 1, 'post_reply_own'),
  (2, 1, 'post_reply_any'),
  (2, 1, 'post_unapproved_topics'),
  (2, 1, 'post_unapproved_replies_any'),
  (2, 1, 'post_unapproved_replies_own'),
  (2, 1, 'post_unapproved_attachments'),
  (2, 1, 'poll_post'),
  (2, 1, 'poll_add_any'),
  (2, 1, 'poll_remove_any'),
  (2, 1, 'poll_view'),
  (2, 1, 'poll_vote'),
  (2, 1, 'poll_lock_any'),
  (2, 1, 'poll_edit_any'),
  (2, 1, 'report_any'),
  (2, 1, 'lock_own'),
  (2, 1, 'delete_own'),
  (2, 1, 'modify_own'),
  (2, 1, 'make_sticky'),
  (2, 1, 'lock_any'),
  (2, 1, 'remove_any'),
  (2, 1, 'move_any'),
  (2, 1, 'merge_any'),
  (2, 1, 'split_any'),
  (2, 1, 'delete_any'),
  (2, 1, 'modify_any'),
  (2, 1, 'approve_posts'),
  (2, 1, 'post_attachment'),
  (2, 1, 'view_attachments'),
  (3, 1, 'moderate_board'),
  (3, 1, 'post_new'),
  (3, 1, 'post_draft'),
  (3, 1, 'post_reply_own'),
  (3, 1, 'post_reply_any'),
  (3, 1, 'post_unapproved_topics'),
  (3, 1, 'post_unapproved_replies_any'),
  (3, 1, 'post_unapproved_replies_own'),
  (3, 1, 'post_unapproved_attachments'),
  (3, 1, 'poll_post'),
  (3, 1, 'poll_add_any'),
  (3, 1, 'poll_remove_any'),
  (3, 1, 'poll_view'),
  (3, 1, 'poll_vote'),
  (3, 1, 'poll_lock_any'),
  (3, 1, 'poll_edit_any'),
  (3, 1, 'report_any'),
  (3, 1, 'lock_own'),
  (3, 1, 'delete_own'),
  (3, 1, 'modify_own'),
  (3, 1, 'make_sticky'),
  (3, 1, 'lock_any'),
  (3, 1, 'remove_any'),
  (3, 1, 'move_any'),
  (3, 1, 'merge_any'),
  (3, 1, 'split_any'),
  (3, 1, 'delete_any'),
  (3, 1, 'modify_any'),
  (3, 1, 'approve_posts'),
  (3, 1, 'post_attachment'),
  (3, 1, 'view_attachments'),
  (-1, 2, 'poll_view'),
  (0, 2, 'remove_own'),
  (0, 2, 'lock_own'),
  (0, 2, 'modify_own'),
  (0, 2, 'poll_view'),
  (0, 2, 'poll_vote'),
  (0, 2, 'post_attachment'),
  (0, 2, 'post_new'),
  (0, 2, 'post_draft'),
  (0, 2, 'post_reply_any'),
  (0, 2, 'post_reply_own'),
  (0, 2, 'post_unapproved_topics'),
  (0, 2, 'post_unapproved_replies_any'),
  (0, 2, 'post_unapproved_replies_own'),
  (0, 2, 'post_unapproved_attachments'),
  (0, 2, 'delete_own'),
  (0, 2, 'report_any'),
  (0, 2, 'view_attachments'),
  (2, 2, 'moderate_board'),
  (2, 2, 'post_new'),
  (2, 2, 'post_draft'),
  (2, 2, 'post_reply_own'),
  (2, 2, 'post_reply_any'),
  (2, 2, 'post_unapproved_topics'),
  (2, 2, 'post_unapproved_replies_any'),
  (2, 2, 'post_unapproved_replies_own'),
  (2, 2, 'post_unapproved_attachments'),
  (2, 2, 'poll_post'),
  (2, 2, 'poll_add_any'),
  (2, 2, 'poll_remove_any'),
  (2, 2, 'poll_view'),
  (2, 2, 'poll_vote'),
  (2, 2, 'poll_lock_any'),
  (2, 2, 'poll_edit_any'),
  (2, 2, 'report_any'),
  (2, 2, 'lock_own'),
  (2, 2, 'delete_own'),
  (2, 2, 'modify_own'),
  (2, 2, 'make_sticky'),
  (2, 2, 'lock_any'),
  (2, 2, 'remove_any'),
  (2, 2, 'move_any'),
  (2, 2, 'merge_any'),
  (2, 2, 'split_any'),
  (2, 2, 'delete_any'),
  (2, 2, 'modify_any'),
  (2, 2, 'approve_posts'),
  (2, 2, 'post_attachment'),
  (2, 2, 'view_attachments'),
  (3, 2, 'moderate_board'),
  (3, 2, 'post_new'),
  (3, 2, 'post_draft'),
  (3, 2, 'post_reply_own'),
  (3, 2, 'post_reply_any'),
  (3, 2, 'post_unapproved_topics'),
  (3, 2, 'post_unapproved_replies_any'),
  (3, 2, 'post_unapproved_replies_own'),
  (3, 2, 'post_unapproved_attachments'),
  (3, 2, 'poll_post'),
  (3, 2, 'poll_add_any'),
  (3, 2, 'poll_remove_any'),
  (3, 2, 'poll_view'),
  (3, 2, 'poll_vote'),
  (3, 2, 'poll_lock_any'),
  (3, 2, 'poll_edit_any'),
  (3, 2, 'report_any'),
  (3, 2, 'lock_own'),
  (3, 2, 'delete_own'),
  (3, 2, 'modify_own'),
  (3, 2, 'make_sticky'),
  (3, 2, 'lock_any'),
  (3, 2, 'remove_any'),
  (3, 2, 'move_any'),
  (3, 2, 'merge_any'),
  (3, 2, 'split_any'),
  (3, 2, 'delete_any'),
  (3, 2, 'modify_any'),
  (3, 2, 'approve_posts'),
  (3, 2, 'post_attachment'),
  (3, 2, 'view_attachments'),
  (-1, 3, 'poll_view'),
  (0, 3, 'remove_own'),
  (0, 3, 'lock_own'),
  (0, 3, 'modify_own'),
  (0, 3, 'poll_view'),
  (0, 3, 'poll_vote'),
  (0, 3, 'post_attachment'),
  (0, 3, 'post_reply_any'),
  (0, 3, 'post_reply_own'),
  (0, 3, 'post_unapproved_replies_any'),
  (0, 3, 'post_unapproved_replies_own'),
  (0, 3, 'post_unapproved_attachments'),
  (0, 3, 'delete_own'),
  (0, 3, 'report_any'),
  (0, 3, 'view_attachments'),
  (2, 3, 'moderate_board'),
  (2, 3, 'post_new'),
  (2, 3, 'post_draft'),
  (2, 3, 'post_reply_own'),
  (2, 3, 'post_reply_any'),
  (2, 3, 'post_unapproved_topics'),
  (2, 3, 'post_unapproved_replies_any'),
  (2, 3, 'post_unapproved_replies_own'),
  (2, 3, 'post_unapproved_attachments'),
  (2, 3, 'poll_post'),
  (2, 3, 'poll_add_any'),
  (2, 3, 'poll_remove_any'),
  (2, 3, 'poll_view'),
  (2, 3, 'poll_vote'),
  (2, 3, 'poll_lock_any'),
  (2, 3, 'poll_edit_any'),
  (2, 3, 'report_any'),
  (2, 3, 'lock_own'),
  (2, 3, 'delete_own'),
  (2, 3, 'modify_own'),
  (2, 3, 'make_sticky'),
  (2, 3, 'lock_any'),
  (2, 3, 'remove_any'),
  (2, 3, 'move_any'),
  (2, 3, 'merge_any'),
  (2, 3, 'split_any'),
  (2, 3, 'delete_any'),
  (2, 3, 'modify_any'),
  (2, 3, 'approve_posts'),
  (2, 3, 'post_attachment'),
  (2, 3, 'view_attachments'),
  (3, 3, 'moderate_board'),
  (3, 3, 'post_new'),
  (3, 3, 'post_draft'),
  (3, 3, 'post_reply_own'),
  (3, 3, 'post_reply_any'),
  (3, 3, 'post_unapproved_topics'),
  (3, 3, 'post_unapproved_replies_any'),
  (3, 3, 'post_unapproved_replies_own'),
  (3, 3, 'post_unapproved_attachments'),
  (3, 3, 'poll_post'),
  (3, 3, 'poll_add_any'),
  (3, 3, 'poll_remove_any'),
  (3, 3, 'poll_view'),
  (3, 3, 'poll_vote'),
  (3, 3, 'poll_lock_any'),
  (3, 3, 'poll_edit_any'),
  (3, 3, 'report_any'),
  (3, 3, 'lock_own'),
  (3, 3, 'delete_own'),
  (3, 3, 'modify_own'),
  (3, 3, 'make_sticky'),
  (3, 3, 'lock_any'),
  (3, 3, 'remove_any'),
  (3, 3, 'move_any'),
  (3, 3, 'merge_any'),
  (3, 3, 'split_any'),
  (3, 3, 'delete_any'),
  (3, 3, 'modify_any'),
  (3, 3, 'approve_posts'),
  (3, 3, 'post_attachment'),
  (3, 3, 'view_attachments'),
  (-1, 4, 'poll_view'),
  (0, 4, 'poll_view'),
  (0, 4, 'poll_vote'),
  (0, 4, 'report_any'),
  (0, 4, 'view_attachments'),
  (2, 4, 'moderate_board'),
  (2, 4, 'post_new'),
  (2, 4, 'post_draft'),
  (2, 4, 'post_reply_own'),
  (2, 4, 'post_reply_any'),
  (2, 4, 'post_unapproved_topics'),
  (2, 4, 'post_unapproved_replies_any'),
  (2, 4, 'post_unapproved_replies_own'),
  (2, 4, 'post_unapproved_attachments'),
  (2, 4, 'poll_post'),
  (2, 4, 'poll_add_any'),
  (2, 4, 'poll_remove_any'),
  (2, 4, 'poll_view'),
  (2, 4, 'poll_vote'),
  (2, 4, 'poll_lock_any'),
  (2, 4, 'poll_edit_any'),
  (2, 4, 'report_any'),
  (2, 4, 'lock_own'),
  (2, 4, 'delete_own'),
  (2, 4, 'modify_own'),
  (2, 4, 'make_sticky'),
  (2, 4, 'lock_any'),
  (2, 4, 'remove_any'),
  (2, 4, 'move_any'),
  (2, 4, 'merge_any'),
  (2, 4, 'split_any'),
  (2, 4, 'delete_any'),
  (2, 4, 'modify_any'),
  (2, 4, 'approve_posts'),
  (2, 4, 'post_attachment'),
  (2, 4, 'view_attachments'),
  (3, 4, 'moderate_board'),
  (3, 4, 'post_new'),
  (3, 4, 'post_draft'),
  (3, 4, 'post_reply_own'),
  (3, 4, 'post_reply_any'),
  (3, 4, 'post_unapproved_topics'),
  (3, 4, 'post_unapproved_replies_any'),
  (3, 4, 'post_unapproved_replies_own'),
  (3, 4, 'post_unapproved_attachments'),
  (3, 4, 'poll_post'),
  (3, 4, 'poll_add_any'),
  (3, 4, 'poll_remove_any'),
  (3, 4, 'poll_view'),
  (3, 4, 'poll_vote'),
  (3, 4, 'poll_lock_any'),
  (3, 4, 'poll_edit_any'),
  (3, 4, 'report_any'),
  (3, 4, 'lock_own'),
  (3, 4, 'delete_own'),
  (3, 4, 'modify_own'),
  (3, 4, 'make_sticky'),
  (3, 4, 'lock_any'),
  (3, 4, 'remove_any'),
  (3, 4, 'move_any'),
  (3, 4, 'merge_any'),
  (3, 4, 'split_any'),
  (3, 4, 'delete_any'),
  (3, 4, 'modify_any'),
  (3, 4, 'approve_posts'),
  (3, 4, 'post_attachment'),
  (3, 4, 'view_attachments');
# --------------------------------------------------------

#
# Dumping data for table `boards`
#

INSERT INTO {$db_prefix}boards
  (id_board, id_cat, board_order, id_last_msg, id_msg_updated, name, description, num_topics, num_posts, member_groups)
VALUES (1, 1, 1, 1, 1, '{$default_board_name}', '{$default_board_description}', 1, 1, '-1,0,2');
# --------------------------------------------------------

#
# Dumping data for table `categories`
#

INSERT INTO {$db_prefix}categories
VALUES (1, 0, '{$default_category_name}', '', 1);
# --------------------------------------------------------

#
# Dumping data for table `custom_fields`
#

INSERT INTO {$db_prefix}custom_fields
  (`col_name`, `field_name`, `field_desc`, `field_type`, `field_length`, `field_options`, `field_order`, `mask`, `show_reg`, `show_display`, `show_mlist`, `show_profile`, `private`, `active`, `bbc`, `can_search`, `default_value`, `enclose`, `placement`)
VALUES ('cust_skype', 'Skype', 'Your Skype name', 'text', 32, '', 1, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ', 1),
  ('cust_loca', 'Location', 'Geographic location.', 'text', 50, '', 2, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '', 0);

# --------------------------------------------------------

#
# Dumping data for table `membergroups`
#

INSERT INTO {$db_prefix}membergroups
  (id_group, group_name, description, online_color, icons, group_type)
VALUES (1, '{$default_administrator_group}', '', '#FF0000', '5#iconadmin.png', 1),
  (2, '{$default_global_moderator_group}', '', '#0000FF', '5#icongmod.png', 0),
  (3, '{$default_moderator_group}', '', '', '5#iconmod.png', 0);
# --------------------------------------------------------

#
# Dumping data for table `message_icons`
#

# // @todo i18n
INSERT INTO {$db_prefix}message_icons
  (filename, title, icon_order)
VALUES ('xx', 'Standard', '0'),
  ('thumbup', 'Thumb Up', '1'),
  ('thumbdown', 'Thumb Down', '2'),
  ('exclamation', 'Exclamation point', '3'),
  ('question', 'Question mark', '4'),
  ('lamp', 'Lamp', '5'),
  ('smiley', 'Smiley', '6'),
  ('angry', 'Angry', '7'),
  ('cheesy', 'Cheesy', '8'),
  ('grin', 'Grin', '9'),
  ('sad', 'Sad', '10'),
  ('wink', 'Wink', '11'),
  ('poll', 'Poll', '12');
# --------------------------------------------------------

#
# Dumping data for table `messages`
#

INSERT INTO {$db_prefix}messages
  (id_msg, id_msg_modified, id_topic, id_board, poster_time, subject, poster_name, poster_email, modified_name, body, icon)
VALUES (1, 1, 1, 1, UNIX_TIMESTAMP(), '{$default_topic_subject}', 'StoryBB', 'info@storybb.org', '', '{$default_topic_message}', 'xx');
# --------------------------------------------------------

#
# Dumping data for table `permission_profiles`
#

INSERT INTO {$db_prefix}permission_profiles
  (id_profile, profile_name)
VALUES (1, 'default'), (2, 'no_polls'), (3, 'reply_only'), (4, 'read_only');
# --------------------------------------------------------

#
# Dumping data for table `permissions`
#

INSERT INTO {$db_prefix}permissions
  (id_group, permission)
VALUES (-1, 'search_posts'),
  (-1, 'view_stats'),
  (0, 'view_mlist'),
  (0, 'search_posts'),
  (0, 'profile_view'),
  (0, 'pm_read'),
  (0, 'pm_send'),
  (0, 'pm_draft'),
  (0, 'view_stats'),
  (0, 'who_view'),
  (0, 'profile_identity_own'),
  (0, 'profile_password_own'),
  (0, 'profile_displayed_name_own'),
  (0, 'profile_signature_own'),
  (0, 'profile_website_own'),
  (0, 'profile_forum_own'),
  (0, 'profile_extra_own'),
  (0, 'profile_remove_own'),
  (0, 'profile_upload_avatar'),
  (0, 'profile_remote_avatar'),
  (0, 'mention'),
  (2, 'view_mlist'),
  (2, 'search_posts'),
  (2, 'profile_view'),
  (2, 'pm_read'),
  (2, 'pm_send'),
  (2, 'pm_draft'),
  (2, 'view_stats'),
  (2, 'who_view'),
  (2, 'profile_identity_own'),
  (2, 'profile_password_own'),
  (2, 'profile_displayed_name_own'),
  (2, 'profile_signature_own'),
  (2, 'profile_website_own'),
  (2, 'profile_forum_own'),
  (2, 'profile_extra_own'),
  (2, 'profile_remove_own'),
  (2, 'profile_upload_avatar'),
  (2, 'profile_remote_avatar'),
  (2, 'mention'),
  (2, 'access_mod_center');
# --------------------------------------------------------

#
# Dumping data for table `policy`
#

INSERT INTO {$db_prefix}policy
  (id_policy, policy_type, language, title, description, last_revision)
VALUES
  (1, 1, '{$language}', '{$default_policy_terms}', '{$default_policy_terms_desc}', 1),
  (2, 2, '{$language}', '{$default_policy_privacy}', '{$default_policy_privacy_desc}', 2),
  (3, 3, '{$language}', '{$default_policy_roleplay}', '{$default_policy_roleplay_desc}', 3),
  (4, 4, '{$language}', '{$default_policy_cookies}', '{$default_policy_cookies_desc}', 4);

# --------------------------------------------------------

#
# Dumping data for table `policy_revision`
#

INSERT INTO {$db_prefix}policy_revision
  (id_revision, id_policy, last_change, short_revision_note, revision_text, edit_id_member, edit_member_name)
VALUES
  (1, 1, {$current_time}, '', '{$default_policy_terms_text}', 0, ''),
  (2, 2, {$current_time}, '', '{$default_policy_privacy_text}', 0, ''),
  (3, 3, {$current_time}, '', '{$default_policy_roleplay_text}', 0, ''),
  (4, 4, {$current_time}, '', '{$default_policy_cookies_text}', 0, '');

# --------------------------------------------------------

#
# Dumping data for table `policy_types`
#

INSERT INTO {$db_prefix}policy_types
  (id_policy_type, policy_type, require_acceptance, show_footer, show_reg, show_help)
VALUES
  (1, 'terms', 1, 1, 1, 1),
  (2, 'privacy', 1, 1, 1, 1),
  (3, 'roleplay', 0, 0, 0, 0),
  (4, 'cookies', 0, 0, 0, 1);

# --------------------------------------------------------

#
# Dumping data for table `scheduled_tasks`
#

INSERT INTO {$db_prefix}scheduled_tasks
  (id_task, next_time, time_offset, time_regularity, time_unit, disabled, task, class)
VALUES
  (1, 0, 0, 2, 'h', 0, 'approval_notification', 'StoryBB\\Task\\Schedulable\\ApprovalNotifications'),
  (3, 0, 60, 1, 'd', 0, 'daily_maintenance', 'StoryBB\\Task\\Schedulable\\DailyMaintenance'),
  (5, 0, 0, 1, 'd', 0, 'daily_digest', 'StoryBB\\Task\\Schedulable\\DailyDigest'),
  (6, 0, 0, 1, 'w', 0, 'weekly_digest', 'StoryBB\\Task\\Schedulable\\WeeklyDigest'),
  (7, 0, {$sched_task_offset}, 1, 'd', 0, 'fetchStoryBBfiles', 'StoryBB\\Task\\Schedulable\\FetchStoryBBFiles'),
  (8, 0, 0, 1, 'd', 1, 'birthdayemails', 'StoryBB\\Task\\Schuledable\\BirthdayNotify'),
  (9, 0, 0, 1, 'w', 0, 'weekly_maintenance', 'StoryBB\\Task\\Schedulable\\WeeklyMaintenance'),
  (10, 0, 120, 1, 'd', 1, 'paid_subscriptions', 'StoryBB\\Task\\Schedulable\\UpdatePaidSubs'),
  (11, 0, 120, 1, 'd', 0, 'remove_temp_attachments', 'StoryBB\\Task\\Schedulable\\RemoveTempAttachments'),
  (12, 0, 180, 1, 'd', 0, 'remove_topic_redirect', 'StoryBB\\Task\\Schedulable\\RemoveTopicRedirects'),
  (13, 0, 240, 1, 'd', 0, 'remove_old_drafts', 'StoryBB\\Task\\Schedulable\\RemoveOldDrafts'),
  (14, 0, 300, 1, 'd', 0, 'clean_exports', 'StoryBB\\Task\\Schedulable\\CleanExports'),
  (15, 0, 360, 1, 'd', 0, 'scrub_logs', 'StoryBB\\Task\\Schedulable\\ScrubLogs'),
  (16, 0, 420, 1, 'd', 0, 'remove_unapproved_accts', 'StoryBB\\Task\\Schedulable\\RemoveUnapprovedAccounts');

# --------------------------------------------------------

#
# Dumping data for table `settings`
#

INSERT INTO {$db_prefix}settings
  (variable, value)
VALUES ('sbbVersion', '{$sbb_version}'),
  ('news', '{$default_news}'),
  ('todayMod', '1'),
  ('pollMode', '1'),
  ('attachmentSizeLimit', '128'),
  ('attachmentPostLimit', '192'),
  ('attachmentNumPerPostLimit', '4'),
  ('attachmentDirSizeLimit', '10240'),
  ('attachmentDirFileLimit', '1000'),
  ('attachmentUploadDir', '{$attachdir}'),
  ('attachmentExtensions', 'doc,gif,jpg,mpg,pdf,png,txt,zip'),
  ('attachmentCheckExtensions', '0'),
  ('attachmentShowImages', '1'),
  ('attachmentEnable', '1'),
  ('attachmentThumbnails', '1'),
  ('attachmentThumbWidth', '150'),
  ('attachmentThumbHeight', '150'),
  ('use_subdirectories_for_attachments', '1'),
  ('currentAttachmentUploadDir', 1),
  ('censorIgnoreCase', '1'),
  ('mostOnline', '1'),
  ('mostOnlineToday', '1'),
  ('mostDate', UNIX_TIMESTAMP()),
  ('allow_disableAnnounce', '1'),
  ('timeBetweenPosts', '3'),
  ('timeBetweenPostsBoards', 'ooc'),
  ('trackStats', '1'),
  ('userLanguage', '1'),
  ('topicSummaryPosts', '15'),
  ('enableErrorLogging', '1'),
  ('log_ban_hits', '1'),
  ('max_image_width', '0'),
  ('max_image_height', '0'),
  ('smtp_host', ''),
  ('smtp_port', '25'),
  ('smtp_username', ''),
  ('smtp_password', ''),
  ('mail_type', '0'),
  ('totalMembers', '0'),
  ('totalTopics', '1'),
  ('totalMessages', '1'),
  ('censor_vulgar', ''),
  ('censor_proper', ''),
  ('enablePostHTML', '0'),
  ('theme_allow', '1'),
  ('theme_default', '1'),
  ('theme_guests', '1'),
  ('xmlnews_enable', '1'),
  ('xmlnews_maxlen', '255'),
  ('registration_method', '{$registration_method}'),
  ('remove_unapproved_accounts_days', '7'),
  ('send_validation_onChange', '0'),
  ('send_welcomeEmail', '1'),
  ('allow_editDisplayName', '1'),
  ('allow_hideOnline', '1'),
  ('spamWaitTime', '5'),
  ('pm_spam_settings', '10,5,20'),
  ('reserveWord', '0'),
  ('reserveCase', '1'),
  ('reserveUser', '1'),
  ('reserveName', '1'),
  ('reserveNames', '{$default_reserved_names}'),
  ('autoLinkUrls', '1'),
  ('banLastUpdated', '0'),
  ('smileys_dir', '{$boarddir}/Smileys'),
  ('smileys_url', '{$boardurl}/Smileys'),
  ('custom_avatar_dir', '{$boarddir}/custom_avatar'),
  ('custom_avatar_url', '{$boardurl}/custom_avatar'),
  ('avatar_max_width', '125'),
  ('avatar_max_height', '125'),
  ('avatar_action_too_large', 'option_css_resize'),
  ('avatar_resize_upload', '1'),
  ('avatar_download_png', '1'),
  ('failed_login_threshold', '3'),
  ('oldTopicDays', '120'),
  ('edit_wait_time', '90'),
  ('edit_disable_time', '0'),
  ('allow_guestAccess', '1'),
  ('time_format', '{$default_time_format}'),
  ('number_format', '1234.00'),
  ('enableBBC', '1'),
  ('max_messageLength', '20000'),
  ('signature_settings', '1,300,0,0,0,0,0,0:'),
  ('defaultMaxMessages', '15'),
  ('defaultMaxTopics', '20'),
  ('defaultMaxMembers', '30'),
  ('recycle_enable', '0'),
  ('recycle_board', '0'),
  ('maxMsgID', '1'),
  ('enableAllMessages', '0'),
  ('knownThemes', '1'),
  ('enableThemes', '1'),
  ('who_enabled', '1'),
  ('time_offset', '0'),
  ('lastActive', '15'),
  ('unapprovedMembers', '0'),
  ('databaseSession_enable', '{$databaseSession_enable}'),
  ('databaseSession_loose', '1'),
  ('databaseSession_lifetime', '2880'),
  ('search_cache_size', '50'),
  ('search_results_per_page', '30'),
  ('search_weight_frequency', '30'),
  ('search_weight_age', '25'),
  ('search_weight_length', '20'),
  ('search_weight_subject', '15'),
  ('search_weight_first_message', '10'),
  ('search_max_results', '1200'),
  ('search_floodcontrol_time', '5'),
  ('mail_next_send', '0'),
  ('mail_recent', '0000000000|0'),
  ('settings_updated', '0'),
  ('next_task_time', '1'),
  ('warning_settings', '1,20,0'),
  ('warning_watch', '10'),
  ('warning_moderate', '35'),
  ('warning_mute', '60'),
  ('last_mod_report_action', '0'),
  ('pruningOptions', '30,180,180,180,30'),
  ('modlog_enabled', '1'),
  ('adminlog_enabled', '1'),
  ('cache_enable', '1'),
  ('minimum_age', '16'),
  ('reg_verification', '1'),
  ('visual_verification_type', '3'),
  ('enable_buddylist', '1'),
  ('birthday_email', 'happy_birthday'),
  ('attachment_image_reencode', '1'),
  ('attachment_image_paranoid', '0'),
  ('attachment_thumb_png', '1'),
  ('avatar_reencode', '1'),
  ('avatar_paranoid', '0'),
  ('drafts_post_enabled', '1'),
  ('drafts_pm_enabled', '1'),
  ('drafts_autosave_enabled', '1'),
  ('drafts_show_saved_enabled', '1'),
  ('drafts_keep_days', '7'),
  ('topic_move_any', '0'),
  ('browser_cache', '?beta21'),
  ('mail_limit', '5'),
  ('mail_quantity', '5'),
  ('additional_options_collapsable', '1'),
  ('show_modify', '1'),
  ('show_user_images', '1'),
  ('show_profile_buttons', '1'),
  ('enable_ajax_alerts', '1'),
  ('defaultMaxListItems', '15'),
  ('loginHistoryDays', '30'),
  ('httponlyCookies', '1'),
  ('tfa_mode', '1'),
  ('allow_expire_redirect', '1'),
  ('displayFields', '[{"col_name":"cust_skype","title":"Skype","type":"text","order":"1","bbc":"0","placement":"1","enclose":"<a href=\\"skype:{INPUT}?call\\"><img src=\\"{DEFAULT_IMAGES_URL}\\/skype.png\\" alt=\\"{INPUT}\\" title=\\"{INPUT}\\" \\/><\\/a> ","mlist":"0"},{"col_name":"cust_loca","title":"Location","type":"text","order":"2","bbc":"0","placement":"0","enclose":"","mlist":"0"}]'),
  ('minimize_files', '1'),
  ('enable_mentions', '1'),
  ('retention_policy_standard', 90),
  ('retention_policy_sensitive', 15);

# --------------------------------------------------------

#
# Dumping data for table `smileys`
#

INSERT INTO {$db_prefix}smileys
  (code, filename, description, smiley_order, hidden)
VALUES (':)', 'smiley.gif', '{$default_smiley_smiley}', 0, 0),
  (';)', 'wink.gif', '{$default_wink_smiley}', 1, 0),
  (':D', 'cheesy.gif', '{$default_cheesy_smiley}', 2, 0),
  (';D', 'grin.gif', '{$default_grin_smiley}', 3, 0),
  ('>:(', 'angry.gif', '{$default_angry_smiley}', 4, 0),
  (':(', 'sad.gif', '{$default_sad_smiley}', 5, 0),
  (':o', 'shocked.gif', '{$default_shocked_smiley}', 6, 0),
  ('8)', 'cool.gif', '{$default_cool_smiley}', 7, 0),
  ('???', 'huh.gif', '{$default_huh_smiley}', 8, 0),
  ('::)', 'rolleyes.gif', '{$default_roll_eyes_smiley}', 9, 0),
  (':P', 'tongue.gif', '{$default_tongue_smiley}', 10, 0),
  (':-[', 'embarrassed.gif', '{$default_embarrassed_smiley}', 11, 0),
  (':-X', 'lipsrsealed.gif', '{$default_lips_sealed_smiley}', 12, 0),
  (':-\\', 'undecided.gif', '{$default_undecided_smiley}', 13, 0),
  (':-*', 'kiss.gif', '{$default_kiss_smiley}', 14, 0),
  (':''(', 'cry.gif', '{$default_cry_smiley}', 15, 0),
  ('>:D', 'evil.gif', '{$default_evil_smiley}', 16, 1),
  ('^-^', 'azn.gif', '{$default_azn_smiley}', 17, 1),
  ('O0', 'afro.gif', '{$default_afro_smiley}', 18, 1),
  (':))', 'laugh.gif', '{$default_laugh_smiley}', 19, 1),
  ('C:-)', 'police.gif', '{$default_police_smiley}', 20, 1),
  ('O:-)', 'angel.gif', '{$default_angel_smiley}', 21, 1);
# --------------------------------------------------------

#
# Dumping data for table `themes`
#

INSERT INTO {$db_prefix}themes
  (id_theme, variable, value)
VALUES (1, 'name', '{$default_theme_name}'),
  (1, 'theme_url', '{$boardurl}/Themes/default'),
  (1, 'images_url', '{$boardurl}/Themes/default/images'),
  (1, 'theme_dir', '{$boarddir}/Themes/default'),
  (1, 'show_latest_member', '1'),
  (1, 'show_newsfader', '0'),
  (1, 'number_recent_posts', '0'),
  (1, 'show_stats_index', '1'),
  (1, 'newsfader_time', '3000'),
  (1, 'enable_news', '1'),
  (1, 'drafts_show_saved_enabled', '1'),
  (1, 'sub_boards_columns', '2');

INSERT INTO {$db_prefix}themes
  (id_member, id_theme, variable, value)
VALUES (-1, 1, 'posts_apply_ignore_list', '1'),
  (-1, 1, 'return_to_post', '1');
# --------------------------------------------------------

#
# Dumping data for table `topics`
#

INSERT INTO {$db_prefix}topics
  (id_topic, id_board, id_first_msg, id_last_msg, id_member_started, id_member_updated)
VALUES (1, 1, 1, 1, 0, 0);
# --------------------------------------------------------

#
# Dumping data for table `user_alerts_prefs`
#

INSERT INTO {$db_prefix}user_alerts_prefs
  (id_member, alert_pref, alert_value)
VALUES (0, 'member_group_request', 1),
  (0, 'member_register', 1),
  (0, 'msg_like', 1),
  (0, 'msg_report', 1),
  (0, 'msg_report_reply', 1),
  (0, 'unapproved_reply', 3),
  (0, 'topic_notify', 1),
  (0, 'board_notify', 1),
  (0, 'msg_mention', 1),
  (0, 'msg_quote', 1),
  (0, 'pm_new', 1),
  (0, 'pm_reply', 1),
  (0, 'groupr_approved', 3),
  (0, 'groupr_rejected', 3),
  (0, 'member_report_reply', 3),
  (0, 'birthday', 2),
  (0, '_announcements', 2),
  (0, 'member_report', 3),
  (0, 'unapproved_post', 1),
  (0, 'buddy_request', 1),
  (0, 'warn_any', 1),
  (0, 'request_group', 1),
  (0, 'approval_notify', 2);
# --------------------------------------------------------
