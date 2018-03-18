# Getting Behat running on your own machine

## Things you need, to make you go.

PHP - needs to be PHP 7.0 or higher, available from the command line. Go to your
favourite command line and try `php -v`. If it fails with an error, you'll need
to figure out how to get that going. If you get some output, the first line will
be the version number. Needs to be 7.0 or higher.

MySQL - while StoryBB does support Postgres, the Behat support currently does
not support anything other than MySQL. It will need to be at least MySQL 5.1,
it'll need to be running on its usual port 3306, with a user called root who has
no password. If you're using XAMPP or similar, this should be how it's set up.
(The root user will be able to make you the storybb_behat database etc.)

Composer - your PHP setup will need Composer to load the dev tools. We
intentionally don't ship with the dev tools installed because that's an awful
lot of code you mostly won't need to care about.

## Getting it set up

All of these instructions assume you're creating an instance on your local
machine where you have a copy of StoryBB checked out but that it is not running
an existing installation of StoryBB - you can't run the Behat setup from the
exact same place to avoid breaking your normal site/environment.

* MySQL should be running on port 3306
* run `composer install --dev` from the main StoryBB directory - this will
  install all of the developer tools including Behat and all its libraries
* Start a PHP development server on port 8000 - go to the main StoryBB folder
  and run `php -S localhost:8000`
* From the main StoryBB folder, run `vendor/bin/behat --init` (if you're on
  Windows, make that `vendor\bin\behat --init`) which will do some general setup

Lastly, whenever you want to run some actual tests, `vendor/bin/behat` is your
friend. Without any extra parameters, it will just try to run every single test
file in the other/behat folder.

## Will I need to change anything?

If you're not on Windows, you might want to change behat.yml slightly, because
in the default configuration if a page throws an error, the HTML of that page
is spat out to Windows' Notepad to see it there and then.

## Is that it?

Yes. This isn't trying to be a thorough and super-robust environment (yet) but
enough to be able to run some tests to prove things work as expected.

Between every scenario, the database will be refreshed back to a fresh installed
state so tests don't interfere with each other.

If a test fails, it's possible the Settings.php file from the main folder won't
be cleaned up and subsequent runs will think you have an installation. For those
cases, it would be safe to remove the Settings.php file and let it be rebuilt.

## My setup is a bit different...

If you need to use a different database or different port or something - or you
need to give the database a different user, edit the other/Settings_behat.php
file before a run begins; this is copied to become Settings.php for the setup
process and subsequent running.