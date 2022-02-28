<?php

/**
 * This file contains language strings for the generic settings pages.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

$txt['modSettings_desc'] = 'This page allows you to change the settings of features and basic options in your forum. Please see the <a href="%4$s?action=admin;area=theme;sa=list;th=%1$s;%3$s=%2$s">theme settings</a> for more options. Click the help icons for more information about a setting.';

$txt['pollMode'] = 'Poll mode';
$txt['disable_polls'] = 'Disable polls';
$txt['enable_polls'] = 'Enable polls';
$txt['polls_as_topics'] = 'Show existing polls as topics';
$txt['userLanguage'] = 'Enable user-selectable language support';
$txt['allow_hideOnline'] = 'Allow non-administrators to hide their online status';
$txt['enable_buddylist'] = 'Enable buddy/ignore lists';
$txt['time_format'] = 'Default time format';
$txt['setting_time_offset'] = 'Overall time offset';
$txt['setting_time_offset_note'] = '(added to the member specific option)';
$txt['setting_default_timezone'] = 'Server timezone';
$txt['setting_timezone_priority_countries'] = 'Show time zones from these countries first';
$txt['setting_timezone_priority_countries_note'] = 'A comma separated list of two character ISO country codes.';
$txt['failed_login_threshold'] = 'Failed login threshold';
$txt['loginHistoryDays'] = 'Days to keep login history';
$txt['lastActive'] = 'User online time threshold';
$txt['trackStats'] = 'Track daily statistics';
$txt['hitStats'] = 'Track daily page views (must have stats enabled)';
$txt['debug_templates'] = 'Enable template debug mode';
$txt['error_log_desc'] = 'The error log, if enabled, will log every error encountered by users using your forum. This can be an invaluable aid to identifying forum problems.';
$txt['enableErrorLogging'] = 'Enable error logging';
$txt['enableErrorQueryLogging'] = 'Include database query in the error log';
$txt['pruningOptions'] = 'Enable pruning of log entries';
$txt['pruneErrorLog'] = 'Remove error log entries older than';
$txt['pruneModLog'] = 'Remove moderation log entries older than';
$txt['pruneBanLog'] = 'Remove ban hit log entries older than';
$txt['pruneReportLog'] = 'Remove report to moderator log entries older than';
$txt['pruneScheduledTaskLog'] = 'Remove scheduled task log entries older than';
$txt['retention_policy_standard'] = 'Remove privacy-related items in logs after';
$txt['retention_policy_sensitive'] = 'Remove more sensitive items in logs after';
$txt['zero_means_zero'] = 'Setting this to 0 means purging these items after only a matter of hours.';
$txt['securityDisable'] = 'Disable administration security';
$txt['send_validation_onChange'] = 'Require reactivation after email change';
$txt['timeBetweenPosts'] = 'Display "x days later" banner between posts after how many days';
$txt['timeBetweenPostsBoards'] = 'When displaying "x days later" banner, which boards should this apply to?';
$txt['disallow_sendBody'] = 'Don\'t allow post text in notifications';
$txt['enable_ajax_alerts'] = 'Allow AJAX Desktop Notifications for Alerts';
$txt['analytics_google_id'] = 'Google Analytics ID';
$txt['analytics_google_id_sub'] = 'You can get this from your GA account admin, and it looks something like UA-000000-2.';
$txt['minimize_css'] = 'Minimize CSS (cached)';
$txt['minimize_js'] = 'Minimize JS (cached)';
$txt['max_pm_recipients'] = 'Maximum number of recipients allowed in a personal message';
$txt['max_pm_recipients_note'] = '(0 for no limit, admins are exempt)';
$txt['pm_posts_verification'] = 'Post count under which users must pass verification when sending personal messages';
$txt['pm_posts_verification_note'] = '(0 for no limit, admins are exempt)';
$txt['pm_posts_per_hour'] = 'Number of personal messages a user may send in an hour';
$txt['pm_posts_per_hour_note'] = '(0 for no limit, moderators are exempt)';
$txt['contiguous_page_display'] = 'Contiguous pages to display';
$txt['to_display'] = 'to display';
$txt['todayMod'] = 'Enable shorthand date display';
$txt['today_disabled'] = 'Disabled';
$txt['today_only'] = 'Only Today';
$txt['yesterday_today'] = 'Today &amp; Yesterday';
$txt['defaultMaxMembers'] = 'Members per page in member list';
$txt['who_enabled'] = 'Enable who\'s online list';
$txt['meta_keywords'] = 'Meta keywords associated with forum';
$txt['meta_keywords_note'] = 'For search engines. Leave blank for default.';
$txt['meta_description'] = 'Site level meta description';
$txt['settings_error'] = 'Warning: Updating of Settings.php failed, the settings cannot be saved.';
$txt['image_proxy_enabled'] = 'Enable Image Proxy';
$txt['image_proxy_secret'] = 'Image Proxy Secret';
$txt['image_proxy_maxsize'] = 'Maximum file size of images to cache (in KB)';
$txt['force_ssl'] = 'Forum SSL mode';
$txt['force_ssl_off'] = 'Disable SSL';
$txt['force_ssl_auth'] = 'Enable SSL for Authentication (Login and Register)';
$txt['force_ssl_complete'] = 'Force SSL throughout the forum';

// Like settings.
$txt['enable_likes'] = 'Enable likes';

// Mention settings.
$txt['enable_mentions'] = 'Enable mentions';

$txt['caching_information'] = 'StoryBB supports caching through the following: APCu, Memcache, Redis, SQLite, and Zend Platform/Performance Suite, as well as an internal file-based cache.';
$txt['detected_no_caching'] = '<strong class="alert">StoryBB has not been able to detect a compatible accelerator on your server. File based caching can be used instead.</strong>';
$txt['detected_accelerators'] = '<strong class="success">StoryBB has detected the following accelerators: %1$s</strong>';


$txt['cache_enable'] = 'Caching Level';
$txt['cache_off'] = 'No caching';
$txt['cache_level1'] = 'Level 1 Caching (Recommended)';
$txt['cache_level2'] = 'Level 2 Caching';
$txt['cache_level3'] = 'Level 3 Caching (Not Recommended)';
$txt['cache_accelerator'] = 'Caching Accelerator';
$txt['sbb_cache'] = 'StoryBB file based caching';
$txt['sqlite_cache'] = 'SQLite3 database based caching';
$txt['cachedir_sqlite'] = 'SQLite3 database cache directory';
$txt['memcache_cache'] = 'Memcache';
$txt['memcached_cache'] = 'Memcached';
$txt['zend_cache'] = 'Zend Platform/Performance Suite';
$txt['cache_sbb_settings'] = 'StoryBB file based caching settings';
$txt['cache_sqlite_settings'] = 'SQLite3 database caching settings';
$txt['cache_memcache_settings'] = 'Memcache(d) settings';
$txt['cache_memcache_servers'] = 'Memcache(d) servers';
$txt['cache_memcache_servers_subtext'] = 'Example: 127.0.0.1:11211,127.0.0.2';
$txt['cache_redis_settings'] = 'Redis settings';
$txt['cache_redis_server'] = 'Redis server';
$txt['cache_redis_server_subtext'] = 'Example: 127.0.0.1:6379 or 127.0.0.1:6379:password if a password is needed';

$txt['setting_password_strength'] = 'Required strength for user passwords';
$txt['setting_password_strength_low'] = 'Low - 4 character minimum';
$txt['setting_password_strength_medium'] = 'Medium - cannot contain username';
$txt['setting_password_strength_high'] = 'High - mixture of different characters';

$txt['antispam_Settings'] = 'Anti-Spam Verification';
$txt['antispam_Settings_desc'] = 'This section allows you to setup verification checks to ensure the user is a human (and not a bot), and tweak how and where these apply.';
$txt['setting_reg_verification'] = 'Require verification on registration page';
$txt['posts_require_captcha'] = 'Post count under which users must pass verification to make a post';
$txt['posts_require_captcha_desc'] = '(0 for no limit, moderators are exempt)';
$txt['search_enable_captcha'] = 'Require verification on all guest searches';
$txt['setting_guests_require_captcha'] = 'Guests must pass verification when making a post';
$txt['setting_guests_require_captcha_desc'] = '(Automatically set if you specify a minimum post count below)';
$txt['question_not_defined'] = 'You need to add a question and answer for your forum\'s default language (%1$s) otherwise users will not be able to fill in a CAPTCHA, meaning no registration.';

$txt['configure_verification_means'] = 'Configure Verification Methods';
$txt['setting_qa_verification_number'] = 'Number of verification questions user must answer';
$txt['setting_qa_verification_number_desc'] = '(0 to disable; questions are set below)';
$txt['configure_verification_means_desc'] = '<span class="smalltext">Below you can set which anti-spam features you wish to have enabled whenever a user needs to verify they are a human. Note that the user will have to pass <em>all</em> verification so if you enable both a verification image and a question/answer test they need to complete both to proceed.</span>';
$txt['setting_visual_verification_type'] = 'Visual verification image to display';
$txt['setting_visual_verification_type_desc'] = 'The more complex the image the harder it is for bots to bypass';
$txt['setting_image_verification_off'] = 'None';
$txt['setting_image_verification_vsimple'] = 'Very Simple - Plain text on image';
$txt['setting_image_verification_simple'] = 'Simple - Overlapping colored letters, no noise';
$txt['setting_image_verification_medium'] = 'Medium - Overlapping colored letters, with noise/lines';
$txt['setting_image_verification_high'] = 'High - Angled letters, considerable noise/lines';
$txt['setting_image_verification_extreme'] = 'Extreme - Angled letters, noise, lines and blocks';
$txt['setting_image_verification_sample'] = 'Sample';

// reCAPTCHA
$txt['recaptcha_configure'] = 'reCAPTCHA Verification System';
$txt['recaptcha_configure_desc'] = 'Configure the reCAPTCHA Verification System. Don\'t have a key for reCAPTCHA? <a href="https://www.google.com/recaptcha/admin"> Get your reCAPTCHA key here</a>.';
$txt['recaptcha_enabled'] = 'Use reCAPTCHA Verification System';
$txt['recaptcha_enable_desc'] = 'This augments the built-in visual verification';
$txt['recaptcha_theme'] = 'reCAPTCHA Theme';
$txt['recaptcha_theme_light'] = 'Light';
$txt['recaptcha_theme_dark'] = 'Dark';
$txt['recaptcha_site_key'] = 'Site Key';
$txt['recaptcha_site_key_desc'] = 'This will be set in the HTML code your site serves to users.';
$txt['recaptcha_secret_key'] = 'Secret Key';
$txt['recaptcha_secret_key_desc'] = 'This is for communication between your site and Google. Be sure to keep it a secret.';
$txt['recaptcha_no_key_question'] = 'Don\'t have a key for reCAPTCHA?';
$txt['recaptcha_get_key'] = 'Get your reCAPTCHA key here.';

$txt['setup_verification_questions'] = 'Verification Questions';
$txt['setup_verification_questions_desc'] = '<span class="smalltext">If you want users to answer verification questions in order to stop spam bots, you should setup a number of questions in the table below. You should choose questions which relate to the subject of your forum. Genuine users will be able to answer these questions, while spam bots will not. Answers are not case sensitive. You may use BBC in the questions for formatting. To remove a question simply delete the contents of that line.</span>';
$txt['setup_verification_question'] = 'Question';
$txt['setup_verification_answer'] = 'Answer';
$txt['setup_verification_add_more'] = 'Add another question';
$txt['setup_verification_add_answer'] = 'Add another answer';

$txt['moderation_settings'] = 'Moderation Settings';
$txt['setting_warning_enable'] = 'Enable User Warning System';
$txt['setting_warning_watch'] = 'Warning level for user watch';
$txt['setting_warning_watch_note'] = 'The user warning level after which a user watch is put in place.';
$txt['setting_warning_moderate'] = 'Warning level for post moderation';
$txt['setting_warning_moderate_note'] = 'The user warning level after which a user has all posts moderated.';
$txt['setting_warning_mute'] = 'Warning level for user muting';
$txt['setting_warning_mute_note'] = 'The user warning level after which a user cannot post any further.';
$txt['setting_user_limit'] = 'Maximum user warning points per day';
$txt['setting_user_limit_note'] = 'This value is the maximum amount of warning points a single moderator can assign to a user in a 24 hour period - 0 for no limit.';
$txt['setting_warning_decrement'] = 'Warning points that are decreased every 24 hours';
$txt['setting_warning_decrement_note'] = 'Only applies to users not warned within last 24 hours.';
$txt['setting_warning_show'] = 'Users who can see warning status';
$txt['setting_warning_show_note'] = 'Determines who can see the warning level of users on the forum.';
$txt['setting_warning_show_mods'] = 'Moderators Only';
$txt['setting_warning_show_user'] = 'Moderators and Warned Users';
$txt['setting_warning_show_all'] = 'All Users';

$txt['signature_settings'] = 'Signature Settings';
$txt['signature_settings_desc'] = 'Use the settings on this page to decide how member signatures should be treated in StoryBB.';
$txt['signature_settings_warning'] = 'Note that settings are not applied to existing signatures by default. <a href="%3$s?action=admin;area=featuresettings;sa=sig;apply;%2$s=%1$s">Run the process now</a>';
$txt['signature_settings_applied'] = 'The updated rules have been applied to the existing signatures.';
$txt['signature_enable'] = 'Enable signatures';
$txt['signature_max_length'] = 'Maximum allowed characters';
$txt['signature_max_lines'] = 'Maximum amount of lines';
$txt['signature_max_images'] = 'Maximum image count';
$txt['signature_max_images_note'] = '(0 for no max - excludes smileys)';
$txt['signature_allow_smileys'] = 'Allow smileys in signatures';
$txt['signature_max_smileys'] = 'Maximum smiley count';
$txt['signature_max_image_width'] = 'Maximum width of signature images (pixels)';
$txt['signature_max_image_height'] = 'Maximum height of signature images (pixels)';
$txt['signature_max_font_size'] = 'Maximum font size allowed in signatures (pixels)';
$txt['signature_bbc'] = 'Enabled BBC tags';

$txt['custom_profile_title'] = 'Custom Profile Fields';
$txt['custom_profile_desc'] = 'From this page you can create your own custom profile fields that fit in with your own forums requirements';
$txt['custom_profile_active'] = 'Active';
$txt['custom_profile_fieldname'] = 'Field Name';
$txt['custom_profile_fieldtype'] = 'Field Type';
$txt['custom_profile_fieldorder'] = 'Field Order';
$txt['custom_profile_make_new'] = 'New Field';
$txt['custom_profile_none_ic'] = 'You have not created any custom profile fields for characters yet.';
$txt['custom_profile_none_ooc'] = 'You have not created any custom profile fields for accounts yet.';
$txt['custom_profile_icon'] = 'Icon';
$txt['custom_profile_title_ic_fields'] = 'Custom Fields - Characters';
$txt['custom_profile_title_ooc_fields'] = 'Custom Fields - Accounts';

$txt['custom_profile_type_text'] = 'Text';
$txt['custom_profile_type_textarea'] = 'Large Text';
$txt['custom_profile_type_select'] = 'Select Box';
$txt['custom_profile_type_radio'] = 'Radio Button';
$txt['custom_profile_type_check'] = 'Checkbox';

$txt['custom_add_title_ic'] = 'Add Profile Field - Character';
$txt['custom_add_title_ooc'] = 'Add Profile Field - Account';
$txt['custom_edit_title'] = 'Edit Profile Field';
$txt['custom_edit_general'] = 'Display Settings';
$txt['custom_edit_input'] = 'Input Settings';
$txt['custom_edit_advanced'] = 'Advanced Settings';
$txt['custom_edit_name'] = 'Name';
$txt['custom_edit_desc'] = 'Description';
$txt['custom_edit_profile'] = 'Profile Section';
$txt['custom_edit_profile_desc'] = 'Section of profile this is edited in.';
$txt['custom_edit_profile_none'] = 'None';
$txt['custom_edit_profile_char'] = 'Character profile';
$txt['custom_edit_registration'] = 'Show on Registration';
$txt['custom_edit_registration_disable'] = 'No';
$txt['custom_edit_registration_allow'] = 'Yes';
$txt['custom_edit_registration_require'] = 'Yes, and require entry';
$txt['custom_edit_display'] = 'Show on Topic View';
$txt['custom_edit_picktype'] = 'Field Type';

$txt['custom_edit_max_length'] = 'Maximum Length';
$txt['custom_edit_max_length_desc'] = '(0 for no limit)';
$txt['custom_edit_dimension'] = 'Dimensions';
$txt['custom_edit_dimension_row'] = 'Rows';
$txt['custom_edit_dimension_col'] = 'Columns';
$txt['custom_edit_bbc'] = 'Allow BBC';
$txt['custom_edit_options'] = 'Options';
$txt['custom_edit_options_desc'] = 'Leave option box blank to remove. Radio button selects default option.';
$txt['custom_edit_options_more'] = 'More';
$txt['custom_edit_default'] = 'Default State';
$txt['custom_edit_active'] = 'Active';
$txt['custom_edit_active_desc'] = 'If not selected this field will not be shown to anyone.';
$txt['custom_edit_privacy'] = 'Privacy';
$txt['custom_edit_privacy_desc'] = 'Who can see and edit this field.';
$txt['custom_edit_privacy_all'] = 'Users can see this field; owner can edit it';
$txt['custom_edit_privacy_see'] = 'Users can see this field; only admins can edit it';
$txt['custom_edit_privacy_owner'] = 'Users cannot see this field; owner and admins can edit it.';
$txt['custom_edit_privacy_none'] = 'This field is only visible to admins';
$txt['custom_edit_can_search'] = 'Searchable';
$txt['custom_edit_can_search_desc'] = 'Can this field be searched from the members list.';
$txt['custom_edit_mask'] = 'Input Mask';
$txt['custom_edit_mask_desc'] = 'For text fields an input mask can be selected to validate the data.';
$txt['custom_edit_mask_email'] = 'Valid Email';
$txt['custom_edit_mask_number'] = 'Numeric';
$txt['custom_edit_mask_nohtml'] = 'No HTML';
$txt['custom_edit_mask_regex'] = 'Regex (Advanced)';
$txt['custom_edit_enclose'] = 'Show Enclosed Within Text (Optional)';
$txt['custom_edit_enclose_desc'] = 'We <strong>strongly</strong> recommend to use an input mask to validate the input supplied by the user.';

$txt['custom_edit_order_move'] = 'Move ';
$txt['custom_edit_order_up'] = 'Up';
$txt['custom_edit_order_down'] = 'Down';
$txt['custom_edit_placement'] = 'Choose Placement';
$txt['custom_profile_placement'] = 'Placement';
$txt['custom_profile_placement_standard'] = 'Standard (with title)';
$txt['custom_profile_placement_icons'] = 'With Icons';
$txt['custom_profile_placement_above_signature'] = 'Above Signature';
$txt['custom_profile_placement_below_signature'] = 'Below Signature';
$txt['custom_profile_placement_below_avatar'] = 'Below Avatar';
$txt['custom_profile_placement_above_member'] = 'Above Username';
$txt['custom_profile_placement_bottom_poster'] = 'Bottom poster info';
$txt['custom_profile_placement_after_membergroup'] = 'After Membergroup';

// Use numeric entities in the string below!
$txt['custom_edit_delete_sure'] = 'Are you sure you wish to delete this field - all related user data will be lost!';

$txt['standard_profile_title'] = 'Standard Profile Fields';
$txt['standard_profile_field'] = 'Field';
$txt['standard_profile_field_timezone'] = 'Timezone';

$txt['languages_lang_name'] = 'Language Name';
$txt['languages_locale'] = 'Locale';
$txt['languages_available'] = 'Available';
$txt['languages_default'] = 'Default';
$txt['languages_users'] = 'Users';
$txt['languages_right_to_left'] = 'Right-to-Left';
$txt['language_settings_writable'] = 'Warning: Settings.php is not writable so the default language setting cannot be saved.';
$txt['edit_languages'] = 'Edit Languages';
$txt['lang_file_not_writable'] = '<strong>Warning:</strong> The primary language file (%1$s) is not writable. You must make this writable before you can make any changes.';
$txt['lang_entries_not_writable'] = '<strong>Warning:</strong> The language file you wish to edit (%1$s) is not writable. You must make this writable before you can make any changes.';
$txt['languages_ltr'] = 'Right to Left';
$txt['language_master_value'] = 'Master value';
$txt['language_current_value'] = 'Current value';
$txt['language_master_values'] = 'Master values';
$txt['language_current_values'] = 'Current values';
$txt['language_new_value'] = 'New value';
$txt['language_new_values'] = 'New values';
$txt['language_add_new_entry'] = 'Add new entry';

$txt['edit_language_entries_primary'] = 'You can select which set of language entries to review and edit below.';
$txt['edit_language_entries'] = 'Edit Language Entries';
$txt['edit_language_entries_file'] = 'Select entries to edit';
$txt['languages_rtl'] = 'Enable &quot;Right to Left&quot; Mode';
$txt['default_language_sections'] = 'Default Language Sections';
$txt['language_general_files'] = 'General Language Sections';
$txt['language_admin_files'] = 'Admin Language Sections';
$txt['language_other_files'] = 'Other Language Content';
$txt['configured_elsewhere'] = 'Some language content is configured elsewhere:';
$txt['all_site_policies'] = 'All site policies';

$txt['edit_email_templates'] = 'Edit Email Templates';
$txt['email_subject'] = 'Subject:';
$txt['email_body'] = 'Body:';
$txt['email_template_group_registration'] = 'Registration Emails';
$txt['email_template_group_registration_admin'] = 'Registration Emails (Admin)';
$txt['email_template_group_personal_messages'] = 'Personal Messages';
$txt['email_template_group_account_changes'] = 'Account Changes';
$txt['email_template_group_content_notifications'] = 'Content Notifications';
$txt['email_template_group_mod_notifications'] = 'Moderation Notifications';
$txt['email_template_group_reported_content'] = 'Reported Content';
$txt['email_template_group_group_membership'] = 'Group Membership';
$txt['email_template_group_paid_subs'] = 'Paid Subscriptions';
$txt['email_template_group_general'] = 'General Emails';

$txt['email_template_desc_register_activate'] = 'Sent when a user registers and needs to activate their account.';
$txt['email_template_desc_register_immediate'] = 'Sent when a user registers and is immediately approved.';
$txt['email_template_desc_register_pending'] = 'Sent to a user when they have registered and the registration is waiting for admin approval.';
$txt['email_template_desc_resend_activate_message'] = 'Sent to a user who tries to re-register but is already registered and awaiting email activation.';
$txt['email_template_desc_resend_pending_message'] = 'Sent to a user who tries to re-register but is already registered and awaiting admin approval.';

$txt['email_template_desc_admin_approve_accept'] = 'Sent when an admin approves a new member (when admin approval is enabled)';
$txt['email_template_desc_admin_approve_activation'] = 'Sent when an admin approves a new member, but selects "approve with activation"';
$txt['email_template_desc_admin_approve_reject'] = 'Sent when an admin rejects a new member from joining (when admin approval is enabled)';
$txt['email_template_desc_admin_approve_delete'] = 'Sent when an admin deletes a new member (when admin approval is enabled)';
$txt['email_template_desc_admin_approve_remind'] = 'Sent when an admin sends a reminder to a new user to activate their account via welcome email.';
$txt['email_template_desc_admin_register_activate'] = 'Sent when an admin creates a new account in registration, and wants the user to activate their account.';
$txt['email_template_desc_admin_register_immediate'] = 'Sent when an admin creates a new account in the registration area.';
$txt['email_template_desc_admin_notify'] = 'Sent to admins when a new member joins (without admin approval)';
$txt['email_template_desc_admin_notify_approval'] = 'Sent to admins when a new member joins and is in need of approval.';

$txt['email_template_desc_request_membership'] = 'Sent to admins and group moderators when a new group request is made, for their review.';
$txt['email_template_desc_mc_group_approve'] = 'Sent to users to tell them when a group request they made was approved.';
$txt['email_template_desc_mc_group_reject'] = 'Sent to users to tell them when a group request they made was rejected.';
$txt['email_template_desc_mc_group_reject_reason'] = 'Sent to users to tell them when a group request they made was rejected, and the reason why.';

$txt['email_template_desc_new_pm'] = 'Sent to users when they receive a PM and the message body will not be included in the message.';
$txt['email_template_desc_new_pm_body'] = 'Sent to users when they receive a PM and the message body will be included in the message.';
$txt['email_template_desc_new_pm_tolist'] = 'Sent to users when they and someone else receive a PM and the message body will not be included in the message.';
$txt['email_template_desc_new_pm_body_tolist'] = 'Sent to users when they and someone else receive a PM and the message body will be included in the message.';

$txt['email_template_desc_notify_boards'] = 'Sent to users requesting notifications on new topics in a board. (Every new topic, does not include message body.)';
$txt['email_template_desc_notify_boards_body'] = 'Sent to users requesting notifications on new topics in a board. (Every new topic, does include message body.)';
$txt['email_template_desc_notify_boards_once'] = 'Sent to users requesting notifications on new topics in a board. (First new topic, no more notifications until they visit the board, does not include message body.)';
$txt['email_template_desc_notify_boards_once_body'] = 'Sent to users requesting notifications on new topics in a board. (First new topic, no more notifications until they visit the board, does include message body.)';
$txt['email_template_desc_notification_reply'] = 'Sent to users requesting notifications on new replies to a topic. (Every reply, does not include message body.)';
$txt['email_template_desc_notification_reply_body'] = 'Sent to users requesting notifications on new replies to a topic. (Every reply, does include message body.)';
$txt['email_template_desc_notification_reply_once'] = 'Sent to users requesting notifications on new replies to a topoic. (Sent on first reply, no more notifications until the thread is read. Does not include message body.)';
$txt['email_template_desc_notification_reply_body_once'] = 'Sent to users requesting notifications on new replies to a topoic. (Sent on first reply, no more notifications until the thread is read. Does include message body.)';
$txt['email_template_desc_msg_quote'] = 'Sent to users requesting notification emails when a message of theirs is quoted.';
$txt['email_template_desc_msg_mention'] = 'Sent to users requesting notification emails when they are mentioned in a message.';

$txt['email_template_desc_notification_sticky'] = 'Sent to users when a topic they are watching gets stickied.';
$txt['email_template_desc_notification_lock'] = 'Sent to users when a topic they are watching gets locked.';
$txt['email_template_desc_notification_unlock'] = 'Sent to users when a topic they are watching gets unlocked.';
$txt['email_template_desc_notification_remove'] = 'Sent to users when a topic they are watching gets removed.';
$txt['email_template_desc_notification_move'] = 'Sent to users when a topic they are watching gets moved.';
$txt['email_template_desc_notification_merge'] = 'Sent to users when a topic they are watching gets merged.';
$txt['email_template_desc_notification_split'] = 'Sent to users when a topic they are watching gets split.';
$txt['email_template_desc_alert_unapproved_reply'] = 'Sent to the topic starter of an unapproved topic when there is a reply to it (e.g. moderators)';
$txt['email_template_desc_alert_unapproved_post'] = 'Sent to moderators when a new post has been made that is not approved.';
$txt['email_template_desc_alert_unapproved_topic'] = 'Sent to moderators when a new topic has been made that is not approved.';
$txt['email_template_desc_scheduled_approval'] = 'Sent to moderators regularly listing all the unapproved content for them to review.';

$txt['email_template_desc_report_to_moderator'] = 'Sent to moderators when a post is reported.';
$txt['email_template_desc_reply_to_moderator'] = 'Sent to moderators after a comment is made on a previously-reported post.';
$txt['email_template_desc_report_member_profile'] = 'Sent to moderators when a profile is reported.';
$txt['email_template_desc_reply_to_member_report'] = 'Sent to moderators after a comment is made on a previously-reported profile.';

$txt['email_template_desc_activate_reactivate'] = 'Sent to a user when they change their email address and it needs activation.';
$txt['email_template_desc_forgot_password'] = 'Sent to a user when requesting a new password through the forgot-password reminder.';
$txt['email_template_desc_change_password'] = 'Sent to a user when the admin resets their username/password through the profile area.';

$txt['email_template_desc_paid_subscription_new'] = 'Sent to admins when a new paid subscription is taken out.';
$txt['email_template_desc_paid_subscription_reminder'] = 'Sent to users who have a paid subscription which will expire in the next 24 hours.';
$txt['email_template_desc_paid_subscription_refund'] = 'Sent to admins when a paid subsciption refund is processed by the payment provider.';
$txt['email_template_desc_paid_subscription_error'] = 'Sent to admins when a paid subscription error occurs with the payment provider.';

$txt['email_template_desc_contact_form_response'] = 'Sent to people when admins respond to messages from the contact form.';
$txt['email_template_desc_admin_attachments_full'] = 'Sent to admins when the attachments folder gets close to the maximum defined size.';

$txt['lang_file_desc_General'] = 'General strings';
$txt['lang_file_desc_ManagePaid'] = 'Paid subscriptions';
$txt['lang_file_desc_Modlog'] = 'Moderation log';
$txt['lang_file_desc_Who'] = 'Who\'s Online';

$txt['languages_download'] = 'Download Language Pack';
$txt['languages_download_note'] = 'This page lists all the files that are contained within the language pack and some useful information about each one. All files that have their associated check box marked will be copied.';
$txt['languages_download_info'] = '<strong>Note:</strong>
	<ul class="normallist">
		<li>Files which have the status &quot;Not Writable&quot; means StoryBB will not be able to copy this file to the directory at the present and you must make the destination writable either using an FTP client or by filling in your details at the bottom of the page.</li>
		<li>The Version information for a file displays the last StoryBB version which it was updated for. If it is indicated in green then this is a newer version than you have at current. If amber this indicates it\'s the same version number as at current, red indicates you have a newer version installed than contained in the pack.</li>
		<li>Where a file already exists on your forum the &quot;Already Exists&quot; column will have one of two values. &quot;Identical&quot; indicates that the file already exists in an identical form and need not be overwritten. &quot;Different&quot; means that the contents vary in some way and overwriting is probably the optimum solution.</li>
	</ul>';

$txt['languages_download_main_files'] = 'Primary Files';
$txt['languages_download_filename'] = 'File Name';
$txt['languages_download_dest'] = 'Destination';
$txt['languages_download_writable'] = 'Writable';
$txt['languages_download_version'] = 'Version';
$txt['languages_download_older'] = 'You have a newer version of this file installed. Overwriting is not recommended.';
$txt['languages_download_exists'] = 'Already Exists';
$txt['languages_download_exists_same'] = 'Identical';
$txt['languages_download_exists_different'] = 'Different';
$txt['languages_download_copy'] = 'Copy';
$txt['languages_download_not_chmod'] = 'You cannot proceed with the installation until all files selected to be copied are writable.';
$txt['languages_download_illegal_paths'] = 'Package contains illegal paths - please contact StoryBB';
$txt['languages_download_complete'] = 'Installation Complete';
$txt['languages_download_complete_desc'] = 'Language pack installed successfully. Please click <a href="%1$s">here</a> to return to the languages page';
$txt['languages_delete_confirm'] = 'Are you sure you want to delete this language?';

$txt['setting_frame_security'] = 'Frame Security Options';
$txt['setting_frame_security_SAMEORIGIN'] = 'Allow Same Origin';
$txt['setting_frame_security_DENY'] = 'Deny all frames';
$txt['setting_frame_security_DISABLE'] = 'Disabled';

$txt['setting_proxy_ip_header'] = 'Reverse Proxy IP Header';
$txt['setting_proxy_ip_header_disabled'] = 'Do not allow any Proxy IP Headers';
$txt['setting_proxy_ip_header_autodetect'] = 'Auto-detect Proxy IP header';
$txt['setting_proxy_ip_servers'] = 'Reverse Proxy Servers IPs';

$txt['select_boards_from_list'] = 'Select boards which apply';

$txt['defaultMaxListItems'] = 'Maximum number of items per page in lists';
