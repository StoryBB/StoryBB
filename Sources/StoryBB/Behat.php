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

namespace StoryBB;

use StoryBB\Database\AdapterFactory;
use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use RuntimeException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Defines application features from the specific context.
 */
class Behat extends RawMinkContext implements Context
{
	/**
	 * Initializes context.
	 *
	 * Every scenario gets its own context instance.
	 * You can also pass arbitrary arguments to the
	 * context constructor through behat.yml.
	 */
	public function __construct()
	{
	}

	/**
	 * Initialise StoryBB and do a fresh database install.
	 *
	 * @BeforeSuite
	 */
	public static function init_storybb()
	{
		// Check if StoryBB is already installed or not.
		if (file_exists(__DIR__ . '/../../Settings.php'))
		{
			die('You appear to have StoryBB already installed. Aborting to avoid damage to install.');
		}

		// Check if the web server is running or not.
		// $connection = @fsockopen('localhost', '8000');
		// if (empty($connection)) {
		//     die('PHP server is not running on localhost:8000');
		// }

		// Now make the installation.
		$version = '1-0';
		$files = [
			['Settings_behat.php', 'Settings.php'],
			'install_' . $version . '_mysql.sql',
		];
		foreach ($files as $file)
		{
			if (is_array($file))
			{
				copy(__DIR__ . '/../../other/' . $file[0], __DIR__ . '/../../' . $file[1]);
			}
			else
			{
				copy(__DIR__ . '/../../other/' . $file, __DIR__ . '/../../' . $file);
			}
		}

		global $txt, $databases, $incontext, $smcFunc, $sourcedir, $boarddir, $boardurl;
		global $db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_type;
		require_once(__DIR__ . '/../../Settings.php');
		$smcFunc = [];
		define('STORYBB', 1);

		require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');
		require_once($boarddir . '/Themes/default/languages/en-us/Install.php');
		$txt['english_name'] = 'English';
		$txt['native_name'] = 'English';
		$txt['lang_locale'] = 'en-US';
		$txt['lang_rtl'] = false;

		$db_connection = sbb_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, ['dont_select_db' => true]);

		$smcFunc['db'] = AdapterFactory::get_adapter($db_type);
		$smcFunc['db']->set_prefix($db_prefix);
		$smcFunc['db']->set_server($db_server, $db_name, $db_user, $db_passwd);
		$smcFunc['db']->connect($options);

		if (empty($db_connection)) {
			die('Database could not be connected - error given: ' . $smcFunc['db_error']());
		}
		db_extend('packages');

		// Make a database.
		$smcFunc['db_query']('', "
			DROP DATABASE IF EXISTS `$db_name`",
			[
				'security_override' => true,
				'db_error_skip' => true,
			],
			$db_connection
		);
		$smcFunc['db_query']('', "
			CREATE DATABASE `$db_name`",
			[
				'security_override' => true,
				'db_error_skip' => true,
			],
			$db_connection
		);
		$smcFunc['db']->select_db($db_name);

		$replaces = array(
			'{$db_prefix}' => 'behat_' . $db_prefix,
			'{$attachdir}' => json_encode(array(1 => $smcFunc['db_escape_string']($boarddir . '/attachments'))),
			'{$boarddir}' => $smcFunc['db_escape_string']($boarddir),
			'{$boardurl}' => $boardurl,
			'{$databaseSession_enable}' => (ini_get('session.auto_start') != 1) ? '1' : '0',
			'{$sbb_version}' => 'Behat',
			'{$current_time}' => time(),
			'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
			'{$registration_method}' => 0,
		);

		foreach ($txt as $key => $value)
		{
			if (substr($key, 0, 8) == 'default_')
				$replaces['{$' . $key . '}'] = $smcFunc['db_escape_string']($value);
		}
		$replaces['{$default_reserved_names}'] = strtr($replaces['{$default_reserved_names}'], array('\\\\n' => '\\n'));

		// MySQL-specific stuff - storage engine and UTF8 handling
		if (substr($db_type, 0, 5) == 'mysql')
		{
			// Just in case the query fails for some reason...
			$engines = [];

			// Figure out storage engines - what do we have, etc.
			$get_engines = $smcFunc['db_query']('', 'SHOW ENGINES', []);

			while ($row = $smcFunc['db_fetch_assoc']($get_engines))
			{
				if ($row['Support'] == 'YES' || $row['Support'] == 'DEFAULT')
					$engines[] = $row['Engine'];
			}

			// Done with this now
			$smcFunc['db_free_result']($get_engines);

			// InnoDB is better, so use it if possible...
			$has_innodb = in_array('InnoDB', $engines);
			$replaces['{$engine}'] = $has_innodb ? 'InnoDB' : 'MyISAM';
			$replaces['{$memory}'] = in_array('MEMORY', $engines) ? 'MEMORY' : $replaces['{$engine}'];

			// We're using UTF-8 setting, so add it to the table definitions.
			$replaces['{$engine}'] .= ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
			$replaces['{$memory}'] .= ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

			$replaces['{$engine_master}'] = $replaces['{$engine}'];

			// One last thing - if we don't have InnoDB, we can't do transactions...
			if (!$has_innodb)
			{
				$replaces['START TRANSACTION;'] = '';
				$replaces['COMMIT;'] = '';
			}
		}
		else
		{
			$has_innodb = false;
		}

		// Read in the SQL.  Turn this on and that off... internationalize... etc.
		$sql_lines = explode("\n", strtr(implode(' ', file($boarddir . '/install_' . $version . '_' . $db_type . '.sql')), $replaces));

