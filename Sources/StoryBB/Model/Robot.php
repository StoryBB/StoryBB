<?php

/**
 * This class handles robots and robot detection.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles robot detection.
 */
class Robot
{
	/**
	 * Attempts to identify a robot from the supplied user agent.
	 *
	 * @param string $user_agent The user agent from the browser
	 * @return string Identifier for the robot, or empty string if not identified
	 */
	public function identify_robot_from_user_agent(string $user_agent): string
	{
		// First, a list of known robots - identifiers in user agent => generic identifier code.
		$known_robots = [
			'abcdatos' => 'abcdatos',
			'adidxbot' => 'bingads',
			'alexabot' => 'alexacertify',
			'archive.org_bot' => 'archive.org',
			'ask jeeves' => 'teoma',
			'baiduspider' => 'baidu',
			'bingbot' => 'bing',
			'bingpreview' => 'bingpreview',
			'facebookexternalhit' => 'facebook',
			'facebot' => 'facebook',
			'feedly' => 'feedly',
			'googlebot' => 'google',
			'ia_archiver' => 'alexa',
			'magpie-crawler' => 'brandwatch',
			'mediapartners-google' => 'googleadsense',
			'mj12bot' => 'majestic12',
			'msnbot-media' => 'bingmedia',
			'msnbot' => 'bing',
			'netvibes' => 'netvibes',
			'omgili' => 'webhose',
			'pingdom.com_bot' => 'pingdom',
			'proximic' => 'proximic',
			'scoutjet' => 'scoutjet',
			'slackbot' => 'slack',
			'sogou web spider' => 'sogou',
			'statuscake' => 'statuscake',
			'teoma' => 'teoma',
			'uptimerobot' => 'uptimerobot',
			'w3c_validator' => 'w3c_validator',
			'yacybot' => 'yacy',
			'yahoo! slurp' => 'yahoo',
			'yandex' => 'yandex',
		];

		$user_agent = strtolower($user_agent);
		foreach ($known_robots as $search_string => $ua_identifier)
		{
			if (stripos($user_agent, $search_string) !== false)
			{
				return $ua_identifier;
			}
		}

		return '';
	}

	/**
	 * Attempts to provide more information about a given bot.
	 *
	 * @param string $ua_identifier The user agent identifier
	 * @return array An array of details about the user agent if known
	 */
	public function get_robot_info(string $ua_identifier): array
	{
		static $robots = [
			'abcdatos' => [
				'title' => 'ABCDatos',
				'link' => 'http://www.abcdatos.com/botlink/',
			],
			'alexa' => [
				'title' => 'Alexa',
				'link' => 'https://support.alexa.com/hc/en-us/articles/200450194-Alexa-s-Web-and-Site-Audit-Crawlers',
			],
			'alexacertify' => [
				'title' => 'Alexa Certification Crawler',
				'link' => 'https://support.alexa.com/hc/en-us/articles/200462340-Certification-Crawler-Information',
			],
			'archive.org' => [
				'title' => 'Internet Archive',
				'link' => 'http://www.archive.org/details/archive.org_bot',
			],
			'baidu' => [
				'title' => 'Baidu',
				'link' => 'http://help.baidu.com/question?prod_id=99&class=0&id=3001',
			],
			'bing' => [
				'title' => 'Bing (MSNBot)',
				'link' => 'http://www.bing.com/bingbot.htm',
			],
			'bingads' => [
				'title' => 'Bing (AdIdxBot)',
				'link' => 'https://www.bing.com/webmaster/help/which-crawlers-does-bing-use-8c184ec0',
			],
			'bingmedia' => [
				'title' => 'Bing (MSNBot-Media)',
				'link' => 'https://www.bing.com/webmaster/help/which-crawlers-does-bing-use-8c184ec0',
			],
			'bingpreview' => [
				'title' => 'Bing (BingPreview)',
				'link' => 'https://www.bing.com/webmaster/help/which-crawlers-does-bing-use-8c184ec0',
			],
			'brandwatch' => [
				'title' => 'Brandwatch',
				'link' => 'https://www.brandwatch.com/legal/magpie-crawler/',
			],
			'facebook' => [
				'title' => 'Facebook',
				'link' => 'https://developers.facebook.com/docs/sharing/webmasters/crawler',
			],
			'feedly' => [
				'title' => 'Feedly',
				'link' => 'https://www.feedly.com/fetcher.html',
			],
			'google' => [
				'title' => 'Google',
				'link' => 'https://support.google.com/webmasters/answer/182072',
			],
			'googleadsense' => [
				'title' => 'Google (Adsense)',
				'link' => 'https://support.google.com/webmasters/answer/182072',
			],
			'majestic12' => [
				'title' => 'Majestic12',
				'link' => 'https://www.majestic12.co.uk/projects/dsearch/mj12bot.php',
			],
			'netvibes' => [
				'title' => 'Netvibes',
				'link' => 'https://www.netvibes.com/en',
			],
			'pingdom' => [
				'title' => 'Pingdom',
				'link' => 'https://www.pingdom.com/',
			],
			'proximic' => [
				'title' => 'Proximic',
				'link' => 'http://www.proximic.com/info/spider.php',
			],
			'scoutjet' => [
				'title' => 'ScoutJet',
				'link' => 'http://scoutjet.com/',
			],
			'slack' => [
				'title' => 'Slack',
				'link' => 'https://api.slack.com/robots',
			],
			'sogou' => [
				'title' => 'Sogou',
				'link' => 'http://www.sogou.com/docs/help/webmasters.htm#07',
			],
			'statuscake' => [
				'title' => 'StatusCake',
				'link' => 'https://www.statuscake.com/',
			],
			'teoma' => [
				'title' => 'Teoma (Ask Jeeves/Ask)',
				'link' => 'http://ask.com/',
			],
			'uptimerobot' => [
				'title' => 'UptimeRobot',
				'link' => 'https://uptimerobot.com/about',
			],
			'w3c_validator' => [
				'title' => 'W3C Validator',
				'link' => 'http://validator.w3.org',
			],
			'webhose' => [
				'title' => 'Webhose.io (Omgilibot)',
				'link' => 'https://blog.webhose.io/2017/12/28/what-is-the-omgili-bot-and-why-is-it-crawling-your-website/',
			],
			'yacy' => [
				'title' => 'YaCy',
				'link' => 'https://yacy.net/bot.html',
			],
			'yahoo' => [
				'title' => 'Yahoo',
				'link' => 'http://help.yahoo.com/help/us/ysearch/slurp',
			],
			'yandex' => [
				'title' => 'Yandex',
				'link' => 'https://yandex.com/support/search/?id=1112030',
			],
		];

		return isset($robots[$ua_identifier]) ? $robots[$ua_identifier] : [];
	}
}
