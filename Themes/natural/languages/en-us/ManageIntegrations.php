<?php

/**
 * This file contains language strings for the integrations area.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

$txt['no_integrations'] = 'No integrations currently configured.';
$txt['integrations_configured'] = 'The following integrations are currently configured:';
$txt['add_integration'] = 'Add integration';
$txt['add_integration_name'] = 'Add integration: %1$s';
$txt['edit_integration_name'] = 'Edit integration: %1$s';
$txt['this_integration_supports'] = 'This integration supports the following actions:';
$txt['back_to_integrations'] = '&larr; Back to integrations';
$txt['integration_to'] = 'Integration to';
$txt['integration_triggers'] = 'Triggers when';

$txt['active'] = 'Active';
$txt['integration_is_active'] = 'Integration is active?';

$txt['integration_topic_created'] = 'When a topic is created';
$txt['integration_reply_created'] = 'When a reply is created';
$txt['integration_character_approved'] = 'When a character is approved for the first time';

$txt['in_all_character_boards'] = 'in all character boards';
$txt['in_all_ooc_boards'] = 'in all out-of-character boards';
$txt['in_x_boards'][1] = '1 board';
$txt['in_x_boards']['x'] = '%1$s boards';

$txt['integration_discord_webhook_url'] = 'Discord webhook URL:';
$txt['integration_discord_webhook_url_sublabel'] = 'This will likely begin with https://discordapp.com/api/webhooks/';
$txt['integration_discord_topic_created_message'] = 'Message to post in Discord when a new topic is created:';
$txt['integration_discord_message_sublabel'] = 'Use {$subject} for the topic subject, {$link} for the link to it. Discord supports using Markdown links, such as [Example link](http://example.com)';
$txt['integration_discord_reply_created_message'] = 'Message to post in Discord when a new reply is created:';
$txt['integration_discord_topic_boards'] = 'Which boards should be used to post new topics to Discord?';
$txt['integration_discord_reply_boards'] = 'Which boards should be used to post new replies to Discord?';
$txt['integration_discord_embed_link'] = 'Embed a preview of the message?';
$txt['integration_discord_embed_colour'] = 'If embedding, what color to use for the left-hand edge?';
$txt['integration_discord_character_approved_message'] = 'What message to post in Discord when a new character is approved:';
$txt['integration_discord_character_approved_message_sublabel'] = 'Use {$character_name} for the character name, {$character_link} for the link to the character profile, and {$character_sheet_link} for the link to the sheet itself. Discord supports using Markdown links, such as [Example link](http://example.com)';

$txt['field_is_required'] = 'This field is required.';
$txt['please_enter_valid_color'] = 'Please enter a valid color.';
$txt['please_enter_valid_url'] = 'Please enter a valid URL.';
$txt['following_errors'] = 'The following errors were found:';