		// Execute the SQL.
		$current_statement = '';
		$exists = [];
		$incontext['failures'] = [];
		$incontext['sql_results'] = array(
			'tables' => 0,
			'inserts' => 0,
			'table_dups' => 0,
			'insert_dups' => 0,
		);
		foreach ($sql_lines as $count => $line)
		{
			// No comments allowed!
			if (substr(trim($line), 0, 1) != '#')
				$current_statement .= "\n" . rtrim($line);

			// Is this the end of the query string?
			if (empty($current_statement) || (preg_match('~;[\s]*$~s', $line) == 0 && $count != count($sql_lines)))
				continue;

			// Does this table already exist?  If so, don't insert more data into it!
			if (preg_match('~^\s*INSERT INTO ([^\s\n\r]+?)~', $current_statement, $match) != 0 && in_array($match[1], $exists))
			{
				preg_match_all('~\)[,;]~', $current_statement, $matches);
				if (!empty($matches[0]))
					$incontext['sql_results']['insert_dups'] += count($matches[0]);
				else
					$incontext['sql_results']['insert_dups']++;

				$current_statement = '';
				continue;
			}

			if (preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement))
			{
				if (stripos($current_statement, ' text ') === false && stripos($current_statement, ' mediumtext ') === false)
				{
					$current_statement = str_replace("ENGINE=InnoDB", "ENGINE=MEMORY", $current_statement);
				}
			}

			if ($smcFunc['db_query']('', $current_statement, array('security_override' => true, 'db_error_skip' => true), $db_connection) === false)
			{
				// Use the appropriate function based on the DB type
				if ($db_type == 'mysql' || $db_type == 'mysqli')
					$db_errorno = 'mysqli_errno';

				// Error 1050: Table already exists!
				// @todo Needs to be made better!
				if ((($db_type != 'mysql' && $db_type != 'mysqli') || $db_errorno($db_connection) == 1050) && preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement, $match) == 1)
				{
					$exists[] = $match[1];
					$incontext['sql_results']['table_dups']++;
				}
				// Don't error on duplicate indexes
				elseif (!preg_match('~^\s*CREATE( UNIQUE)? INDEX ([^\n\r]+?)~', $current_statement, $match))
				{
					// MySQLi requires a connection object.
					$incontext['failures'][$count] = $smcFunc['db_error']($db_connection) . "\n" . $current_statement;
				}
			}
			else
			{
				if (preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement, $match) == 1)
					$incontext['sql_results']['tables']++;
				elseif (preg_match('~^\s*INSERT INTO ([^\s\n\r]+?)~', $current_statement, $match) == 1)
				{
					preg_match_all('~\)[,;]~', $current_statement, $matches);
					if (!empty($matches[0]))
						$incontext['sql_results']['inserts'] += count($matches[0]);
					else
						$incontext['sql_results']['inserts']++;
				}
			}

			$current_statement = '';

			// Wait, wait, I'm still working here!
			set_time_limit(60);
		}

		foreach ($incontext['sql_results'] as $key => $number)
		{
			if ($number == 0)
				unset($incontext['sql_results'][$key]);
			else
				$incontext['sql_results'][$key] = sprintf($txt['db_populate_' . $key], $number);
		}

		// Output information about successes and failures.
		echo "Database population:\n";
		if (!empty($incontext['failures']))
		{
			foreach ($incontext['failures'] as $line => $error) {
				echo "Error on line $line: $error\n";
			}
		}
		foreach ($incontext['sql_results'] as $message) {
			echo $message, "\n";
		}

		if (!empty($incontext['failures']))
		{
			die('Error installing database');
		}

		// Load things we'll need to poke at the database.
		require_once($sourcedir . '/Subs.php');
		require_once($sourcedir . '/Load.php');
		require_once($sourcedir . '/Security.php');
		require_once($sourcedir . '/Logging.php');
	}

	/**
	 * Some final cleanup before a subsequent run.
	 *
	 * @AfterSuite
	 */
	public static function clean_up()
	{
		unlink(__DIR__ . '/../../Settings.php');
	}

	/**
	 * Initialise fresh tables for a given scenario.
	 *
	 * @BeforeScenario
	 */
	public function init_tables()
	{
		global $smcFunc, $db_prefix, $reservedTables;
		$reservedTables = [];

		$tables = $smcFunc['db_list_tables']();
		$non_prefixed_tables = preg_grep('/^(?!behat_).*/i', $tables);
		$smcFunc['db']->transaction('begin');
		foreach ($non_prefixed_tables as $table)
		{
			$smcFunc['db_query']('', '
				DROP TABLE IF EXISTS {raw:table}',
				array(
					'table' => $table,
				)
			);
		}
		$smcFunc['db']->transaction('commit');

		$pristine_tables = preg_grep('/^behat_/i', $tables);
		foreach ($pristine_tables as $table)
		{
			$smcFunc['db_backup_table']($table, str_replace('behat_', '', $table));
			set_time_limit(60);
		}

		$db_prefix = 'sbb_';
		reloadSettings();
	}

	/**
	 * Clear the various caching stuff.
	 *
	 * @BeforeScenario
	 */
	public function clear_caches()
	{
		$files = scandir(__DIR__ . '/../../cache');
		foreach ($files as $file)
		{
			if (strpos($file, 'data') === 0 || strpos($file, 'template') === 0)
			{
				unlink(__DIR__ . '/../../cache/' . $file);
			}
		}
	}
}
