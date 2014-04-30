<?php

/**
 * This file contains liking posts and displaying the list of who liked a post.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

class Likes
{
	protected $_js = false;
	protected $_error = false;
	protected $_type = '';
	protected $_content = 0;
	protected $_response = array();
	protected $_view = false;
	protected $_canLike = false;
	protected $_redirect = '';

	/**
	 * @var array $_validLikes mostly used for external integration, needs to be filled as an array with the following keys:
	 * 'can_see' boolean|string whether or not the current user can see the like. return a boolean true if the user can, otherwise return a string, the string will be used as key in a regular $txt language error var. The code assumes you already loaded your language file.
	 * 'can_like' boolean|string whether or not the current user can actually like your content. Return a boolean true if the user can, otherwise return a string, the string will be used as key in a regular $txt language error var. The code assumes you already loaded your language file.
	 * 'redirect' string To add support for non JS users, It is highly encouraged to set a valid url to redirect the user to, if you don't provide any the code will redirect the user to the main page.
	 */
	protected $_validLikes = array();

	public function __construct()
	{
		$this->_type = isset($_GET['ltype']) ? $_GET['ltype'] : '';
		$this->_content = isset($_GET['like']) ? (int) $_GET['like'] : 0;
		$this->_view = isset($this->_view) ? $this->_view : false;
		$this->_js = isset($_GET['js']) ? true : false;
	}

/**
 * The main handler. Verifies permissions (whether the user can see the content in question)
 * before either liking/unliking or spitting out the list of likers.
 * Accessed from index.php?action=likes
 */
	protected function init()
	{
		global $context, $smcFunc;

		$this->check();

		if ($this->_error)
			return $this->setResponse();

		// So at this point, whatever type of like the user supplied and the item of content in question,
		// we know it exists, now we need to figure out what we're doing with that.
		if ($this->_view)
			$this->viewLikes();

		else
		{
			// Only registered users may actually like content.
			is_not_guest();
			checkSession('get');
			$this->issueLike();
		}

		$this->setResponse();
	}

	protected function check()
	{
		global $smcFunc, $context;

		// Zerothly, they did indicate some kind of content to like, right?
		preg_match('~^([a-z0-9\-\_]{1,6})~i', $this->_type, $matches);
		$this->_type = isset($matches[1]) ? $matches[1] : '';

		if ($this->_type == '' || $this->_content <= 0)
			return $this->_error = $this->_view ? 'cannot_view_likes' : 'cannot_like_content';

		// First we need to verify if the user can see the type of content or not. This is set up to be extensible,
		// so we'll check for the one type we do know about, and if it's not that, we'll defer to any hooks.
		if ($this->_type == 'msg')
		{
			// So we're doing something off a like. We need to verify that it exists, and that the current user can see it.
			// Fortunately for messages, this is quite easy to do - and we'll get the topic id while we're at it, because
			// we need this later for other things.
			$request = $smcFunc['db_query']('', '
				SELECT m.id_topic
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
				WHERE {query_see_board}
					AND m.id_msg = {int:msg}',
				array(
					'msg' => $this->_content,
				)
			);
			if ($smcFunc['db_num_rows']($request) == 1)
				list ($id_topic) = $smcFunc['db_fetch_row']($request);

			$smcFunc['db_free_result']($request);
			if (empty($id_topic))
				return $this->_error = $this->_view ? 'cannot_view_likes' : 'cannot_like_content';

			// So we know what topic it's in and more importantly we know the user can see it.
			// If we're not viewing, we need some info set up.
			if (!isset($this->_view))
			{
				$context['flush_cache'] = 'likes_topic_' . $id_topic . '_' . $context['user']['id'];
				$context['redirect_from_like'] = 'topic=' . $id_topic . '.msg' . $this->_content . '#msg' . $this->_content;
				add_integration_function('integrate_issue_like', 'msg_issue_like', '', false);
			}
		}
		else
		{
			// Modders: This will give you whatever the user offers up in terms of liking, e.g. $this->_type=msg, $this->_content=1
			// When you hook this, check $this->_type first. If it is not something your mod worries about, return false.
			// Otherwise, determine (however you need to) that the user can see the relevant liked content (and it exists).
			// If the user cannot see it, return false. If the user can see it and can like it, you MUST return your $this->_type back.
			// See also issueLike() for further notes.
			$can_like = call_integration_hook('integrate_valid_likes', array($this->_type, $this->_content));

			$found = false;
			if (!empty($can_like))
			{
				$can_like = (array) $can_like;
				foreach ($can_like as $result)
				{
					if ($result !== false)
					{
						$this->_type = $result;
						$found = true;
						break;
					}
				}
			}

			if (!$found)
				return $this->_error = $this->_view ? 'cannot_view_likes' : 'cannot_like_content';
		}
	}

