<?php

/**
 * This hook runs when a character sheet is approved.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Integratable\Character;

use StoryBB\Hook\AbstractIntegratable;
use StoryBB\Hook\Integratable;
use StoryBB\Hook\Integratable\CharacterDetails;

/**
 * This hook runs when a post is created.
 */
class Approved extends AbstractIntegratable implements Integratable
{
	use CharacterDetails;

	protected $vars = [];

	public function __construct(int $account_id, int $character_id, string $raw_sheet)
	{
		global $scripturl;

		$this->vars = [
			'account_id' => $account_id,
			'character_id' => $character_id,
			'character' => $this->get_character_details($account_id, $character_id),
			'character_sheet' => $scripturl . '?action=profile;u=' . $account_id . ';area=character_sheet;char=' . $character_id,
			'sheet_body' => $raw_sheet,
		];
	}
}
