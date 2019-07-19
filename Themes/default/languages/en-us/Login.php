<?php

/**
 * This file contains language strings for the login/registration pages.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

// Registration form.
$txt['registration_form'] = 'Registration Form';
$txt['need_username'] = 'You need to fill in a username.';
$txt['no_password'] = 'You didn\'t enter your password.';
$txt['incorrect_password'] = 'Password incorrect';
$txt['choose_username'] = 'Choose username';
$txt['maintain_mode'] = 'Maintenance Mode';
$txt['registration_successful'] = 'Registration Successful';
$txt['now_a_member'] = 'Success! You are now a member of the forum.';
// Use numeric entities in the below string.
$txt['your_password'] = 'and your password is';
$txt['valid_email_needed'] = 'Please enter a valid email address, %1$s.';
$txt['required_info'] = 'Required Information';
$txt['additional_information'] = 'Additional Information';
$txt['warning'] = 'Warning!';
$txt['only_members_can_access'] = 'Only registered members are allowed to access this section.';
$txt['login_below'] = 'Please login below.';
$txt['login_below_or_register'] = 'Please login below or <a href="%1$s">sign up for an account</a> with %2$s';

$txt['char_register_nickname'] = 'Your nickname';
$txt['char_register_charname'] = 'Your first character\'s name';
$txt['no_character_added'] = 'You didn\'t add a name for your first roleplay character.';

// Use numeric entities in the below two strings.
$txt['may_change_in_profile'] = 'You may change it after you login by going to the profile page, or by visiting this page after you login:';
$txt['your_username_is'] = 'Your username is: ';

$txt['login_hash_error'] = 'Password security has recently been upgraded. Please enter your password again.';

$txt['ban_register_prohibited'] = 'Sorry, you are not allowed to sign up on this forum.';

$txt['activate_account'] = 'Account activation';
$txt['activate_success'] = 'Your account has been successfully activated. You can now proceed to login.';
$txt['activate_not_completed1'] = 'Your email address needs to be validated before you can login.';
$txt['activate_not_completed2'] = 'Need another activation email?';
$txt['activate_after_registration'] = 'Thank you for signing up. You will receive an email soon with a link to activate your account. If you don\'t receive an email after some time, check your spam folder.';
$txt['invalid_userid'] = 'User does not exist';
$txt['invalid_activation_code'] = 'Invalid activation code';
$txt['invalid_activation_username'] = 'Username or email';
$txt['invalid_activation_new'] = 'If you signed up with the wrong email address, type a new one and your password here.';
$txt['invalid_activation_new_email'] = 'New email address';
$txt['invalid_activation_password'] = 'Old password';
$txt['invalid_activation_resend'] = 'Resend activation code';
$txt['invalid_activation_known'] = 'If you already know your activation code, please type it here.';
$txt['invalid_activation_retry'] = 'Activation code';
$txt['invalid_activation_submit'] = 'Activate';

$txt['awaiting_delete_account'] = 'Your account has been marked for deletion!<br>If you wish to restore your account, please check the &quot;Reactivate my account&quot; box, and login again.';
$txt['undelete_account'] = 'Reactivate my account';

// Use numeric entities in the below three strings.
$txt['change_password'] = 'New Password Details';
$txt['change_password_login'] = 'Your login details at';
$txt['change_password_new'] = 'have been changed and your password reset. Below are your new login details.';

$txt['in_maintain_mode'] = 'This board is in Maintenance Mode.';

$txt['registration_i_agree_to'] = 'I agree to the %1$s';
$txt['site_policies'] = 'Site Policies';

$txt['register_passwords_differ_js'] = 'The two passwords you entered are not the same!';

$txt['approval_after_registration'] = 'Thank you for signing up. The admin must approve your registration before you may begin to use your account, you will receive an email shortly advising you of the admins decision.';

$txt['admin_settings_desc'] = 'Here you can change a variety of settings related to registration of new members.';

$txt['setting_registration_method'] = 'Method of registration employed for new members';
$txt['setting_registration_disabled'] = 'Registration Disabled';
$txt['setting_registration_standard'] = 'Immediate Registration';
$txt['setting_registration_activate'] = 'Email Activation';
$txt['setting_registration_approval'] = 'Admin Approval';
$txt['setting_remove_unapproved_accounts_days'] = 'Remove unapproved accounts after how many days?';
$txt['setting_send_welcomeEmail'] = 'Send welcome email to new members';
$txt['setting_show_cookie_notice'] = 'Show a cookie notice in the footer to guests';

$txt['setting_registration_character'] = 'Whether users need to make characters on registration';
$txt['setting_registration_character_disabled'] = 'No character creation on registration';
$txt['setting_registration_character_optional'] = 'Character creation on registration is optional';
$txt['setting_registration_character_required'] = 'New users must create a new character at sign-up';

$txt['setting_minimum_age'] = 'Minimum allowed age on the site';
$txt['setting_minimum_age_profile'] = 'Enforce minimum allowed age in profiles';
$txt['setting_age_on_registration'] = 'Ask for birthdate on registration';

$txt['admin_register'] = 'Registration of new member';
$txt['admin_register_desc'] = 'From here you can register new members into the forum, and if desired, email them their details.';
$txt['admin_register_username'] = 'New Username';
$txt['admin_register_email'] = 'Email Address';
$txt['admin_register_password'] = 'Password';
$txt['admin_register_username_desc'] = 'Username for the new member';
$txt['admin_register_email_desc'] = 'Email address of the member';
$txt['admin_register_password_desc'] = 'Password for new member';
$txt['admin_register_email_detail'] = 'Email new password to user';
$txt['admin_register_email_detail_desc'] = 'Email address required even if unchecked';
$txt['admin_register_email_activate'] = 'Require user to activate the account';
$txt['admin_register_group'] = 'Primary Membergroup';
$txt['admin_register_group_desc'] = 'Primary membergroup new member will belong to';
$txt['admin_register_group_none'] = '(no primary membergroup)';
$txt['admin_register_done'] = 'Member %1$s has been registered successfully!';

$txt['admin_policies_desc'] = 'Manage site-wide policies for users.';
$txt['policy_type'] = 'Policy type';
$txt['policy_name'] = 'Policy name';
$txt['policy_desc'] = 'Policy description';
$txt['policy_type_terms'] = 'Terms and conditions';
$txt['policy_type_privacy'] = 'Privacy policy';
$txt['policy_type_roleplay'] = 'Roleplay rules';
$txt['policy_type_cookies'] = 'Cookie notice';
$txt['policy_show_reg'] = 'Show on registration';
$txt['policy_show_help'] = 'Show in help area';
$txt['policy_show_footer'] = 'Show in footer';
$txt['policies_in_languages'] = 'Policies in languages';
$txt['policy_language_no_version'] = 'Missing in these languages:';
$txt['policy_text'] = 'Policy text';
$txt['policy_reagree'] = 'Force users to re-agree to this policy';
$txt['policy_reagree_desc'] = 'If ticked, all users will be forced to reagree to this policy before continuing to use the site.';
$txt['policy_edit'] = 'Reason for change';
$txt['policy_edit_desc'] = 'If specified, it will be shown to users who are re-agreeing to this policy.';

$txt['visual_verification_sound_again'] = 'Play again';
$txt['visual_verification_sound_close'] = 'Close window';
$txt['visual_verification_sound_direct'] = 'Having problems hearing this?  Try a direct link to it.';

// Use numeric entities in the below.
$txt['registration_username_available'] = 'Username is available';
$txt['registration_username_unavailable'] = 'Username is not available';
$txt['registration_username_check'] = 'Check if username is available';
$txt['registration_password_short'] = 'Password is too short';
$txt['registration_password_reserved'] = 'Password contains your username/email';
$txt['registration_password_numbercase'] = 'Password must contain both upper and lower case, and numbers';
$txt['registration_password_no_match'] = 'Passwords do not match';
$txt['registration_password_valid'] = 'Password is valid';

$txt['registration_errors_occurred'] = 'The following errors were detected in your registration. Please correct them to continue:';