/**
 * @param string $this->_type The type of content being liked
 * @param integer $this->_content The ID of the content being liked
 */
function issueLike()
{
	global $context, $smcFunc;

	// Safety first!
	if (empty($this->_type) || empty($this->_content))
		return $this->_error = $this->_view ? 'cannot_view_likes' : 'cannot_like_content';

	// Do we already like this?
	$request = $smcFunc['db_query']('', '
		SELECT content_id, content_type, id_member
		FROM {db_prefix}user_likes
		WHERE content_id = {int:like_content}
			AND content_type = {string:like_type}
			AND id_member = {int:id_member}',
		array(
			'like_content' => $this->_content,
			'like_type' => $this->_type,
			'id_member' => $context['user']['id'],
		)
	);
	$already_liked = $smcFunc['db_num_rows']($request) != 0;
	$smcFunc['db_free_result']($request);

	if ($already_liked)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}user_likes
			WHERE content_id = {int:like_content}
				AND content_type = {string:like_type}
				AND id_member = {int:id_member}',
			array(
				'like_content' => $this->_content,
				'like_type' => $this->_type,
				'id_member' => $context['user']['id'],
			)
		);
	}
	else
	{
		// Insert the like.
		$smcFunc['db_insert']('insert',
			'{db_prefix}user_likes',
			array('content_id' => 'int', 'content_type' => 'string-6', 'id_member' => 'int', 'like_time' => 'int'),
			array($this->_content, $this->_type, $context['user']['id'], time()),
			array('content_id', 'content_type', 'id_member')
		);

		// Add a background task to process sending alerts.
		$smcFunc['db_insert']('insert',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/Likes-Notify.php', 'Likes_Notify_Background', serialize(array(
				'content_id' => $this->_content,
				'content_type' => $this->_type,
				'sender_id' => $context['user']['id'],
				'sender_name' => $context['user']['name'],
				'time' => time(),
			)), 0),
			array('id_task')
		);
	}

	// Now, how many people like this content now? We *could* just +1 / -1 the relevant container but that has proven to become unstable.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_member)
		FROM {db_prefix}user_likes
		WHERE content_id = {int:like_content}
			AND content_type = {string:like_type}',
		array(
			'like_content' => $this->_content,
			'like_type' => $this->_type,
		)
	);
	list ($num_likes) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Sometimes there might be other things that need updating after we do this like.
	call_integration_hook('integrate_issue_like', array($this->_type, $this->_content, $num_likes));

	// Now some clean up. This is provided here for any like handlers that want to do any cache flushing.
	// This way a like handler doesn't need to explicitly declare anything in integrate_issue_like, but do so
	// in integrate_valid_likes where it absolutely has to exist.
	if (!empty($context['flush_cache']))
		cache_put_data($context['flush_cache'], null);

	if (!empty($context['redirect_from_like']))
		redirectexit($context['redirect_from_like']);
	else
		redirectexit(); // Because we have to go *somewhere*.
}

/**
 * Callback attached to integrate_issue_like.
 * Partly it indicates how it's supposed to work and partly it deals with updating the count of likes
 * attached to this message now.
 * @param string $this->_type The type of content being liked - should always be 'msg'
 * @param int $this->_content The ID of the post being liked
 * @param int $num_likes The number of likes this message has received
 */
function msg_issue_like($this->_type, $this->_content, $num_likes)
{
	global $smcFunc;

	if ($this->_type !== 'msg')
		return;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET likes = {int:num_likes}
		WHERE id_msg = {int:id_msg}',
		array(
			'id_msg' => $this->_content,
			'num_likes' => $num_likes,
		)
	);

	// Note that we could just as easily have cleared the cache here, or set up the redirection address
	// but if your liked content doesn't need to do anything other than have the record in smf_user_likes,
	// there's no point in creating another function unnecessarily.
}

/**
 * This is for viewing the people who liked a thing.
 * Accessed from index.php?action=likes;view and should generally load in a popup.
 * We use a template for this in case themers want to style it.
 * @param string $this->_type The type of content being liked
 * @param integer $this->_content The ID of the content being liked
 */
