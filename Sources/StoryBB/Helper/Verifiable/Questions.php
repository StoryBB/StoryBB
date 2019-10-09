<?php

/**
 * The Q&A handler for CAPTCHA.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Verifiable;

use StoryBB\Helper\Verifiable\AbstractVerifiable;
use StoryBB\Helper\Verifiable\UnverifiableException;
use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

class Questions extends AbstractVerifiable implements Verifiable
{
	protected $id;
	protected $number_questions;
	protected $question_cache;
	protected $questions;

	public function __construct(string $id)
	{
		global $modSettings;
		parent::__construct($id);

		$this->number_questions = !empty($modSettings['qa_verification_number']) ? (int) $modSettings['qa_verification_number'] : 0;
		if ($this->number_questions)
		{
			$this->load_questions();
		}
	}

	public function is_available(): bool
	{
		return !empty($this->number_questions);
	}

	protected function load_questions()
	{
		global $smcFunc;

		if ($this->question_cache !== null)
		{
			return;
		}

		if (($this->question_cache = cache_get_data('verificationQuestions', 300)) === null)
		{
			$request = $smcFunc['db']->query('', '
				SELECT id_question, lngfile, question, answers
				FROM {db_prefix}qanda',
				[]
			);
			$this->question_cache = [
				'questions' => [],
				'langs' => [],
			];
			// This is like Captain Kirk climbing a mountain in some ways. This is L's fault, mkay? :P
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				$id_question = $row['id_question'];
				unset ($row['id_question']);

				// Make them all lowercase.
				$row['answers'] = sbb_json_decode($row['answers'], true);
				array_walk($row['answers'], ['StoryBB\\StringLibrary', 'toLower']);

				$this->question_cache['questions'][$id_question] = $row;
				$this->question_cache['langs'][$row['lngfile']][] = $id_question;
			}
			$smcFunc['db']->free_result($request);

			cache_put_data('verificationQuestions', $this->question_cache, 300);
		}
	}

	public function reset()
	{
		global $user_info, $language;

		// Attempt to try the current page's language, followed by the user's preference, followed by the site default.
		$possible_langs = [];
		if (isset($_SESSION['language']))
		{
			$possible_langs[] = $_SESSION['language'];
		}
		if (!empty($user_info['language']));
		$possible_langs[] = $user_info['language'];
		$possible_langs[] = $language;

		$questionIDs = [];
		foreach ($possible_langs as $lang)
		{
			if (isset($this->question_cache['langs'][$lang]))
			{
				// If we find questions for this, grab the ids from this language's ones, randomize the array and take just the number we need.
				$questionIDs = $this->question_cache['langs'][$lang];
				shuffle($questionIDs);
				$questionIDs = array_slice($questionIDs, 0, $this->number_questions);
				break;
			}
		}

		// Having decided the questions, make them available to everything else.
		$_SESSION[$this->id . '_vv']['q'] = $questionIDs;
	}

	protected function get_questions($question_ids, $incorrectQuestions = [])
	{
		global $smcFunc;

		$this->load_questions();
		foreach ($question_ids as $q)
		{
			$row = $this->question_cache['questions'][$q];
			$this->questions[] = [
				'id' => $q,
				'q' => Parser::parse_bbc($row['question']),
				'is_error' => !empty($incorrectQuestions) && in_array($q, $incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$this->id . '_vv'], $_REQUEST[$this->id . '_vv']['q'], $_REQUEST[$this->id . '_vv']['q'][$q]) ? StringLibrary::escape($_REQUEST[$this->id . '_vv']['q'][$q]) : '',
			];
		}
	}

	public function verify()
	{
		global $txt, $smcFunc;

		$incorrectQuestions = [];
		foreach ($_SESSION[$this->id . '_vv']['q'] as $q)
		{
			// We don't have this question any more, thus no answers.
			if (!isset($this->question_cache['questions'][$q]))
			{
				continue;
			}

			// This is quite complex. We have our question but it might have multiple answers.
			// First, did they actually answer this question?
			if (!isset($_REQUEST[$this->id . '_vv']['q'][$q]) || trim($_REQUEST[$this->id . '_vv']['q'][$q]) == '')
			{
				$incorrectQuestions[] = $q;
				continue;
			}
			// Second, is their answer in the list of possible answers?
			else
			{
				$given_answer = trim(StringLibrary::escape(strtolower($_REQUEST[$this->id . '_vv']['q'][$q])));
				if (!in_array($given_answer, $this->question_cache['questions'][$q]['answers']))
				{
					$incorrectQuestions[] = $q;
				}
			}
		}

		$this->get_questions($_SESSION[$this->id . '_vv']['q'], $incorrectQuestions);

		if (!empty($incorrectQuestions))
		{
			throw new UnverifiableException($txt['error_wrong_verification_answer']);
		}
	}

	public function render()
	{
		global $txt;
		if ($this->questions === null)
		{
			$this->get_questions($_SESSION[$this->id . '_vv']['q']);
		}

		$template = \StoryBB\Template::load_partial('control_verification_questions');
		$phpStr = \StoryBB\Template::compile($template, [], 'control_verification_nativeimage-' . \StoryBB\Template::get_theme_id('partials', 'control_verification_nativeimage'));
		return new \LightnCandy\SafeString(\StoryBB\Template::prepare($phpStr, [
			'verify_id' => $this->id,
			'questions' => $this->questions,
			'txt' => $txt,
		]));
	}

	public function get_settings(): array
	{
		global $txt, $context, $language, $smcFunc;

		// Firstly, figure out what languages we're dealing with, and do a little processing for the form's benefit.
		getLanguages();

		// Secondly, load any questions we currently have.
		$context['question_answers'] = [];
		foreach ($context['languages'] as $lang_id => $lang)
		{
			$context['question_answers'][$lang_id] = [
				'name' => $lang['name'],
				'questions' => [],
			];
		}
		$request = $smcFunc['db']->query('', '
			SELECT id_question, lngfile, question, answers
			FROM {db_prefix}qanda'
		);
		$questions = 1;
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$lang = strtr($row['lngfile'], ['-utf8' => '']);
			if (!isset($context['question_answers'][$lang_id]))
			{
				continue;
			}
			$context['question_answers'][$lang_id]['questions'][$row['id_question']] = [
				'question' => $row['question'],
				'answers' => sbb_json_decode($row['answers'], true),
			];
			$questions++;
		}
		$smcFunc['db']->free_result($request);

		// If the user has insisted on questions, but hasn't put anything in for the default forum language, warn them.
		if (!empty($modSettings['qa_verification_number']) && (empty($context['question_answers'][$language]) || empty($context['question_answers'][$language]['questions'])))
		{
			session_flash('warning', sprintf($txt['question_not_defined'], $context['languages'][$language]['name']));
		}

		// Thirdly, push some JavaScript for the form to make it work.
		addInlineJavaScript('
	var nextrow = ' . $questions . ';
	$(".qa_link a").click(function() {
		var id = $(this).parent().attr("id").substring(6);
		$("#qa_fs_" + id).show();
		$(this).parent().hide();
	});
	$(".qa_fieldset legend a").click(function() {
		var id = $(this).closest("fieldset").attr("id").substring(6);
		$("#qa_dt_" + id).show();
		$(this).closest("fieldset").hide();
	});
	$(".qa_add_question a").click(function() {
		var id = $(this).closest("fieldset").attr("id").substring(6);
		$(\'<dt><input type="text" name="question[\' + id + \'][\' + nextrow + \']" value="" size="50" class="verification_question"></dt><dd><input type="text" name="answer[\' + id + \'][\' + nextrow + \'][]" value="" size="50" class="verification_answer" / ><div class="qa_add_answer"><a href="javascript:void(0);" onclick="return addAnswer(this);">[ \' + ' . JavaScriptEscape($txt['setup_verification_add_answer']) . ' + \' ]</a></div></dd>\').insertBefore($(this).parent());
		nextrow++;
	});
	function addAnswer(obj)
	{
		var attr = $(obj).closest("dd").find(".verification_answer:last").attr("name");
		$(\'<input type="text" name="\' + attr + \'" value="" size="50" class="verification_answer">\').insertBefore($(obj).closest("div"));
		return false;
	}
	$("#qa_dt_' . $language . ' a").click();', true);

		return [
			['titledesc', 'setup_verification_questions'],
			['int', 'qa_verification_number', 'subtext' => $txt['setting_qa_verification_number_desc']],
			['callback', 'question_answer_list'],
		];
	}

	public function put_settings(&$save_vars)
	{
		global $context, $txt, $smcFunc;

		// Handle verification questions.
		$changes = [
			'insert' => [],
			'replace' => [],
			'delete' => [],
		];
		$qs_per_lang = [];
		foreach (array_keys($context['question_answers']) as $lang_id)
		{
			// If we had some questions for this language before, but don't now, delete everything from that language.
			if ((!isset($_POST['question'][$lang_id]) || !is_array($_POST['question'][$lang_id])) && !empty($context['question_answers'][$lang_id]['questions']))
			{
				$changes['delete'] = array_merge($changes['delete'], array_keys($context['question_answers'][$lang_id]['questions']));
			}
			// Now step through and see if any existing questions no longer exist.
			elseif (!empty($context['question_answers'][$lang_id]['questions']))
			{
				foreach (array_keys($context['question_answers'][$lang_id]['questions']) as $q_id)
				{
					if (empty($_POST['question'][$lang_id][$q_id]))
					{
						$changes['delete'][] = $q_id;
					}
				}
			}

			// Now let's see if there are new questions or ones that need updating.
			if (isset($_POST['question'][$lang_id]))
			{
				foreach ($_POST['question'][$lang_id] as $q_id => $question)
				{
					// Ignore junky ids.
					$q_id = (int) $q_id;
					if ($q_id <= 0)
					{
						continue;
					}

					// Check the question isn't empty (because they want to delete it?)
					if (empty($question) || trim($question) == '')
					{
						if (isset($context['question_answers'][$lang_id]['questions'][$q_id]))
						{
							$changes['delete'][] = $q_id;
						}
						continue;
					}
					$question = StringLibrary::escape(trim($question));

					// Get the answers. Firstly check there actually might be some.
					if (!isset($_POST['answer'][$lang_id][$q_id]) || !is_array($_POST['answer'][$lang_id][$q_id]))
					{
						if (isset($context['question_answers'][$lang_id]['questions'][$q_id]))
						{
							$changes['delete'][] = $q_id;
						}
						continue;
					}
					// Now get them and check that they might be viable.
					$answers = [];
					foreach ($_POST['answer'][$lang_id][$q_id] as $answer)
					{
						if (!empty($answer) && trim($answer) !== '')
						{
							$answers[] = StringLibrary::escape(trim($answer));
						}
					}
					if (empty($answers))
					{
						if (isset($context['question_answers'][$lang_id]['questions'][$q_id]))
						{
							$changes['delete'][] = $q_id;
						}
						continue;
					}
					$answers = json_encode($answers);

					// At this point we know we have a question and some answers. What are we doing with it?
					if (!isset($context['question_answers'][$lang_id]['questions'][$q_id]))
					{
						// New question. Now, we don't want to randomly consume ids, so we'll set those, rather than trusting the browser's supplied ids.
						$changes['insert'][] = [$lang_id, $question, $answers];
					}
					else
					{
						// It's an existing question. Let's see what's changed, if anything.
						if ($question != $context['question_answers'][$lang_id]['questions'][$q_id]['question'] || $answers != $context['question_answers'][$lang_id]['questions'][$q_id]['answers'])
						{
							$changes['replace'][$q_id] = ['lngfile' => $lang_id, 'question' => $question, 'answers' => $answers];
						}
					}

					if (!isset($qs_per_lang[$lang_id]))
					{
						$qs_per_lang[$lang_id] = 0;
					}
					$qs_per_lang[$lang_id]++;
				}
			}
		}

		// OK, so changes?
		if (!empty($changes['delete']))
		{
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}qanda
				WHERE id_question IN ({array_int:questions})',
				[
					'questions' => $changes['delete'],
				]
			);
		}

		if (!empty($changes['replace']))
		{
			foreach ($changes['replace'] as $q_id => $question)
			{
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}qanda
					SET lngfile = {string:lngfile},
						question = {string:question},
						answers = {string:answers}
					WHERE id_question = {int:id_question}',
					[
						'id_question' => $q_id,
						'lngfile' => $question['lngfile'],
						'question' => $question['question'],
						'answers' => $question['answers'],
					]
				);
			}
		}

		if (!empty($changes['insert']))
		{
			$smcFunc['db']->insert('insert',
				'{db_prefix}qanda',
				['lngfile' => 'string-50', 'question' => 'string-255', 'answers' => 'string-65534'],
				$changes['insert'],
				['id_question']
			);
		}

		// Lastly, the count of messages needs to be no more than the lowest number of questions for any one language.
		$count_questions = empty($qs_per_lang) ? 0 : min($qs_per_lang);
		if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions)
		{
			$_POST['qa_verification_number'] = $count_questions;
		}

		cache_put_data('verificationQuestions', null, 300);
	}
}
