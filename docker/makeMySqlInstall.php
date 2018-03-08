<?php 
$version = '3-0';

global $txt, $databases, $incontext, $smcFunc, $sourcedir, $boarddir, $boardurl;
global $db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_type;
require_once(__DIR__ . '/../Settings.php');

//dirty hack
define('SMF', 1);

require_once($sourcedir . '/Subs-Db-mysql.php');
require_once($boarddir . '/Themes/default/languages/Install.english.php');


$output = "DROP DATABASE IF EXISTS `$db_name`";
$output .= "CREATE DATABASE `$db_name`";
$output .= "USE `$db_name`";

$replaces = array(
    '{$db_prefix}' => 'behat_' . $db_prefix,
    '{$attachdir}' => json_encode(array(1 => addslashes($boarddir . '/attachments'))),
    '{$boarddir}' => addslashes($boarddir),
    '{$boardurl}' => $boardurl,
    '{$databaseSession_enable}' => (ini_get('session.auto_start') != 1) ? '1' : '0',
    '{$smf_version}' => 'Behat',
    '{$current_time}' => time(),
    '{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
    '{$registration_method}' => 0,
);

foreach ($txt as $key => $value)
{
    if (substr($key, 0, 8) == 'default_')
        $replaces['{$' . $key . '}'] = addslashes($value);
}

//we control the database version, it has InnoDB
$replaces['{$default_reserved_names}'] = strtr($replaces['{$default_reserved_names}'], array('\\\\n' => '\\n'));
$replaces['{$engine}'] ='InnoDB'; 
$replaces['{$memory}'] = $replaces['{$engine}'];

// We're using UTF-8 setting, so add it to the table definitions.
$replaces['{$engine}'] .= ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
$replaces['{$memory}'] .= ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

// Read in the SQL.  Turn this on and that off... internationalize... etc.
$sql_lines = explode("\n", strtr(implode(' ', file($boarddir . '/other/install_' . $version . '_mysql.sql')), $replaces));


foreach ($sql_lines as $count => $line)
{
    // No comments allowed!
    if (substr(trim($line), 0, 1) != '#')
        $current_statement .= "\n" . rtrim($line) . "\n";
    
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

    $output .= $current_statement;

}

file_put_contents("install_mysql_docker.sql", $output);
?>