<?php

/**
 * This hook runs when a post is created.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Integratable\Reply;

use StoryBB\Hook\AbstractIntegratable;
use StoryBB\Hook\Integratable;
use StoryBB\Hook\Integratable\CharacterDetails;

/**
 * This hook runs when a post is created.
 */
class Created extends AbstractIntegratable implements Integratable
{
	use CharacterDetails;

	protected $vars = [];

	public function __construct($msgOptions, $topicOptions, $posterOptions)
	{
		global $txt, $scripturl;

		$subject = $msgOptions['subject'];
		if (!empty($txt['response_prefix']) && strpos($subject, $txt['response_prefix']) === 0)
		{
			$subject = substr($subject, strlen($txt['response_prefix']));
		}

		$this->vars = [
			'topic_link' => $scripturl . '?topic=' . $topicOptions['id'] . '.msg' . $msgOptions['id'] . '#msg' . $msgOptions['id'],
			'topic_subject' => html_entity_decode($subject, ENT_QUOTES, 'UTF-8'),
			'posted_by' => $this->get_character_details((int) $posterOptions['id'], (int) $posterOptions['char_id'] ?? 0),
			'msgOptions' => $msgOptions,
			'topicOptions' => $topicOptions,
			'posterOptions' => $posterOptions,
		];
	}
}
