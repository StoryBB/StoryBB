<?php

/**
 * This class handles behaviours for Behat tests within StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Behat;

use StoryBB\Behat;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\DriverException;
use WebDriver\Exception\NoSuchElement;
use WebDriver\Exception\StaleElementReference;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Defines application features from the specific context.
 */
class Navigation extends RawMinkContext implements Context
{
	/**
	 * Visit the forum homepage - the board index - in this unit test
	 * @When I go to the board index
	 */
	public function iGoToTheBoardIndex()
	{
		$this->visitPath('index.php');
	}

	/**
	 * Navigate to a specific board in this test
	 * @When I go to :boardname board
	 * @param string $boardname The name of the board (unescaped) to visit in this unit test
	 * @throws ExpectationException if the board could not be visited
	 */
	public function iGoToBoard($boardname)
	{
		global $smcFunc;

		// Find the board id from its name.
		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}boards
			WHERE name = {string:board}',
			[
				'board' => $smcFunc['htmlspecialchars']($boardname, ENT_QUOTES)
			]
		);
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			$smcFunc['db_free_result']($request);
			throw new ExpectationException('Board "' . $boardname . '" was not found', $this->getSession());
		}
		if ($smcFunc['db_num_rows']($request) > 1)
		{
			$smcFunc['db_free_result']($request);
			throw new ExpectationException('Board "' . $boardname . '" matched multiple boards; cannot disambiguate', $this->getSession());
		}
		list ($id_board) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$this->visitPath('index.php?board=' . $id_board . '.0');
	}

	/**
	 * Navigate to a specific topic in this test
	 * @When I go to :topicname topic
	 * @param string $topicname The subject of a topic (unescaped) to visit in this unit test
	 * @throws ExpectationException if the topic could not be visited
	 */
	public function iGoToTopic($topicname)
	{
		global $smcFunc;

		// Find the board id from its name.
		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic
			FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE m.subject = {string:topic}',
			[
				'topic' => $smcFunc['htmlspecialchars']($topicname, ENT_QUOTES)
			]
		);
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			$smcFunc['db_free_result']($request);
			throw new ExpectationException('Topic "' . $topicname . '" was not found', $this->getSession());
		}
		if ($smcFunc['db_num_rows']($request) > 1)
		{
			$smcFunc['db_free_result']($request);
			throw new ExpectationException('Topic "' . $topicname . '" matched multiple topics; cannot disambiguate', $this->getSession());
		}
		list ($id_topic) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$this->visitPath('index.php?topic=' . $id_topic . '.0');
	}
}
