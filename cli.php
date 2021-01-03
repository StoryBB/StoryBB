#!/usr/bin/env php
<?php

/**
 * Command line runner for StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB project
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\ClassManager;
use StoryBB\Cli\App as CliApp;
use StoryBB\Container;
use Symfony\Component\Console;

define('STORYBB', 1);

error_reporting(E_ALL);
require_once __DIR__ . '/vendor/autoload.php';

$container = Container::instance();
App::start(__DIR__);

CliApp::build_container(App::get_global_config());

$app = new Console\Application;
foreach (ClassManager::get_classes_implementing('StoryBB\\Cli\\Command') as $command)
{
	$app->add($container->instantiate($command));
}
$app->run();
