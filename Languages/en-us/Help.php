<?php

/**
 * This file contains language strings for the help popups.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

global $helptxt;

$txt['close_window'] = 'Close window';

$helptxt['userlog'] = '<strong>Profile Edits Log</strong><br>
	This page allows members of the admin team to view changes users make to their profiles, and is available from inside a user\'s profile area.';
$helptxt['warning_enable'] = '<strong>User Warning System</strong><br>
	This feature enables members of the admin and moderation team to issue warnings to members - and to use a members warning level to determine the
	actions available to them on the forum. Upon enabling this feature a permission will be available within the permissions page to define
	which groups may assign warnings to members. Warning levels can be adjusted from a member\'s profile.';
$helptxt['warning_watch'] = 'This setting defines the percentage warning level a member must reach to automatically assign a &quot;watch&quot; to the member. Any member who is being &quot;watched&quot; will appear in the watched members list in the moderation center.';
$helptxt['warning_moderate'] = 'Any member passing the value of this setting will find all their posts require moderator approval before they appear to the forum community. This will override any local board permissions which may exist related to post moderation.';
$helptxt['warning_mute'] = 'If this warning level is passed by a member they will find themselves under a post ban. The member will lose all posting rights.';
$helptxt['user_limit'] = 'This setting limits the amount of points a moderator may add/remove to any particular member in a twenty four hour period. This
			can be used to limit what a moderator can do in a small period of time. This can be disabled by setting it to a value of zero. Note that
			any members with administrator permissions are not affected by this value.';

$helptxt['theme_settings'] = '<strong>Theme Settings</strong><br>
	The settings page allows you to change settings specific to a theme. These settings include options such as the themes directory and URL information but
	also options that affect the layout of a theme on your forum. Most themes will have a variety of user configurable settings, allowing you to adapt a theme
	to suit your individual forum needs.';

$helptxt['topicSummaryPosts'] = 'This allows you to set the number of previous posts shown in the topic summary on the reply page.';
$helptxt['enableAllMessages'] = 'Set this to the <em>maximum</em> number of posts a topic can have to show the <em>all</em> link. Setting this lower than &quot;Maximum messages to display in a topic page&quot; will simply mean it never gets shown, and setting it too high could slow down your forum.';
$helptxt['allow_guestAccess'] = 'Unchecking this box will stop guests from doing anything but very basic actions on your forum - login, register, password reminder, etc. - on your forum. This is not the same as disallowing guest access to boards.';
$helptxt['userLanguage'] = 'Turning this setting on will allow users to select which language file they use. It will not affect the
		default selection.';
$helptxt['trackStats'] = 'Stats:<br>This will track show several statistics, like the most members online, new members, and new topics.<hr>
		Page views:<br>Adds another column to the stats page with the number of pageviews on your forum.';
$helptxt['todayMod'] = 'This will show &quot;Today&quot; or &quot;Yesterday&quot; instead of the date.<br><br>
		<strong>Examples:</strong><br><br>
		<ul class="normallist">
			<li>
			<strong>Disabled</strong><br>
			October 3, 2009 at 12:59:18 am</li>
			<li><strong>Only Today</strong><br>
			Today at 12:59:18 am</li>
			<li><strong>Today &amp; Yesterday</strong><br>
			Yesterday at 09:36:55 pm</li>
		</ul>';
$helptxt['disableCustomPerPage'] = 'Check this setting to stop users from customizing the amount of messages and topics to display per page on the Message Index and Topic Display page respectively.';
$helptxt['pollMode'] = 'This selects whether polls are enabled or not. If polls are disabled, any existing polls will be hidden
		from the topic listing. You can choose to continue to show the regular topic without their polls by selecting
		&quot;Show Existing Polls as Topics&quot;.<br><br>To choose who can post polls, view polls, and similar, you
		can allow and disallow those permissions. Remember this if polls are not working.';
$helptxt['enable_debug_templates'] = 'By default, the templates that make up the pages on StoryBB are compiled into PHP and stored on the server to make it faster. For development and debugging purposes, you might want to turn off this storage and let templates be processed only as needed.';

$helptxt['enableErrorLogging'] = 'This will log any errors, like a failed login, so you can see what went wrong.';
$helptxt['enableErrorQueryLogging'] = 'This will include the full query sent to the database in the error log. It requires error logging to be turned on.<br><br><strong>Note:  This will affect the ability to filter the error log by the error message.</strong>';
$helptxt['log_ban_hits'] = 'If enabled, every time a banned user tries to access the site, this will be logged in the error log. If you do not care whether, or how often, banned users attempt to access the site, you can turn this off for a performance boost.';
$helptxt['disallow_sendBody'] = 'This setting removes the option to receive the text of replies, posts, and personal messages in notification emails.<br><br>Often, members will reply to the notification email, which in most cases means the webmaster receives the reply.';
$helptxt['enable_ajax_alerts'] = 'This option allows your members to receive AJAX notifications. This means that members don\'t need to refresh the page to get new notifications.<br><strong>DO NOTE:</strong> This option might cause a severe load at your server with many users online.';
$helptxt['removeNestedQuotes'] = 'This will strip nested quotes from a post when citing the post in question via a quote link.';
$helptxt['max_image_width'] = 'This allows you to set a maximum size for posted pictures. Pictures smaller than the maximum will not be affected. This also determines how attached images are displayed when a thumbnail is clicked on.';
$helptxt['mail_type'] = 'This setting allows you to choose either PHP\'s default settings, or to override those settings with SMTP. PHP doesn\'t support using authentication with SMTP (which many hosts now require), so if you want that you should select SMTP. Please note that SMTP can be slower, and some servers will not accept usernames and passwords.<br><br>You don\'t need to fill in the SMTP settings if this is set to PHP\'s default.';
$helptxt['attachmentCheckExtensions'] = 'For some communities, you may wish to limit the types of files that users can upload by checking the extension: e.g. myphoto.jpg has an extension of jpg.';
$helptxt['attachmentExtensions'] = 'If "check attachment\'s extension" above is ticked, these are the extensions that will be permitted for new attachments.';
$helptxt['attachmentUploadDir'] = 'The path to your attachment folder on the server<br>(example: /home/sites/yoursite/www/forum/attachments)';
$helptxt['attachmentDirSizeLimit'] = ' Select how large the attachment folder can be, including all files within it.';
$helptxt['attachmentPostLimit'] = ' Select the maximum filesize (in KB) of all attachments made per post. If this is lower than the per-attachment limit, this will be the limit.';
$helptxt['attachmentSizeLimit'] = 'Select the maximum filesize of each separate attachment.';
$helptxt['attachmentNumPerPostLimit'] = ' Select the number of attachments a person can make per post.';
$helptxt['attachmentShowImages'] = 'If the uploaded file is a picture, it will be displayed underneath the post.';
$helptxt['attachmentThumbnails'] = 'If the above setting is selected, this will save a separate (smaller) attachment for the thumbnail to decrease bandwidth.';
$helptxt['attachmentThumbWidth'] = 'Only used with the &quot;Resize images when showing under posts&quot; setting the maximum width to resize attachments down from. They will be resized proportionally.';
$helptxt['attachmentThumbHeight'] = 'Only used with the &quot;Resize images when showing under posts&quot; setting the maximum height to resize attachments down from. They will be resized proportionally.';
$helptxt['attachmentDirFileLimit'] = 'Max number of files per directory';
$helptxt['attachmentEnable'] = 'This setting enables you to configure how attachments can be made.<br><br>
	<ul class="normallist">
		<li>
			<strong>Disable all attachments</strong><br>
			All attachments are disabled. Existing attachments are not deleted, but they are hidden from view (even administrators cannot see them). New attachments cannot be made either, regardless of permissions.<br><br>
		</li>
		<li>
			<strong>Enable all attachments</strong><br>
			Everything behaves as normal, users who are permitted to view attachments can do so, users who are permitted to upload can do so.<br><br>
		</li>
		<li>
			<strong>Disable new attachments</strong><br>
			Existing attachments are still accessible, but no new attachments can be added, regardless of permission.
		</li>
	</ul>';
$helptxt['attachment_image_paranoid'] = 'Selecting this setting will enable very strict security checks on image attachments. <strong>Warning!</strong> These extensive checks can fail on valid images too. It is strongly recommended to only use this setting together with image re-encoding, in order to have StoryBB try to resample the images which fail the security checks: if successful, they will be sanitized and uploaded. Otherwise, if image re-encoding is not enabled, all attachments failing checks will be rejected.';
$helptxt['attachment_image_reencode'] = 'Selecting this setting will enable trying to re-encode the uploaded image attachments. Image re-encoding offers better security. Note however that image re-encoding also renders all animated images static. <br> This feature is only possible if the GD module is installed on your server.';
$helptxt['automanage_attachments'] = 'By default, StoryBB puts new attachments into a single folder. For most sites this is not a problem, but as a site grows it can be useful to have multiple folders to store attachments in.<br><br>This setting allows you to set whether you manage these folders yourself (e.g. creating a second folder and moving to it when you are ready) or whether you let StoryBB do it, based on criteria, such as when the current directory reaches a given size, or breaking down folders by years or even months on very busy sites.';
$helptxt['use_subdirectories_for_attachments'] = 'Create new directories.';
$helptxt['max_image_height'] = 'As with the maximum width, this setting indicates the maximum height a posted image can be.';
$helptxt['avatar_paranoid'] = 'Selecting this setting will enable very strict security checks on avatars. <strong>Warning!</strong> These extensive checks can fail on valid images too. It is strongly recommended to only use this setting together with avatar re-encoding, in order to have StoryBB try to resample the images which fail the security checks: if successful, they will be sanitized and uploaded. Otherwise, if re-encoding of avatars is not enabled, all avatars failing checks will be rejected.';
$helptxt['avatar_reencode'] = 'Selecting this setting will enable trying to re-encode the uploaded avatars. Image re-encoding offers better security. Note, however, that image re-encoding also renders all animated images static. <br> This feature is only possible if the GD module is installed on your server.';
$helptxt['enableBBC'] = 'Selecting this setting will allow your members to use Bulletin Board Code (BBC) throughout the forum, allowing users to format their posts with images, type formatting, and more.';
$helptxt['time_offset'] = 'Not all forum administrators want their forum to use the same time zone as the server upon which it is hosted. Use this setting to specify the time difference (in hours) between the server time and the time to be used for the forum. Negative and decimal values are permitted.';
$helptxt['default_timezone'] = 'The server time zone tells PHP where your server is located. You should ensure that this is set correctly, preferably to the country/city in which the server is located. You can find out more information on the <a href="https://php.net/manual/en/timezones.php" target="_blank" rel="noopener">PHP Site</a>.';
$helptxt['timezone_priority_countries'] = 'This setting lets you push the time zones for a certain country or countries to the top of the list of selectabled time zones that is shown when users are configuring their profiles, etc.<br><br>For example, if many of your forum\'s members live in New Zealand or Fiji, you may enter "NZ,FJ" to make it easier for them to find the most relevant time zones quickly.<br><br>You can find the complete list of ISO country codes by searching the Internet for "<a href="//www.google.com/search?q=iso+3166-1+alpha-2" target="_blank" rel="noopener">ISO 3166-1 alpha-2</a>".';
$helptxt['spamWaitTime'] = 'Here you can select the amount of time that must pass between postings. This can be used to stop people from "spamming" your forum by limiting how often they can post.';

$helptxt['enablePostHTML'] = 'This will allow the posting of some basic HTML tags:
	<ul class="normallist">
		<li>&lt;b&gt;, &lt;u&gt;, &lt;i&gt;, &lt;s&gt;, &lt;em&gt;, &lt;strong&gt;, &lt;ins&gt;, &lt;del&gt;</li>
		<li>&lt;a href=&quot;&quot;&gt;</li>
		<li>&lt;img src=&quot;&quot; alt=&quot;&quot; /&gt;</li>
		<li>&lt;br&gt;, &lt;hr&gt;</li>
		<li>&lt;pre&gt;, &lt;blockquote&gt;</li>
	</ul>';

$helptxt['theme_install'] = 'This allows you to install new themes. You can do this from an existing directory, by uploading an archive for the theme, or by copying the default theme.<br><br>Note that the archive or directory must have a <pre>theme.json</pre> definition file.';
$helptxt['xmlnews_enable'] = 'Allows people to link to <a href="%1$s?action=.xml;sa=news" target="_blank" rel="noopener">Recent news</a>
	and similar data. It is also recommended that you limit the size of recent posts/news because some clients expect the RSS data to be truncated for display.';
$helptxt['securityDisable'] = 'This <em>disables</em> the additional password check for the administration page. This is not recommended!';
$helptxt['securityDisable_why'] = 'This is your current password. (the same one you use to login.)<br><br>The requirement to enter this helps ensure that you want to do whatever administration you are doing, and that it is <strong>you</strong> doing it.';
$helptxt['frame_security'] = 'Modern browsers now understand a security header presented by servers called X-Frame-Options. By setting this option you specify how you want to allow your site to be framed inside a frameset or a iframe. Disable will not send any header and is the most unsecure, however allows the most freedom. Deny will prevent all frames completely and is the most restrictive and secure. Allowing the Same Origin will only allow your domain to issue any frames and provides a middle ground for the previous two options.';
$helptxt['proxy_ip_header'] = 'This is the server header that will be trusted by StoryBB for containing the actual users IP address. Changing this setting can cause unexpected IP results on members. Please check with your server administrator, CDN provider or proxy administrator prior to changing these settings. Most providers will understand and use HTTP_X_FORWARDED_FOR. You should fill out the list of Servers sending the reverse proxy headers for security to ensure these headers only come from valid sources.';

$helptxt['failed_login_threshold'] = 'Set the number of failed login attempts before directing the user to the password reminder page.';
$helptxt['loginHistoryDays'] = 'The number of days to keep login history under user profile tracking. The default is 30 days.';
$helptxt['oldTopicDays'] = 'If this setting is enabled, a warning will be displayed to the user when attempting to reply to a topic which has not had any new replies for the amount of time, in days, specified by this setting. Set this setting to 0 to disable the feature.';
$helptxt['edit_wait_time'] = 'The number of seconds allowed for a post to be edited before logging the last edit date.';
$helptxt['edit_disable_time'] = 'The number of minutes allowed to pass before a user can no longer edit a post they have made. Set to 0 disable. <br><br><em>Note: This will not affect any user who has permission to edit other people\'s posts.</em>';
$helptxt['preview_characters'] = 'This setting sets the number of available characters for the first and last message topic preview.';
$helptxt['posts_require_captcha'] = 'This setting will force users to pass anti-spam bot verification each time they make a post to a board. Only users with a post count below the number set will need to enter the code - this should help combat automated spamming scripts.';
$helptxt['disable_wysiwyg'] = 'This setting disallows all users from using the WYSIWYG (&quot;What You See Is What You Get&quot;) editor on the post page.';
$helptxt['lastActive'] = 'Set the number of minutes to show people are active in X number of minutes on the board index. Default is 15 minutes.';

$helptxt['customoptions'] = 'This defines the options that a user may choose from a drop down list. There are a few key points to note on this page:
	<ul class="normallist">
		<li><strong>Default setting:</strong> Whichever check box has the &quot;radio button&quot; next to it selected will be the default selection for the user when they enter their profile.</li>
		<li><strong>Removing Options:</strong> To remove an option simply empty the text box for that option - all users with that selected will have their option cleared.</li>
		<li><strong>Reordering Options:</strong> You can reorder the options by moving text around between the boxes. However - an important note - you must make sure you do <strong>not</strong> change the text when reordering options as otherwise user data will be lost.</li>
	</ul>';

$helptxt['allow_ignore_boards'] = 'Checking this setting will allow users to select boards they wish to ignore.';

$helptxt['who_enabled'] = 'This setting allows you to turn on or off the ability for users to see who is browsing the forum and what they are doing.';

$helptxt['recycle_enable'] = '&quot;Recycles&quot; deleted topics and posts to the specified board.';

$helptxt['max_pm_recipients'] = 'This setting allows you to set the maximum amount of recipients allowed in a single personal message sent by a forum member. This may be used to help stop spam abuse of the PM system. Note that users with permission to send newsletters are exempt from this restriction. Set to zero for no limit.';
$helptxt['pm_posts_verification'] = 'This setting will force users to enter a code shown on a verification image each time they are sending a personal message. Only users with a post count below the number set will need to enter the code - this should help combat automated spamming scripts.';
$helptxt['pm_posts_per_hour'] = 'This will limit the number of personal messages which may be sent by a user in a one hour period. This does not affect admins or moderators.';

$helptxt['registration_method'] = 'This setting determines what method of registration is used for people wishing to join your forum. You can select from:<br><br>
	<ul class="normallist">
		<li>
			<strong>Registration Disabled</strong><br>
				Disables the registration process, which means that no new members can register to join your forum.<br>
		</li><li>
			<strong>Immediate Registration</strong><br>
				New members can login and post immediately after registering on your forum.<br>
		</li><li>
			<strong>Email Activation</strong><br>
				When this setting is enabled any members registering with the forum will have an activation link emailed to them which they must click before they can become full members.<br>
		</li><li>
			<strong>Admin Approval</strong><br>
				This setting will make it so all new members registering on your forum will need to be approved by the admin before they become members.
		</li>
	</ul>';

$helptxt['send_validation_onChange'] = 'When this setting is checked all members who change their email address in their profile will have to reactivate their account from an email sent to that address';

$helptxt['send_welcomeEmail'] = 'When this setting is enabled all new members will be sent an email welcoming them to your community';
$helptxt['show_cookie_notice'] = 'When this setting is enabled, StoryBB will check to see if the user has previously accepted to have cookies from this site - if not, puts up a message in the footer that will remain there until the guest accepts. This option is designed to make it easier to be compliant with EU cookie regulations and the GDPR.';
$helptxt['password_strength'] = 'This setting determines the strength required for passwords selected by your forum users. The stronger the password, the harder it should be to compromise member\'s accounts.
	Its possible settings are:
	<ul class="normallist">
		<li><strong>Low:</strong> The password must be at least four characters long.</li>
		<li><strong>Medium:</strong> The password must be at least eight characters long, and cannot be part of a user\'s name or email address.</li>
		<li><strong>High:</strong> As for medium, except the password must also contain a mixture of upper and lower case letters, and at least one number.</li>
	</ul>';

$helptxt['minimum_age'] = 'This setting sets the minimum age that will be shown in the terms and conditions.';
$helptxt['minimum_age_profile'] = 'By default, the minimum age is just set for the terms and conditions, but this enforces that anyone setting their date of birth in their profile also meets the minimum age.';

$helptxt['allow_hideOnline'] = 'With this setting enabled all members will be able to hide their online status from other users (except administrators). If disabled, only users who can moderate the forum can hide their presence. Note that disabling this setting will not change any existing member\'s status - it just stops them from hiding themselves in the future.';
$helptxt['meta_keywords'] = 'These keywords are sent in the output of every page to indicate to search engines (etc) the key content of your site. They should be a comma separated list of words, and should not use HTML.';

$helptxt['secret_why_blank'] = 'For your security, your password and the answer to your secret question are encrypted so that the StoryBB software will never tell you, or anyone else, what they are.';
$helptxt['moderator_why_missing'] = 'Since moderation is done on a board-by-board basis, you have to make members moderators from the <a href="%1$s?action=admin;area=manageboards" target="_blank" rel="noopener">board management interface</a>.';

$helptxt['permissions_deny'] = 'Denying permissions can be useful when you want to take away permission from certain members. You can add a membergroup with a \'deny\'-permission to the members you wish to deny a permission.<br><br><strong>Use with care</strong>, a denied permission will stay denied no matter what other membergroups the member is in.';
$helptxt['membergroup_guests'] = 'The Guests membergroup is for all users that are not logged in.';
$helptxt['membergroup_regular_members'] = 'The Regular Members are all members that are logged in, but that have no primary membergroup assigned.';
$helptxt['membergroup_administrator'] = 'The administrator can, per definition, do anything and see any board. There are no permission settings for the administrator.';
$helptxt['membergroup_moderator'] = 'The Moderator membergroup is a special membergroup. Permissions and settings assigned to this group apply to moderators but only <em>on the boards they moderate</em>. Outside these boards they\'re just like any other member. Note that permissions for this group also apply to any group assigned to moderate a board.';

$helptxt['membergroup_badge'] = 'When people post, either in their account or their characters, it\'s possible to display an icon, or group of icons, next to their avatar as a visual guide as to special things on their account. For example, administrators usually have an icon by their avatar to show people that they are administrators. Moderators usually have something similar, too.<br>
<br>
Sometimes you just want a single interesting badge to show, other times you might want to show 5 of the same icons like stars or pips to show off what rank someone is. Either way, here is where you set the number of times the badge should be shown for this group, and what badge to show.<br>
<br>
You can always add new icons to this by uploading the images into the Themes/natural/images/membericons/ folder as .gif, .jpg or .png files and they will show up in this list. You can also have different images in different themes - just name the file with the same name and put it in the images/membericons/ folder of your theme.<br>
<br>
Of course, you don\'t have to have a badge - just untick the box and no badge will be shown for people in this group.<br>
<br>
For best results, images should probably be no larger than 150 pixels wide and 100 pixels high.';

$helptxt['avatar_action_too_large'] = 'This allows you to choose what to do with over-sized avatars that users put in as a URL.<br>
<ul class="normallist">
	<li>Don\'t allow it and tell the user - users have to put in a different avatar that is smaller</li>
	<li>Resize it in the users\'s browser - doesn\'t take up any server space, but might be slow for people on slow connections especially with big images</li>
	<li>Download it to the server - downloads it, resizes it to fit the maximum allowed size, and uses it; means images won\'t be very big (e.g. if someone tried to use a large photo as their avatar) but means it comes out of the forum\'s storage space</li>
</ul>';
$helptxt['avatar_download_png'] = 'PNGs are larger, but offer better quality compression. If this is unchecked, JPEG will be used instead - which is often smaller, but also of lesser or blurry quality.';

$helptxt['whytwoip'] = 'StoryBB uses various methods to detect user IP addresses. Usually these two methods result in the same address but in some cases more than one address may be detected. In this case StoryBB logs both addresses, and uses them both for ban checks (etc). You can click on either address to track that IP and ban if necessary.';

$helptxt['ban_cannot_post'] = 'The \'cannot post\' restriction turns the forum into read-only mode for the banned user. The user cannot create new topics, or reply to existing ones, send personal messages or vote in polls. The banned user can however still read personal messages and topics.<br><br>A warning message is shown to the users that are banned this way.';

$helptxt['birthday_email'] = 'Choose the index of the birthday email message to use. A preview will be shown in the Email Subject and Email Body fields.<br><strong>Note:</strong> Selecting this setting does not automatically enable birthday emails. To enable birthday emails use the <a href="%1$s?action=admin;area=scheduledtasks;%3$s=%2$s" target="_blank" rel="noopener">Scheduled Tasks</a> page and enable the birthday email task.';

$helptxt['move_topics_maintenance'] = 'This will allow you to move all the posts from one board to another board.';
$helptxt['maintain_reattribute_posts'] = 'You can use this function to attribute guest posts on your board to a registered member. This is useful if, for example, a user deleted their account and changed their mind and wished to have their old posts associated with their account.';
$helptxt['chmod_flags'] = 'You can manually set the permissions you wish to set the selected files to. To do this enter the chmod value as a numeric (octet) value. Note - these flags will have no effect on Microsoft Windows operating systems.';

$helptxt['postmod'] = 'This page allows members of the moderation team (with sufficient permissions) to approve any posts and topics before they are shown.';

$helptxt['field_show_enclosed'] = 'Encloses the user input between some text or html. This will allow you to add more instant message providers, images or an embed etc. For example:<br><br>
		&lt;a href="https://example.com/{INPUT}"&gt;&lt;img src="{DEFAULT_IMAGES_URL}/icon.png" alt="{INPUT}" /&gt;&lt;/a&gt;<br><br>
		Note that you can use the following variables:<br>
		<ul class="normallist">
			<li>{INPUT} - The input specified by the user.</li>
			<li>{SCRIPTURL} - Web address of forum.</li>
			<li>{IMAGES_URL} - Url to images directory in the users current theme.</li>
			<li>{DEFAULT_IMAGES_URL} - Url to the images directory in the default theme.</li>
		</ul>';

$helptxt['custom_mask'] = 'The input mask is important for your forum\'s security. Validating the input from a user can help ensure that data is not used in a way you do not expect. We have provided some simple regular expressions as hints.<br><br>
	<div class="smalltext" style="margin: 0 2em">
		&quot;~[A-Za-z]+~&quot; - Match all upper and lower case alphabet characters.<br>
		&quot;~[0-9]+~&quot; - Match all numeric characters.<br>
		&quot;~[A-Za-z0-9]{7}~&quot; - Match all upper and lower case alphabet and numeric characters seven times.<br>
		&quot;~[^0-9]?~&quot; - Forbid any number from being matched.<br>
		&quot;~^([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$~&quot; - Only allow 3 or 6 character hexcodes.<br>
	</div><br><br>
	Additionally, special metacharacters ?+*^$ and {xx} can be defined.
	<div class="smalltext" style="margin: 0 2em">
		? - None or one match of previous expression.<br>
		+ - One or more of previous expression.<br>
		* - None or more of previous expression.<br>
		{xx} - An exact number from previous expression.<br>
		{xx,} - An exact number or more from previous expression.<br>
		{,xx} - An exact number or less from previous expression.<br>
		{xx,yy} - An exact match between the two numbers from previous expression.<br>
		^ - Start of string.<br>
		$ - End of string.<br>
		\ - Escapes the next character.<br>
	</div><br><br>
	More information and advanced techniques may be found on the Internet.';

$helptxt['alert_pm_new'] = 'Notifications of new personal messages do not appear in the Alerts pane, but appear in the "My Messages" list instead.';

$helptxt['force_ssl'] = '<strong>Test SSL and HTTPS on your server properly before enabling this, it may cause your forum to become inaccessible.</strong> Enable maintenance mode if you are unable to access the forum after enabling this.<br><br><strong>Changing this setting will update your forum\'s primary URL, as well as the URLs for your theme files and images, smileys, and avatars, setting them to either http: or https: based on your selection. Customized URLs will not be affected.</strong>';
$helptxt['image_proxy_enabled'] = 'Required for embedding external images when in full SSL';
$helptxt['image_proxy_secret'] = 'Keep this a secret, protects your forum from hotlinking images. Change it in order to render current hotlinked images useless';
$helptxt['image_proxy_maxsize'] = 'Maximum image size that the SSL image proxy will cache: bigger images will be not be cached. Cached images are stored in your StoryBB cache folder, so make sure you have enough free space.';

$helptxt['field_reg_require'] = 'If this field is required during registration, it will also be required on profile changes.';

$helptxt['characters_ic_may_post'] = 'On a forum where you have a strong sense of immersion, you might want to ensure that "characters" can only post in in-character boards and never outside. Tick this box to enforce this.';
$helptxt['characters_ooc_may_post'] = 'On a forum where there is a strong divide between "in character" posts and "out of character" posts, you may wish to enforce that characters cannot post in the out of character areas - tick this box to enforce this.';
$helptxt['characters_ic_require_sheet'] = 'By default, the permissions setup does not prevent a just-made character from posting. Ticking this box ensures that for in-character posts to happen, the character must have an approved character sheet.';
$helptxt['enable_immersive_mode'] = 'Immersive mode is an optional way to run a roleplay forum that can be used to make users feel more like the site recognises their characters better. When immersive mode is enabled, the groups attached to each character are enforced, meaning that different characters may not be able to see all of the areas that the overall account theoretically could. For example, if roleplaying in a Harry Potter based universe, characters might have a Gryffindor group and a Slytherin group - and only Gryffindors can see the Gryffindor common room. This setting controls whether this is enforced or not.';


$helptxt['retention_policy_standard'] = 'The software keeps logs that include identifiers such as IP addresses. This list is the more general list of items that get pruned for privacy reasons after the given number of days. This includes (but is not limited to):<br>
	<ul class="normallist">
		<li>IP addresses in posts (unless the IP address is in a ban)</li>
		<li>IP address in moderation/admin/profile edits logs (unless banned)</li>
		<li>IP addresses that visited the site while banned</li>
		<li>IP addresses used by members when logging in (unless banned)</li>
		<li>IP address when commenting on moderation reports (unless banned)</li>
	</ul>';
$helptxt['retention_policy_sensitive'] = 'Sensitive items in logs are ones that generally do not need to be kept as long as the period of time in which they are relevant is usually shorter, and may be damaging if held onto for long periods. This includes (but is not limited to):<br>
	<ul class="normallist">
		<li>IP addresses in error log</li>
		<li>Session IDs in error log</li>
	</ul>';
