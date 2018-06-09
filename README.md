# StoryBB
[![Build Status](https://travis-ci.org/StoryBB/StoryBB.svg?branch=master)]https://travis-ci.org/StoryBB/StoryBB)

This is StoryBB! It was forked from SMF.
The software is licensed under [BSD 3-clause license](https://opensource.org/licenses/BSD-3-Clause).

Contributions to documentation are licensed under [CC-by-SA 3](https://creativecommons.org/licenses/by-sa/3.0). Third party libraries or sets of images, are under their own licenses.

## Notes:

Feel free to fork this repository and make your desired changes.

To get started, <a href="https://www.clahub.com/agreements/StoryBB/StoryBB">sign the Contributor License Agreement</a>.

## Branches organization:
* ***master*** - is the main branch, from where we release
* feature branches exist for working on small features. Please branch from where you intend to merge into. Hotfixes can bypass the release branch. 

## How to contribute:
* fork the repository. If you are not used to Github, please check out [fork a repository](https://help.github.com/fork-a-repo).
* branch your repository, to commit the desired changes.
* send a pull request to us. If you have not signed the contributor agreement, the bot will remind you to do so at this time

## How to submit a pull request:
* Just do a PR against the master branch with why it seems like a good idea

## Requirements
* MySQL 5.0.3 or PostgreSQL 9.2 or higher
* PHP 7.0 or higher

#### Required PHP extensions
* cURL
* GD
* MySQLi if using MySQL
* libpq (the base PostgreSQL extension) if using Postgres

#### Optional PHP extensions
* mbstring
* iconv
* Imagick or MagickWand for ImageMagick support
* APC/APCu/memcache/SQLite 3/xcache/Zend SHM cache

Please, feel free to play around. That's what we're doing. ;)