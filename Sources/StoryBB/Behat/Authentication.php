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

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Defines application features from the specific context.
 */
class Authentication extends RawMinkContext implements Context
{
	/**
	 * Log in as a user
	 * @When I log in as :user
	 * @param string $user The username to log in as.
	 */
	public function iLogInAs(string $user)
	{
		$this->visitPath('index.php?action=login');
		$page = $this->getSession()->getPage();
		$page->fillField('user', $user);
		$page->fillField('passwrd', 'password');
		$page->pressButton('Login');
	}

	/**
	 * Log out as a user
	 * @When I log out
	 */
	public function iLogOut()
	{
		$page = $this->getSession()->getPage();
		$main_menu = $page->find('css', '#main_menu');
		if (!$main_menu)
		{
			$exception_msg = 'Main menu (#main_menu) could not be found in the page';
			throw new ElementNotFoundException($this->getSession(), 'css', null, $exception_msg);
		}
		$link = $main_menu->findLink('Logout');
		if (!$link)
		{
			$exception_msg = 'Logout link in the main menu was not found';
			throw new ElementNotFoundException($this->getSession(), 'link', null, $exception_msg);
		}
		$link->click();
		$this->getSession()->reset();
	}
}
