<?php

/**
 * This file contains language strings for the themes management area.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

$txt['themeadmin_explain'] = 'Themes are the different looks and feels of your forum. These settings affect the selection of themes, and which themes guests and other members use.';

$txt['theme_allow'] = 'Allow members to select their own themes.';
$txt['theme_guests'] = 'Overall forum default';
$txt['theme_select'] = 'choose...';
$txt['theme_reset'] = 'Reset everyone to';
$txt['theme_nochange'] = 'No change';
$txt['theme_forum_default'] = 'Forum Default';

$txt['theme_remove'] = 'remove';
$txt['theme_enable'] = 'enable';
$txt['theme_disable'] = 'disable';
$txt['theme_remove_confirm'] = 'Are you sure you want to permanently remove this theme?';
$txt['theme_enable_confirm'] = 'Are you sure you want to enable this theme?';
$txt['theme_disable_confirm'] = 'Are you sure you want to disable this theme?';
$txt['theme_confirmed_disabling'] = 'The theme was successfully disabled.';
$txt['theme_confirmed_enabling'] = 'The theme was successfully enabled.';
$txt['theme_confirmed_removing'] = 'The theme was successfully removed.';

$txt['theme_install'] = 'Install a New Theme';
$txt['theme_install_dir'] = 'From a directory on the server';
$txt['theme_install_error'] = 'That theme directory doesn\'t exist, or doesn\'t contain a theme.';
$txt['theme_install_go'] = 'Install';
$txt['theme_install_new'] = 'Create a copy of Default named';
$txt['theme_install_new_confirm'] = 'Are you sure you want to install this new theme?';
$txt['theme_install_writable'] = 'Warning - you cannot create or install a new theme as your themes directory is not currently writable.';
$txt['theme_installed'] = 'Installed Successfully';
$txt['theme_installed_message'] = 'was installed successfully.';
$txt['theme_updated_message'] = 'was updated successfully.';
$txt['theme_install_no_action'] = 'This isn\'t a valid install action.';
$txt['theme_install_error_title'] = 'An error occurred while installing the theme.';
$txt['theme_install_error_file_1'] = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
$txt['theme_install_error_file_2'] = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
$txt['theme_install_error_file_3'] = 'The uploaded file was only partially uploaded.';
$txt['theme_install_error_file_4'] = 'No file was uploaded.';
$txt['theme_install_error_file_6'] = 'Missing a temporary upload folder.';
$txt['theme_install_error_file_7'] = 'Failed to write file to disk.';
$txt['theme_install_invalid_dir'] = 'You did not add a path for your actual theme, you cannot re-add the default theme';
$txt['theme_install_already_dir'] = 'The name you specified is already been used by another theme, please try a different name.';
$txt['theme_install_invalid_id'] = 'This is not a valid theme ID.';

$txt['theme_pick'] = 'Choose a theme...';
$txt['theme_preview'] = 'Preview theme';
$txt['theme_set'] = 'Use this theme';
$txt['theme_user'] = 'person is using this theme.';
$txt['theme_users'] = 'people are using this theme.';
$txt['theme_pick_variant'] = 'Select Variant';

$txt['theme_edit'] = 'Edit Theme';
$txt['theme_edit_style'] = 'Modify the stylesheets. (colors, fonts, etc.)';
$txt['theme_edit_index'] = 'Modify the index template. (the main template)';

$txt['theme_global_description'] = 'This is the default theme, which means your theme will change along with the administrators\' settings and the board you are viewing.';

$txt['theme_url_config'] = 'Theme URLs and Configuration';
$txt['theme_variants'] = 'Theme Variants';
$txt['theme_options'] = 'Theme Options and Preferences';
$txt['actual_theme_name'] = 'This theme\'s name: ';
$txt['actual_theme_dir'] = 'This theme\'s directory: ';
$txt['actual_theme_url'] = 'This theme\'s URL: ';
$txt['actual_images_url'] = 'This theme\'s images URL: ';
$txt['current_theme_style'] = 'This theme\'s style: ';

$txt['theme_variants_default'] = 'Default theme variant';
$txt['theme_variants_user_disable'] = 'Disable user variant selection';

$txt['allow_no_censored'] = 'Allow users to turn off word censoring';
$txt['who_display_viewing'] = 'Show who is viewing the board index and posts';
$txt['who_display_viewing_off'] = 'Don\'t show';
$txt['who_display_viewing_numbers'] = 'Show only numbers';
$txt['who_display_viewing_names'] = 'Show member names';
$txt['disable_recent_posts'] = 'Disable recent posts';
$txt['enable_single_post'] = 'Enable single post';
$txt['enable_multiple_posts'] = 'Enable multiple posts';
$txt['latest_members'] = 'Show latest member on board index';
$txt['member_list_bar'] = 'Show members list bar on board index';
$txt['header_logo_url'] = 'Logo image URL';
$txt['header_logo_url_desc'] = '(leave blank to show forum name or default logo.)';
$txt['sub_boards_columns'] = 'Display sub-boards in how many columns';

$txt['theme_adding_title'] = 'Obtaining Themes';
$txt['theme_adding'] = 'You can always find new themes for your forum from the StoryBB themes area - <strong><a href="https://storybb.org" target="_blank" rel="noopener">https://storybb.org/</a></strong>. You can browse them on the website, read the comments, and download them to your computer and then upload them to your forum from there.';

$txt['theme_options_defaults'] = 'These are the default values for some member specific settings. Changing these will only affect new members and guests.';
$txt['theme_options_title'] = 'Change or reset default options';

$txt['themeadmin_title'] = 'Themes and Layout Settings';
$txt['themeadmin_description'] = 'Here you can modify the settings for your themes, update theme selections, reset member options, and the like.';
$txt['themeadmin_admin_desc'] = 'This page allows you to change the default theme, reset members to all use a certain theme, and choose other settings related to theme selection. You are also able to install themes from here.<br><br>Don\'t forget to look at the theme settings for your themes for layout options.';
$txt['themeadmin_list_desc'] = 'From here, you can view the list of themes you currently have installed, change their paths and settings, and uninstall them.';
$txt['themeadmin_reset_desc'] = 'Below you will see an interface to change the current theme-specific options for all your members. You will only see those themes that have their own set of settings.';

$txt['themeadmin_list_heading'] = 'Theme Settings Overview';
$txt['themeadmin_list_tip'] = 'Remember, the layout settings may be different between one theme and another. Click on the theme\'s names below to set their options, change their directory or URL settings, or to find other options.';
$txt['themeadmin_list_theme_dir'] = 'Theme directory (templates)';
$txt['themeadmin_list_invalid'] = '(Warning! this path is not correct.)';
$txt['themeadmin_list_theme_url'] = 'URL to above directory';
$txt['themeadmin_list_images_url'] = 'URL to images directory';
$txt['themeadmin_list_reset'] = 'Reset Theme URLs and Directories';
$txt['themeadmin_list_reset_dir'] = 'Base path to Themes directory';
$txt['themeadmin_list_reset_url'] = 'Base URL to the same directory';
$txt['themeadmin_list_reset_go'] = 'Attempt to reset all themes';

$txt['themeadmin_reset_tip'] = 'Each theme may have its own custom options for selection by your members. These include things like avatars, signatures, layout options and other similar options. Here you can change the defaults or reset everyone\'s options.<br><br>Please note that some themes may use the default options, in which case they will not have their own options.';
$txt['themeadmin_reset_defaults'] = 'Configure guest and new user options for this theme';
$txt['themeadmin_reset_defaults_current'] = 'options currently set.';
$txt['themeadmin_reset_members'] = 'Change current options for all members using this theme';
$txt['themeadmin_reset_remove'] = 'Remove all members\' options and use the defaults';
$txt['themeadmin_reset_remove_current'] = 'members currently using their own options.';
// Don't use entities in the below string.
$txt['themeadmin_reset_remove_confirm'] = 'Are you sure you want to remove all theme options?-n-This may reset some custom profile fields as well.';
$txt['themeadmin_reset_options_info'] = 'The options below will reset options for <em>everyone</em>. To change an option, select &quot;change&quot; in the box next to it, and then select a value for it. To use the default, select &quot;default&quot;. Otherwise, use &quot;don\'t change&quot; to keep it as-is.';
$txt['themeadmin_reset_options_change'] = 'Change';
$txt['themeadmin_reset_options_none'] = 'Don\'t change';
$txt['themeadmin_reset_options_default'] = 'Default';

$txt['themeadmin_edit_exists'] = 'already exists';
$txt['themeadmin_edit_do_copy'] = 'copy';
$txt['themeadmin_edit_copy_warning'] = 'When StoryBB needs a template or language file which is not in the current theme, it looks in the theme it is based on, or the default theme.<br>Unless you need to modify a template, it\'s better not to copy it.';
$txt['themeadmin_edit_copy_confirm'] = 'Are you sure you want to copy this template?';
$txt['themeadmin_edit_overwrite_confirm'] = 'Are you sure you want to copy this template over the one that already exists?\nThis will OVERWRITE any changes you\\\'ve made';
$txt['themeadmin_edit_no_copy'] = '<em>(can\'t copy)</em>';
$txt['themeadmin_edit_filename'] = 'Filename';
$txt['themeadmin_selectable'] = 'Themes that the user is able to select';
$txt['themeadmin_themelist_link'] = 'Show the list of themes';

/* Open Graph */
$txt['og_image'] = 'OG Image';
$txt['og_image_desc'] = 'Link to your Open Graph optimized image, suggested size 175x175px<br><span class="smalltext">You can read more about here <a href="http://ogp.me/">Open Graph</a></span>';

$txt['favicon_explain'] = 'Favicons are images used by different browsers to help users identify your site among others on their computer, e.g. in browser tabs, bookmarks and similar. The most common sizes are supported below.';
$txt['favicon_0'] = '16x16 PNG (browser tab icon)';
$txt['favicon_1'] = '32x32 PNG (browser tab icon)';
$txt['favicon_2'] = '120x120 PNG (older iPhone/Android)';
$txt['favicon_3'] = '180x180 PNG (new iPhone/Android)';
$txt['favicon_4'] = '152x152 PNG (older iPad)';
$txt['favicon_5'] = '167x167 PNG (newer iPad)';
$txt['favicon_6'] = '128x128 PNG (Android)';
$txt['favicon_7'] = '192x192 PNG (Android)';
$txt['favicon_none'] = '(none currently uploaded)';
$txt['favicon_wrong_type'] = 'The image uplodaed for "%1$s" was not a PNG image.';
$txt['favicon_could_not_save'] = 'The image uploaded for "%1$s" could not be saved.';
$txt['favicon_saved'] = 'The image uploaded for "%1$s" was saved successfully.';
