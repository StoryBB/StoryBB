<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

/**
 * The massive XML for previewing posts.
 */
function template_post()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<smf>
	<preview>
		<subject><![CDATA[', $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '" topic_locked="', $context['locked'] ? '1' : '0', '">';
	if (!empty($context['post_error']))
		foreach ($context['post_error'] as $message)
			echo '
		<error><![CDATA[', cleanXml($message), ']]></error>';
	echo '
		<caption name="guestname" class="', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? 'error' : '', '" />
		<caption name="email" class="', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? 'error' : '', '" />
		<caption name="subject" class="', isset($context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset($context['post_error']['no_question']) ? 'error' : '', '" />', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? '
		<post_error />' : '', '
	</errors>
	<last_msg>', isset($context['topic_last_message']) ? $context['topic_last_message'] : '0', '</last_msg>';

	if (!empty($context['previous_posts']))
	{
		echo '
	<new_posts>';
		foreach ($context['previous_posts'] as $post)
			echo '
		<post id="', $post['id'], '">
			<time><![CDATA[', $post['time'], ']]></time>
			<poster><![CDATA[', cleanXml($post['poster']), ']]></poster>
			<message><![CDATA[', cleanXml($post['message']), ']]></message>
			<is_ignored>', $post['is_ignored'] ? '1' : '0', '</is_ignored>
		</post>';
		echo '
	</new_posts>';
	}

	echo '
</smf>';
}

/**
 * All the XML for previewing a PM
 */
function template_pm()
{
	global $context, $txt;

	// @todo something could be removed...otherwise it can be merged again with template_post
	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<smf>
	<preview>
		<subject><![CDATA[', $txt['preview'], ' - ', !empty($context['preview_subject']) ? $context['preview_subject'] : $txt['no_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '">';
	if (!empty($context['post_error']['messages']))
		foreach ($context['post_error']['messages'] as $message)
			echo '
		<error><![CDATA[', cleanXml($message), ']]></error>';

	echo '
		<caption name="to" class="', isset($context['post_error']['no_to']) ? 'error' : '', '" />
		<caption name="bbc" class="', isset($context['post_error']['no_bbc']) ? 'error' : '', '" />
		<caption name="subject" class="', isset($context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset($context['post_error']['no_question']) ? 'error' : '', '" />', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? '
		<post_error />' : '', '
	</errors>';

	echo '
</smf>';
}

/**
 * The XML for previewing a warning
 */
function template_warning()
{
	global $context;

	// @todo something could be removed...otherwise it can be merged again with template_post
	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<smf>
	<preview>
		<subject><![CDATA[', $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '">';
	if (!empty($context['post_error']['messages']))
		foreach ($context['post_error']['messages'] as $message)
			echo '
		<error><![CDATA[', cleanXml($message), ']]></error>';

	echo '
	</errors>';

	echo '
</smf>';
}

/**
 * This is just to hold off some errors if people are stupid.
 */
if (!function_exists('template_menu'))
{
	function template_menu()
	{
	}
}

?>