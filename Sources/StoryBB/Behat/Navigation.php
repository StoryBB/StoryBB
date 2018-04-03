<?php

/**
 * This class handles behaviours for Behat tests within StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
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
	 * @When I go to the board index
	 */
	public function iGoToTheBoardIndex()
	{
		$this->visitPath('index.php');
	}
}

?>