<?php

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
     * @When the following settings are set:
     */
    public function theFollowingSettingsAreSet(TableNode $table)
    {
    	$settings = $table->getHash();
    	foreach ($settings as $setting) {
    		updateSettings([$setting->variable_name => $setting->value]);
    	}
    }
}

?>