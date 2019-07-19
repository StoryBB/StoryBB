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
class Character extends RawMinkContext implements Context
{
	/**
	 * Change who the current character is in the Behat session.
	 *
	 * @When I switch character to :character
	 * @param string $character A character name to switch to
	 * @throws ElementNotFoundException if the switch-character link couldn't be found
	 */
	public function iSwitchCharacterTo($character)
	{
		// So we need to go to the profile page. We (probably) don't have JavaScript.
		$this->visitPath('index.php?action=profile');

		// Now to find the menu of all the characters.
		$page = $this->getSession()->getPage();
		$links_to_characters = $page->findAll('xpath', "//div[@id='main_content_section']//div[contains(@class, 'generic_menu')]//*[a='Characters']/ul/li[@class='subsections']/a");

		if (empty($links_to_characters))
		{
			throw new ElementNotFoundException($this->getSession(), 'css', null, 'any links to characters');
		}
		foreach ($links_to_characters as $link)
		{
			if ($character_link = $link->find('named', ['content', $character]))
			{
				$character_link->click();
				$page = $this->getSession()->getPage();
				$switch_link = $page->find('named', ['link', 'Switch to this character']);
				$switch_link->click();
				return;
			}
		}
		throw new ElementNotFoundException($this->getSession(), 'link', null, $character);
	}
}
