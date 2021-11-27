<?php

/**
 * This file contains language strings for the board management.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

$txt['boards_and_cats'] = 'Manage Boards and Categories';
$txt['order'] = 'Order';
$txt['full_name'] = 'Full name';
$txt['board_slug'] = 'Board slug';
$txt['board_slug_desc'] = 'This is the part of the URL that contains the board name, e.g. general-discussion to make the URL yoursite.com/board/general-discussion';
$txt['name_on_display'] = 'This is the name that will be displayed.';
$txt['boards_and_cats_desc'] = 'Edit your categories and boards here. List multiple moderators as <em>&quot;username&quot;, &quot;username&quot;</em>. (these must be usernames and *not* display names)<br>To create a new board, click the Add Board button. To make the new board a sub-board of a current board, select "Sub-board of..." from the Order drop down menu when creating the board.';
$txt['parent_members_only'] = 'Regular Members';
$txt['parent_guests_only'] = 'Guests';
$txt['catConfirm'] = 'Do you really want to delete this category?';
$txt['boardConfirm'] = 'Do you really want to delete this board?';

$txt['catEdit'] = 'Edit Category';
$txt['collapse_enable'] = 'Collapsible';
$txt['collapse_desc'] = 'Allow users to collapse this category';
$txt['catModify'] = '(modify)';

$txt['mboards_order_after'] = 'After ';
$txt['mboards_order_inside'] = 'Inside ';
$txt['mboards_order_first'] = 'In first place';

$txt['mboards_new_board'] = 'Add Board';
$txt['mboards_new_cat_name'] = 'New Category';
$txt['mboards_add_cat_button'] = 'Add Category';
$txt['mboards_new_board_name'] = 'New Board';

$txt['mboards_name'] = 'Name';
$txt['mboards_modify'] = 'modify';
$txt['mboards_permissions'] = 'permissions';
// Don't use entities in the below string.
$txt['mboards_permissions_confirm'] = 'Are you sure you want to switch this board to use local permissions?';

$txt['mboards_delete_cat'] = 'Delete Category';
$txt['mboards_delete_board'] = 'Delete Board';

$txt['mboards_delete_cat_contains'] = 'Deleting this category will also delete the below boards, including all topics, posts and attachments within each board';
$txt['mboards_delete_option1'] = 'Delete category and all boards contained within.';
$txt['mboards_delete_option2'] = 'Delete category and move all boards contained within to';
$txt['mboards_delete_board_contains'] = 'Deleting this board will also move the sub-boards below, including all topics, posts and attachments within each board';
$txt['mboards_delete_board_option1'] = 'Delete board and move sub-boards contained within to category level.';
$txt['mboards_delete_board_option2'] = 'Delete board and move all sub-boards contained within to';
$txt['mboards_delete_what_do'] = 'Please select what you would like to do with these boards';
$txt['mboards_delete_confirm'] = 'Confirm';
$txt['mboards_delete_cancel'] = 'Cancel';

$txt['mboards_category'] = 'Category';
$txt['mboards_description'] = 'Description';
$txt['mboards_description_desc'] = 'A short description of your board.';
$txt['mboards_cat_description_desc'] = 'A short description of your category.';
$txt['mboards_groups'] = 'Allowed Groups';
$txt['mboards_groups_regular_members'] = 'This group contains all members that have no primary group set.';
$txt['mboards_moderators'] = 'Moderators';
$txt['mboards_moderators_desc'] = 'Additional members to have moderation privileges on this board. Note that administrators don\'t have to be listed here.';
$txt['mboards_moderator_groups'] = 'Moderator Groups';
$txt['mboards_moderator_groups_desc'] = 'Groups whose members have moderation privileges on this board. Note that this is limited to groups which are not post-based and not "hidden".';
$txt['mboards_count_posts'] = 'Count Posts';
$txt['mboards_count_posts_desc'] = 'Makes new replies and topics raise members\' post counts.';
$txt['mboards_unchanged'] = 'Unchanged';
$txt['mboards_theme'] = 'Board Theme';
$txt['mboards_theme_desc'] = 'This allows you to change the look of your forum only inside this board.';
$txt['mboards_theme_default'] = '(overall forum default.)';
$txt['mboards_override_theme'] = 'Override Member\'s Theme';
$txt['mboards_override_theme_desc'] = 'Use this board\'s theme even if the member didn\'t choose to use the defaults.';

$txt['mboards_redirect'] = 'Redirect to a web address';
$txt['mboards_redirect_desc'] = 'Enable this option to redirect anyone who clicks on this board to another web address.';
$txt['mboards_redirect_url'] = 'Address to redirect users to';
$txt['mboards_redirect_url_desc'] = 'For example: &quot;https://storybb.org&quot;.';
$txt['mboards_redirect_reset'] = 'Reset redirect count';
$txt['mboards_redirect_reset_desc'] = 'Selecting this will reset the redirection count for this board to zero.';
$txt['mboards_current_redirects'] = 'Currently: %1$s';

$txt['mboards_order_before'] = 'Before';
$txt['mboards_order_child_of'] = 'Sub-board of';
$txt['mboards_order_in_category'] = 'In category';
$txt['mboards_current_position'] = 'Current Position';
$txt['no_valid_parent'] = 'Board %1$s does not have a valid parent. Use the \'find and repair errors\' function to fix this.';

$txt['mboards_recycle_disabled_delete'] = 'Note: You must select an alternative recycle bin board or disable recycling before you can delete this board.';

$txt['mboards_settings_desc'] = 'Edit general board and category settings.';
$txt['mboards_settings_submit'] = 'Save';
$txt['recycle_enable'] = 'Enable recycling of deleted topics';
$txt['recycle_board'] = 'Board for recycled topics';
$txt['recycle_board_unselected_notice'] = 'You have enabled the recycling of topics without specifying a board to place them in. This feature will not be enabled until you specify a board to place recycled topics into.';
$txt['countChildPosts'] = 'Count sub-board\'s posts in parent\'s totals';
$txt['allow_ignore_boards'] = 'Allow boards to be ignored';
$txt['boardsaccess_option_desc'] = 'For each permission you can choose \'Allow\' (A), \'Disallow\' (X), or <span class="alert">\'Deny\' (D)</span>.<br><br>If you deny access, any member - (including moderators) - in that group will be denied access.<br>For this reason, you should set deny carefully, only when <strong>necessary</strong>. Disallow, on the other hand, denies unless otherwise granted.';

$txt['mboards_no_cats'] = 'There are currently no categories or boards configured.';

$txt['board_in_character'] = 'Board is "in character"?';
$txt['board_in_character_desc'] = 'If a board is "in character", only characters may post.';

$txt['board_sort_options'] = 'What should the normal order of topics be in this board?';
$txt['board_sort_force'] = 'Make this the only option?';
$txt['board_sort_force_subtext'] = 'Usually users can click on the headings in the lists of topics to reorder them. If this is enabled, only the order you set here will be used.';
$txt['board_sort_subject_asc'] = 'Topic subject, A&rarr;Z';
$txt['board_sort_subject_desc'] = 'Topic subject, Z&rarr;A';
$txt['board_sort_starter_asc'] = 'First poster/topic starter\'s name, A&rarr;Z';
$txt['board_sort_starter_desc'] = 'First poster/topic starter\'s name, Z&rarr;A';
$txt['board_sort_last_poster_asc'] = 'Last poster\'s name, A&rarr;Z';
$txt['board_sort_last_poster_desc'] = 'Last poster\'s name, Z&rarr;A';
$txt['board_sort_first_post_asc'] = 'First post, oldest &rarr; newest';
$txt['board_sort_first_post_desc'] = 'First post, newest &rarr; oldest';
$txt['board_sort_last_post_asc'] = 'Last post, oldest &rarr; newest';
$txt['board_sort_last_post_desc'] = 'Last post, newest &rarr; oldest';
