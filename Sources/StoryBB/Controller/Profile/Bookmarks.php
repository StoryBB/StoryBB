<?php

/**
 * Displays the bookmarks page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\Bookmark;

class Bookmarks extends AbstractProfileController
{
	protected function get_token_name()
	{
		return str_replace('%u', $this->params['u'], 'profile-bm%u');
	}

	public function display_action()
	{
		global $context, $sourcedir, $txt, $scripturl;

		$memID = $this->params['u'];

		require_once($sourcedir . '/Subs-List.php');
		
		createToken($this->get_token_name(), 'post');

		$listOptions = [
			'id' => 'ooc_bookmarks',
			'title' => $txt['bookmarks_ooc'],
			'no_items_label' => $txt['no_topics_bookmarked'],
			'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=bookmarks',
			'default_sort_col' => 'last_post',
			'get_items' => [
				'function' => [Bookmark::class, 'get_bookmarks'],
				'params' => [
					(int) $context['user']['id'],
					0,
				]
			],
			'columns' => [
				'subject' => [
					'header' => [
						'value' => $txt['bookmarked_topic'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function($topic) use ($txt)
						{
							$link = $topic['link'];

							if ($topic['new'])
							{
								$link .= ' <a href="' . $topic['new_href'] . '" class="new_posts"></a>';
							}

							$link .= '<br><span class="smalltext"><em>' . $txt['in'] . ' ' . $topic['board_link'] . '</em></span>';

							return $link;
						},
					],
					'sort' => [
						'default' => 'ms.subject',
						'reverse' => 'ms.subject DESC',
					],
				],
				'started_by' => [
					'header' => [
						'value' => $txt['started_by'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'poster_link',
					],
					'sort' => [
						'default' => 'real_name_col',
						'reverse' => 'real_name_col DESC',
					],
				],
				'last_post' => [
					'header' => [
						'value' => $txt['last_post'],
						'class' => 'lefttext',
					],
					'data' => [
						'sprintf' => [
							'format' => '<span class="smalltext">%1$s<br>' . $txt['by'] . ' %2$s</span>',
							'params' => [
								'updated' => false,
								'poster_updated_link' => false,
							],
						],
					],
					'sort' => [
						'default' => 'ml.id_msg DESC',
						'reverse' => 'ml.id_msg',
					],
				],
				'delete' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'style' => 'width: 4%;',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="bookmark[]" value="%1$d">',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => $scripturl . '?action=profile;area=bookmarks',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					'u' => $memID,
					$context['session_var'] => $context['session_id'],
				],
				'token' => $this->get_token_name(),
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => '<button type="submit" name="remove_bookmarks" value="remove_bookmarks" class="button">' . $txt['remove_bookmarks'] . '</button>',
					'class' => 'floatright',
				],
			],
		];

		createList($listOptions);

		$listOptions['id'] = 'ic_bookmarks';
		$listOptions['title'] = $txt['bookmarks_ic'];
		$listOptions['get_items']['params'][1] = 1;

		$listOptions['default_sort_col'] .= '_ic';

		foreach ($listOptions['columns'] as $columnkey => $column)
		{
			$listOptions['columns'][$columnkey . '_ic'] = $column;
			unset($listOptions['columns'][$columnkey]);
		}

		createList($listOptions);

		$context['sub_template'] = 'profile_bookmarks';
	}

	public function post_action()
	{
		global $context;

		if (!empty($_POST['remove_bookmarks']) && !empty($_POST['bookmark']))
		{
			checkSession();
			validateToken($this->get_token_name());

			Bookmark::unbookmark_topics((int) $context['user']['id'], (array) $_POST['bookmark']);
		}

		return $this->display_action();
	}
}
