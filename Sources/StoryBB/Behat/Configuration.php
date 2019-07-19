<?php

/**
 * Manage and maintain the boards and categories of the forum.
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
class Configuration extends RawMinkContext implements Context
{
	/**
	 * Sets some global settings
	 * @When the following settings are set:
	 * @param TableNode $table The list of settings and their values to be set
	 */
	public function theFollowingSettingsAreSet(TableNode $table)
	{
		$settings = $table->getHash();
		foreach ($settings as $setting)
		{
			if (isset($setting['variable'], $setting['value']))
			{
				updateSettings([$setting['variable'] => $setting['value']]);
			}
		}
	}
}
