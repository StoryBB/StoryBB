<?php

/**
 * This file contains language strings for the manage-members area.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

$txt['groups'] = 'Groups';
$txt['viewing_groups'] = 'Viewing Membergroups';

$txt['membergroups_title'] = 'Manage Membergroups';
$txt['membergroups_description'] = 'Membergroups are groups of members that have similar permission settings, appearance, or access rights. Some membergroups are based on the amount of posts a user has made. You can assign someone to a membergroup by selecting their profile and changing their account settings.';
$txt['membergroups_modify'] = 'Modify';

$txt['membergroups_add_group'] = 'Add group';
$txt['membergroups_regular'] = 'Account groups';
$txt['membergroups_character'] = 'Character groups';
$txt['membergroups_guests_na'] = 'n/a';
$txt['no_character_groups'] = 'There are no character groups configured.';

$txt['membergroups_group_name'] = 'Membergroup name';
$txt['membergroups_new_board'] = 'Visible Boards';
$txt['membergroups_new_board_desc'] = 'Boards the membergroup can see';
$txt['membergroups_new_as_inherit'] = 'inherit from';
$txt['membergroups_new_as_type'] = 'by type';
$txt['membergroups_new_as_copy'] = 'based off of';
$txt['membergroups_new_copy_none'] = '(none)';
$txt['membergroups_can_edit_later'] = 'You can edit them later.';
$txt['membergroups_can_manage_access'] = 'This group can see all boards because they have the power to manage boards.';

$txt['char_group_level'] = 'Group Level';
$txt['char_group_level_acct'] = 'Account-Bound';
$txt['char_group_level_char'] = 'Character-Bound';

$txt['membergroups_cannot_delete_paid'] = 'This group cannot be deleted, it is currently in use by the following paid subscription(s): %1$s';

$txt['membergroups_edit_group'] = 'Edit Membergroup';
$txt['membergroups_edit_name'] = 'Group name';
$txt['membergroups_edit_inherit_permissions'] = 'Inherit Permissions';
$txt['membergroups_edit_inherit_permissions_desc'] = 'Select &quot;No&quot; to enable group to have own permission set.';
$txt['membergroups_edit_inherit_permissions_no'] = 'No - Use Unique Permissions';
$txt['membergroups_edit_inherit_permissions_from'] = 'Inherit From';
$txt['membergroups_edit_hidden'] = 'Visibility';
$txt['membergroups_edit_hidden_no'] = 'Visible';
$txt['membergroups_edit_hidden_boardindex'] = 'Visible - Apart from in group key';
$txt['membergroups_edit_hidden_all'] = 'Invisible';
// Do not use numeric entities in the below string.
$txt['membergroups_edit_hidden_warning'] = 'Are you sure you want to disallow assignment of this group as a users primary group?\\n\\nDoing so will restrict assignment to additional groups only, and will update all current &quot;primary&quot; members to have it as an additional group only.\\n\\nIt will also remove the group as moderator from any boards it is currently assigned to moderate.';
$txt['membergroups_edit_desc'] = 'Group description';
$txt['membergroups_edit_group_type'] = 'Group type';
$txt['membergroups_edit_select_group_type'] = 'Select Group type';
$txt['membergroups_group_type_private'] = 'Private <span class="smalltext">(Membership must be assigned)</span>';
$txt['membergroups_group_type_protected'] = 'Protected <span class="smalltext">(Only administrators can manage and assign)</span>';
$txt['membergroups_group_type_request'] = 'Requestable <span class="smalltext">(User may request membership)</span>';
$txt['membergroups_group_type_free'] = 'Free <span class="smalltext">(User may leave and join group at will)</span>';
$txt['membergroups_online_color'] = 'Color in online list';

$txt['membergroup_no_badge'] = '(no badge)';
$txt['membergroup_badge'] = 'Membergroup badge';
$txt['membergroup_has_badge'] = 'Has badge?';
$txt['membergroup_badge_preview'] = 'Preview:';
$txt['membergroup_badge_show'] = 'Show: ';
$txt['membergroup_badge_times'] = '&times;';
$txt['membergroup_no_images'] = 'There are no images in the Themes/default/images/membericons folder - no badge can be set.';

$txt['membergroups_max_messages'] = 'Max personal messages';
$txt['membergroups_max_messages_note'] = '0 = unlimited';
$txt['membergroups_tfa_force'] = 'Force Two-Factor-Authentication (2FA) for this membergroup';
$txt['membergroups_tfa_force_note'] = 'Be sure to warn your users before you activate this!';
$txt['membergroups_edit_save'] = 'Save';
$txt['membergroups_delete'] = 'Delete';
$txt['membergroups_confirm_delete'] = 'Are you sure you want to delete this group?';
$txt['membergroups_confirm_delete_mod'] = 'This group is assigned to moderate one or more boards. Are you sure you want to delete it?';
$txt['membergroups_swap_mod'] = 'This group is assigned to moderate one or more boards. Changing it to a post group will result in that group being dropped as moderator of those boards.';

$txt['membergroups_members_title'] = 'Viewing Group';
$txt['membergroups_members_group_members'] = 'Group Members';
$txt['membergroups_members_no_members'] = 'This group is currently empty';
$txt['membergroups_members_add_title'] = 'Add a member to this group';
$txt['membergroups_members_add_char_title'] = 'Add a character to this group';
$txt['membergroups_members_add_desc'] = 'List of Members to Add';
$txt['membergroups_members_add_char_desc'] = 'List of Characters to Add';
$txt['membergroups_members_add'] = 'Add Members';
$txt['membergroups_members_remove'] = 'Remove from Group';
$txt['membergroups_members_last_active'] = 'Last Active';
$txt['membergroups_members_additional_only'] = 'Add as additional group only.';
$txt['membergroups_members_group_moderators'] = 'Group Moderators';
$txt['membergroups_members_description'] = 'Description';
// Use javascript escaping in the below.
$txt['membergroups_members_deadmin_confirm'] = 'Are you sure you wish to remove yourself from the Administration group?';

$txt['membergroups_select_permission_type'] = 'Select permission profile';
$txt['membergroups_select_visible_boards'] = 'Show boards';
$txt['membergroups_members_top'] = 'Members';
$txt['membergroups_name'] = 'Name';
$txt['membergroups_icons'] = 'Icons';

$txt['admin_browse_approve'] = 'Members whose accounts are awaiting approval';
$txt['admin_browse_approve_desc'] = 'From here you can manage all members who are waiting to have their accounts approved.';
$txt['admin_browse_activate'] = 'Members whose accounts are awaiting activation';
$txt['admin_browse_activate_desc'] = 'This screen lists all the members who have still not activated their accounts at your forum.';
$txt['admin_browse_awaiting_approval'] = 'Awaiting Approval (%1$d)';
$txt['admin_browse_awaiting_activate'] = 'Awaiting Activation (%1$d)';

$txt['admin_browse_username'] = 'Username';
$txt['admin_browse_email'] = 'Email Address';
$txt['admin_browse_ip'] = 'IP Address';
$txt['admin_browse_registered'] = 'Registered';
$txt['admin_browse_id'] = 'ID';
$txt['admin_browse_with_selected'] = 'With Selected';
$txt['admin_browse_no_members_approval'] = 'No members currently await approval.';
$txt['admin_browse_no_members_activate'] = 'No members currently have not activated their accounts.';

// Don't use entities in the below strings, except the main ones. (lt, gt, quot.)
$txt['admin_browse_warn'] = 'all selected members?';
$txt['admin_browse_outstanding_warn'] = 'all affected members?';
$txt['admin_browse_w_approve'] = 'Approve';
$txt['admin_browse_w_activate'] = 'Activate';
$txt['admin_browse_w_delete'] = 'Delete';
$txt['admin_browse_w_reject'] = 'Reject';
$txt['admin_browse_w_remind'] = 'Remind';
$txt['admin_browse_w_approve_deletion'] = 'Approve (Delete Accounts)';
$txt['admin_browse_w_email'] = 'and send email';
$txt['admin_browse_w_approve_require_activate'] = 'Approve and require activation';

$txt['admin_browse_filter_by'] = 'Filter By';
$txt['admin_browse_filter_show'] = 'Displaying';
$txt['admin_browse_filter_type_0'] = 'Unactivated new accounts';
$txt['admin_browse_filter_type_2'] = 'Unactivated email changes';
$txt['admin_browse_filter_type_3'] = 'Unapproved new accounts';
$txt['admin_browse_filter_type_4'] = 'Unapproved account deletions';
$txt['admin_browse_filter_type_5'] = 'Unapproved "Under Age" Accounts';
$txt['admin_browse_filter_no_deletion'] = 'Note: Deletion of accounts here will not delete any posts.';

$txt['admin_browse_outstanding'] = 'Outstanding Members';
$txt['admin_browse_outstanding_days_1'] = 'With all members who registered longer than';
$txt['admin_browse_outstanding_days_2'] = 'days ago';
$txt['admin_browse_outstanding_perform'] = 'Perform the following action';
$txt['admin_browse_outstanding_go'] = 'Perform Action';

$txt['check_for_duplicate'] = 'Check for duplicates';
$txt['dont_check_for_duplicate'] = 'Don\'t check for duplicates';
$txt['duplicates'] = 'Duplicates';

$txt['not_activated'] = 'Not activated';