function viewLikes($this->_type, $this->_content)
{
	global $smcFunc, $txt, $context, $memberContext;

	// Firstly, load what we need. We already know we can see this, so that's something.
	$context['likers'] = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_member, like_time
		FROM {db_prefix}user_likes
		WHERE content_id = {int:like_content}
			AND content_type = {string:like_type}
		ORDER BY like_time DESC',
		array(
			'like_content' => $this->_content,
			'like_type' => $this->_type,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['likers'][$row['id_member']] = array('timestamp' => $row['like_time']);

	// Now to get member data, including avatars and so on.
	$members = array_keys($context['likers']);
	$loaded = loadMemberData($members);
	if (count($loaded) != count($members))
	{
		$members = array_diff($members, $loaded);
		foreach ($members as $not_loaded)
			unset ($context['likers'][$not_loaded]);
	}

	foreach ($context['likers'] as $liker => $dummy)
	{
		$loaded = loadMemberContext($liker);
		if (!$loaded)
		{
			unset ($context['likers'][$liker]);
			continue;
		}

		$context['likers'][$liker]['profile'] = &$memberContext[$liker];
		$context['likers'][$liker]['time'] = !empty($dummy['timestamp']) ? timeformat($dummy['timestamp']) : '';
	}

	$count = count($context['likers']);
	$title_base = isset($txt['likes_' . $count]) ? 'likes_' . $count : 'likes_n';
	$context['page_title'] = strip_tags(sprintf($txt[$title_base], '', comma_format($count)));

	// Lastly, setting up for display
	loadTemplate('Likes');
	loadLanguage('Help'); // for the close window button
	$context['template_layers'] = array();
	$context['sub_template'] = 'popup';
}

/**
 * What's this?  I dunno, what are you talking about?  Never seen this before, nope.  No sir.
 */
function BookOfUnknown()
{
	global $context, $scripturl;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>The Book of Unknown, ', @$_GET['verse'] == '2:18' ? '2:18' : '4:16', '</title>
		<style type="text/css">
			em
			{
				font-size: 1.3em;
				line-height: 0;
			}
		</style>
	</head>
	<body style="background-color: #444455; color: white; font-style: italic; font-family: serif;">
		<div style="margin-top: 12%; font-size: 1.1em; line-height: 1.4; text-align: center;">';

	if (!isset($_GET['verse']) || ($_GET['verse'] != '2:18' && $_GET['verse'] != '22:1-2'))
		$_GET['verse'] = '4:16';

	if ($_GET['verse'] == '2:18')
		echo '
			Woe, it was that his name wasn\'t <em>known</em>, that he came in mystery, and was recognized by none.&nbsp;And it became to be in those days <em>something</em>.&nbsp; Something not yet <em id="unknown" name="[Unknown]">unknown</em> to mankind.&nbsp; And thus what was to be known the <em>secret project</em> began into its existence.&nbsp; Henceforth the opposition was only <em>weary</em> and <em>fearful</em>, for now their match was at arms against them.';
	elseif ($_GET['verse'] == '4:16')
		echo '
			And it came to pass that the <em>unbelievers</em> dwindled in number and saw rise of many <em>proselytizers</em>, and the opposition found fear in the face of the <em>x</em> and the <em>j</em> while those who stood with the <em>something</em> grew stronger and came together.&nbsp; Still, this was only the <em>beginning</em>, and what lay in the future was <em id="unknown" name="[Unknown]">unknown</em> to all, even those on the right side.';
	elseif ($_GET['verse'] == '22:1-2')
		echo '
			<p>Now <em>behold</em>, that which was once the secret project was <em id="unknown" name="[Unknown]">unknown</em> no longer.&nbsp; Alas, it needed more than <em>only one</em>, but yet even thought otherwise.&nbsp; It became that the opposition <em>rumored</em> and lied, but still to no avail.&nbsp; Their match, though not <em>perfect</em>, had them outdone.</p>
			<p style="margin: 2ex 1ex 0 1ex; font-size: 1.05em; line-height: 1.5; text-align: center;">Let it continue.&nbsp; <em>The end</em>.</p>';

	echo '
		</div>
		<div style="margin-top: 2ex; font-size: 2em; text-align: right;">';

	if ($_GET['verse'] == '2:18')
		echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="', $scripturl, '?action=about:unknown;verse=4:16" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 2:18</span>';
	elseif ($_GET['verse'] == '4:16')
		echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="', $scripturl, '?action=about:unknown;verse=22:1-2" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 4:16</span>';
	elseif ($_GET['verse'] == '22:1-2')
		echo '
			from <span style="font-family: Georgia, serif;"><strong>The Book of Unknown</strong>, 22:1-2</span>';

	echo '
		</div>
	</body>
</html>';

	obExit(false);
}
}
?>