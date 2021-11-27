<?php

/**
 * Parse content according to its bbc and smiley content.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Helper\Bbcode;

use StoryBB\App;
use StoryBB\Container;
use StoryBB\Helper\TLD;
use StoryBB\Hook\Mutatable;
use StoryBB\StringLibrary;

/**
 * Parse content according to its bbc and smiley content.
 */
abstract class AbstractParser
{
	protected $bbcode = [];
	protected $no_autolink_tags = [];
	protected $allow_smileys = true;

	protected $allowed_options = [];
	protected $bbc_options = [];

	protected $enable_html = false;

	public function parse(string $message, array $bbc_options = []): string
	{
		// If there's no message, there's nothing to do regardless of anything else.
		if ($message === '')
		{
			return '';
		}

		// Process message options.
		$this->bbc_options = $this->process_options($bbc_options);

		$message = $this->sanitize_ms_cut_paste($message);
	}

	/**
	 * Process parser options.
	 *
	 * @param array $bbc_options An array of options for this particular parse.
	 * @return array Valid options processed
	 */
	protected function process_options(array $bbc_options): array
	{
		global $modSettings;

		$this->enable_html = $modSettings['enablePostHTML'];

		foreach ($bbc_options as $key => $value)
		{
			if (!isset($this->allowed_options[$key]))
			{
				unset ($bbc_options[$key]);
			}
		}
	}

	/**
	 * Microsoft uses their own character set Code Page 1252 (CP1252), which is a
	 * superset of ISO 8859-1, defining several characters between DEC 128 and 159
	 * that are not normally displayable.  This converts the popular ones that
	 * appear from a cut and paste from windows.
	 *
	 * @param string $string The string
	 * @return string The sanitised string
	 */
	public function sanitize_ms_cut_paste(string $string): string
	{
		if (empty($string))
		{
			return $string;
		}

		// UTF-8 occurences of MS special characters
		$findchars_utf8 = [
			"\xe2\x80\x9a",	// single low-9 quotation mark
			"\xe2\x80\x9e",	// double low-9 quotation mark
			"\xe2\x80\xa6",	// horizontal ellipsis
			"\xe2\x80\x98",	// left single curly quote
			"\xe2\x80\x99",	// right single curly quote
			"\xe2\x80\x9c",	// left double curly quote
			"\xe2\x80\x9d",	// right double curly quote
			"\xe2\x80\x93",	// en dash
			"\xe2\x80\x94",	// em dash
		];

		// safe replacements
		$replacechars = [
			',',	// &sbquo;
			',,',	// &bdquo;
			'...',	// &hellip;
			"'",	// &lsquo;
			"'",	// &rsquo;
			'"',	// &ldquo;
			'"',	// &rdquo;
			'-',	// &ndash;
			'--',	// &mdash;
		];

		return str_replace($findchars_utf8, $replacechars, $string);
	}

	/**
	 * Return two arrays related to definitions of all bbcodes.
	 *
	 * @return array Two elements, the first is the master list of all bbcode, the second which tags to not automatically link URLs inside.
	 */
	public static function bbcode_definitions(): array
	{
		global $modSettings, $context, $sourcedir, $txt, $scripturl;

		$current_user = App::container()->get('currentuser');
		$current_user_id = $current_user ? $current_user->get_id() : 0;

		/* The following bbc are formatted as an array, with keys as follows:

			tag: the tag's name - should be lowercase!

			type: one of...
				- (missing): [tag]parsed content[/tag]
				- unparsed_equals: [tag=xyz]parsed content[/tag]
				- parsed_equals: [tag=parsed data]parsed content[/tag]
				- unparsed_content: [tag]unparsed content[/tag]
				- closed: [tag], [tag/], [tag /]
				- unparsed_commas: [tag=1,2,3]parsed content[/tag]
				- unparsed_commas_content: [tag=1,2,3]unparsed content[/tag]
				- unparsed_equals_content: [tag=...]unparsed content[/tag]

			parameters: an optional array of parameters, for the form
			  [tag abc=123]content[/tag].  The array is an associative array
			  where the keys are the parameter names, and the values are an
			  array which may contain the following:
				- match: a regular expression to validate and match the value.
				- quoted: true if the value should be quoted.
				- validate: callback to evaluate on the data, which is $data.
				- value: a string in which to replace $1 with the data.
				  either it or validate may be used, not both.
				- optional: true if the parameter is optional.

			test: a regular expression to test immediately after the tag's
			  '=', ' ' or ']'.  Typically, should have a \] at the end.
			  Optional.

			content: only available for unparsed_content, closed,
			  unparsed_commas_content, and unparsed_equals_content.
			  $1 is replaced with the content of the tag.  Parameters
			  are replaced in the form {param}.  For unparsed_commas_content,
			  $2, $3, ..., $n are replaced.

			before: only when content is not used, to go before any
			  content.  For unparsed_equals, $1 is replaced with the value.
			  For unparsed_commas, $1, $2, ..., $n are replaced.

			after: similar to before in every way, except that it is used
			  when the tag is closed.

			disabled_content: used in place of content when the tag is
			  disabled.  For closed, default is '', otherwise it is '$1' if
			  block_level is false, '<div>$1</div>' elsewise.

			disabled_before: used in place of before when disabled.  Defaults
			  to '<div>' if block_level, '' if not.

			disabled_after: used in place of after when disabled.  Defaults
			  to '</div>' if block_level, '' if not.

			block_level: set to true the tag is a "block level" tag, similar
			  to HTML.  Block level tags cannot be nested inside tags that are
			  not block level, and will not be implicitly closed as easily.
			  One break following a block level tag may also be removed.

			trim: if set, and 'inside' whitespace after the begin tag will be
			  removed.  If set to 'outside', whitespace after the end tag will
			  meet the same fate.

			validate: except when type is missing or 'closed', a callback to
			  validate the data as $data.  Depending on the tag's type, $data
			  may be a string or an array of strings (corresponding to the
			  replacement.)

			quoted: when type is 'unparsed_equals' or 'parsed_equals' only,
			  may be not set, 'optional', or 'required' corresponding to if
			  the content may be quoted.  This allows the parser to read
			  [tag="abc]def[esdf]"] properly.

			require_parents: an array of tag names, or not set.  If set, the
			  enclosing tag *must* be one of the listed tags, or parsing won't
			  occur.

			require_children: similar to require_parents, if set children
			  won't be parsed if they are not in the list.

			disallow_children: similar to, but very different from,
			  require_children, if it is set the listed tags will not be
			  parsed inside the tag.

			parsed_tags_allowed: an array restricting what BBC can be in the
			  parsed_equals parameter, if desired.
		*/

		$codes = [
			[
				'tag' => 'abbr',
				'type' => 'unparsed_equals',
				'before' => '<abbr title="$1">',
				'after' => '</abbr>',
				'quoted' => 'optional',
				'disabled_after' => ' ($1)',
			],
			[
				'tag' => 'anchor',
				'type' => 'unparsed_equals',
				'test' => '[#]?([A-Za-z][A-Za-z0-9_\-]*)\]',
				'before' => '<span id="post_$1">',
				'after' => '</span>',
			],
			[
				'tag' => 'attach',
				'type' => 'unparsed_content',
				'parameters' => [
					'name' => ['optional' => true],
					'type' => ['optional' => true],
					'alt' => ['optional' => true],
					'title' => ['optional' => true],
					'width' => ['optional' => true, 'match' => '(\d+)'],
					'height' => ['optional' => true, 'match' => '(\d+)'],
				],
				'content' => '$1',
				'validate' => function (&$tag, &$data, $disabled, $params) use ($modSettings, $context, $sourcedir, $txt)
				{
					$returnContext = '';

					// BBC or the entire attachments feature is disabled
					if (empty($modSettings['attachmentEnable']) || !empty($disabled['attach']))
						return $data;

					// Save the attach ID.
					$attachID = $data;

					// Kinda need this.
					require_once($sourcedir . '/Subs-Attachments.php');

					$currentAttachment = parseAttachBBC($attachID);

					// parseAttachBBC will return a string ($txt key) rather than diying with a fatal_error. Up to you to decide what to do.
					if (is_string($currentAttachment))
						return $data = !empty($txt[$currentAttachment]) ? $txt[$currentAttachment] : $currentAttachment;

					if (!empty($currentAttachment['is_image']))
					{
						$alt = ' alt="' . (!empty($params['{alt}']) ? $params['{alt}'] : $currentAttachment['name']) . '"';
						$title = !empty($params['{title}']) ? ' title="' . $params['{title}'] . '"' : '';

						$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
						$height = !empty($params['{height}']) ? ' height="' . $params['{height}'] . '"' : '';

						if (empty($width) && empty($height))
						{
							$width = ' width="' . $currentAttachment['width'] . '"';
							$height = ' height="' . $currentAttachment['height'] . '"';
						}

						if ($currentAttachment['thumbnail']['has_thumb'] && empty($params['{width}']) && empty($params['{height}']))
							$returnContext .= '<a href="'. $currentAttachment['href']. ';image" id="link_'. $currentAttachment['id']. '" onclick="'. $currentAttachment['thumbnail']['javascript']. '"><img src="'. $currentAttachment['thumbnail']['href']. '"' . $alt . $title . ' id="thumb_'. $currentAttachment['id']. '" class="atc_img"></a>';
						else
							$returnContext .= '<img src="' . $currentAttachment['href'] . ';image"' . $alt . $title . $width . $height . ' class="bbc_img"/>';
					}

					// No image. Show a link.
					else
						$returnContext .= $currentAttachment['link'];

					// Gotta append what we just did.
					$data = $returnContext;
				},
			],
			[
				'tag' => 'b',
				'before' => '<strong>',
				'after' => '</strong>',
			],
			[
				'tag' => 'center',
				'before' => '<div class="centertext">',
				'after' => '</div>',
				'block_level' => true,
			],
			[
				'tag' => 'character',
				'type' => 'unparsed_equals',
				'before' => '<a href="' . $scripturl . '?action=characters;char=$1" class="mention" data-mention="$1">@',
				'after' => '</a>',
			],
			[
				'tag' => 'code',
				'type' => 'unparsed_content',
				'content' => '<div class="codeheader"><span class="code floatleft">' . $txt['code'] . '</span> <a class="codeoperation sbb_select_text">' . $txt['code_select'] . '</a></div><code class="bbc_code">$1</code>',
				// @todo Maybe this can be simplified?
				'validate' => function (&$tag, &$data, $disabled) use ($context)
				{
					if (!isset($disabled['code']))
					{
						$data = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", $data);
						$data = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $data);

						// Recent Opera bug requiring temporary fix. &nsbp; is needed before </code> to avoid broken selection.
						if ($context['browser']['is_opera'])
							$data .= '&nbsp;';
					}
				},
				'block_level' => true,
			],
			[
				'tag' => 'code',
				'type' => 'unparsed_equals_content',
				'content' => '<div class="codeheader"><span class="code floatleft">' . $txt['code'] . '</span> ($2) <a class="codeoperation sbb_select_text">' . $txt['code_select'] . '</a></div><code class="bbc_code">$1</code>',
				// @todo Maybe this can be simplified?
				'validate' => isset($disabled['code']) ? null : function (&$tag, &$data, $disabled) use ($context)
				{
					if (!isset($disabled['code']))
					{
						$data[0] = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", $data);
						$data[0] = str_replace("\t", "<span style=\"white-space: pre;\">\t</span>", $data[0]);

						// Recent Opera bug requiring temporary fix. &nsbp; is needed before </code> to avoid broken selection.
						if ($context['browser']['is_opera'])
							$data[0] .= '&nbsp;';
					}
				},
				'block_level' => true,
			],
			[
				'tag' => 'color',
				'type' => 'unparsed_equals',
				'test' => '(#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\s?,\s?){2}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\))\]',
				'before' => '<span style="color: $1;" class="bbc_color">',
				'after' => '</span>',
			],
			[
				'tag' => 'email',
				'type' => 'unparsed_content',
				'content' => '<a href="mailto:$1" class="bbc_email">$1</a>',
				'autolink' => false,
				// @todo Should this respect guest_hideContacts?
				'validate' => function (&$tag, &$data, $disabled)
				{
					$data = strtr($data, ['<br>' => '']);
				},
			],
			[
				'tag' => 'email',
				'type' => 'unparsed_equals',
				'before' => '<a href="mailto:$1" class="bbc_email">',
				'after' => '</a>',
				'autolink' => false,
				// @todo Should this respect guest_hideContacts?
				'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
				'disabled_after' => ' ($1)',
			],
			[
				'tag' => 'float',
				'type' => 'unparsed_equals',
				'test' => '(left|right)(\s+max=\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)?\]',
				'before' => '<div $1>',
				'after' => '</div>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					$class = 'class="bbc_float float' . (strpos($data, 'left') === 0 ? 'left' : 'right') . '"';

					if (preg_match('~\bmax=(\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)~', $data, $matches))
						$css = ' style="max-width:' . $matches[1] . (is_numeric($matches[1]) ? 'px' : '') . '"';
					else
						$css = '';

					$data = $class . $css;
				},
				'trim' => 'outside',
				'block_level' => true,
			],
			[
				'tag' => 'font',
				'type' => 'unparsed_equals',
				'test' => '[A-Za-z0-9_,\-\s]+?\]',
				'before' => '<span style="font-family: $1;" class="bbc_font">',
				'after' => '</span>',
			],
			[
				'tag' => 'html',
				'type' => 'unparsed_content',
				'content' => '<div>$1</div>',
				'block_level' => true,
				'disabled_content' => '$1',
			],
			[
				'tag' => 'hr',
				'type' => 'closed',
				'content' => '<hr>',
				'block_level' => true,
			],
			[
				'tag' => 'i',
				'before' => '<i>',
				'after' => '</i>',
			],
			[
				'tag' => 'img',
				'type' => 'unparsed_content',
				'parameters' => [
					'alt' => ['optional' => true],
					'title' => ['optional' => true],
					'width' => ['optional' => true, 'value' => ' width="$1"', 'match' => '(\d+)'],
					'height' => ['optional' => true, 'value' => ' height="$1"', 'match' => '(\d+)'],
				],
				'content' => '<img src="$1" alt="{alt}" title="{title}"{width}{height} class="bbc_img resized">',
				'validate' => function (&$tag, &$data, $disabled)
				{
					global $image_proxy_enabled, $image_proxy_secret, $boardurl;

					$data = strtr($data, ['<br>' => '']);
					$scheme = parse_url($data, PHP_URL_SCHEME);
					if ($image_proxy_enabled)
					{
						if (empty($scheme))
							$data = 'http://' . ltrim($data, ':/');

						if ($scheme != 'https')
							$data = $boardurl . '/proxy.php?request=' . urlencode($data) . '&hash=' . md5($data . $image_proxy_secret);
					}
					elseif (empty($scheme))
						$data = '//' . ltrim($data, ':/');
				},
				'disabled_content' => '($1)',
			],
			[
				'tag' => 'img',
				'type' => 'unparsed_content',
				'content' => '<img src="$1" alt="" class="bbc_img">',
				'validate' => function (&$tag, &$data, $disabled)
				{
					global $image_proxy_enabled, $image_proxy_secret, $boardurl;

					$data = strtr($data, ['<br>' => '']);
					$scheme = parse_url($data, PHP_URL_SCHEME);
					if ($image_proxy_enabled)
					{
						if (empty($scheme))
							$data = 'http://' . ltrim($data, ':/');

						if ($scheme != 'https')
							$data = $boardurl . '/proxy.php?request=' . urlencode($data) . '&hash=' . md5($data . $image_proxy_secret);
					}
					elseif (empty($scheme))
						$data = '//' . ltrim($data, ':/');
				},
				'disabled_content' => '($1)',
			],
			[
				'tag' => 'iurl',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link">$1</a>',
				'autolink' => false,
				'validate' => function (&$tag, &$data, $disabled)
				{
					$data = strtr($data, ['<br>' => '']);
					$scheme = parse_url($data, PHP_URL_SCHEME);
					if (empty($scheme))
						$data = '//' . ltrim($data, ':/');
				},
			],
			[
				'tag' => 'iurl',
				'type' => 'unparsed_equals',
				'quoted' => 'optional',
				'before' => '<a href="$1" class="bbc_link">',
				'after' => '</a>',
				'autolink' => false,
				'validate' => function (&$tag, &$data, $disabled)
				{
					if (substr($data, 0, 1) == '#')
						$data = '#post_' . substr($data, 1);
					else
					{
						$scheme = parse_url($data, PHP_URL_SCHEME);
						if (empty($scheme))
							$data = '//' . ltrim($data, ':/');
					}
				},
				'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
				'disabled_after' => ' ($1)',
			],
			[
				'tag' => 'justify',
				'before' => '<div align="justify">',
				'after' => '</div>',
				'block_level' => true,
			],
			[
				'tag' => 'left',
				'before' => '<div style="text-align: left;">',
				'after' => '</div>',
				'block_level' => true,
			],
			[
				'tag' => 'li',
				'before' => '<li>',
				'after' => '</li>',
				'trim' => 'outside',
				'require_parents' => ['list'],
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '<br>',
			],
			[
				'tag' => 'list',
				'before' => '<ul class="bbc_list">',
				'after' => '</ul>',
				'trim' => 'inside',
				'require_children' => ['li', 'list'],
				'block_level' => true,
			],
			[
				'tag' => 'list',
				'parameters' => [
					'type' => ['match' => '(none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|upper-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha)'],
				],
				'before' => '<ul class="bbc_list" style="list-style-type: {type};">',
				'after' => '</ul>',
				'trim' => 'inside',
				'require_children' => ['li'],
				'block_level' => true,
			],
			[
				'tag' => 'ltr',
				'before' => '<bdo dir="ltr">',
				'after' => '</bdo>',
				'block_level' => true,
			],
			[
				'tag' => 'mature',
				'before' => $current_user_id ? '<details class="bbc_spoiler"><summary>' . $txt['mature_open'] . '</summary>' : '<div class="noticebox">' . $txt['mature_restricted'] . '</div><sbb___strip>',
				'after' => $current_user_id ? '</details>' : '</sbb___strip>',
				'trim' => 'both',
				'disallow_children' => ['spoiler', 'mature'],
			],
			[
				'tag' => 'mature',
				'type' => 'parsed_equals',
				'before' => $current_user_id ? '<details class="bbc_spoiler"><summary>$1</summary>' : '<div class="noticebox">' . $txt['mature_restricted'] . '</div><sbb___strip>',
				'after' => $current_user_id ? '</details>' : '</sbb___strip>',
				'trim' => 'both',
				'quoted' => 'optional',
				'disallow_children' => ['spoiler', 'mature'],
			],
			[
				'tag' => 'me',
				'type' => 'unparsed_equals',
				'before' => '<div class="meaction">* $1 ',
				'after' => '</div>',
				'quoted' => 'optional',
				'block_level' => true,
				'disabled_before' => '/me ',
				'disabled_after' => '<br>',
			],
			[
				'tag' => 'member',
				'type' => 'unparsed_equals',
				'before' => '<a href="' . $scripturl . '?action=profile;area=summary;u=$1" class="mention" data-mention="$1">@',
				'after' => '</a>',
			],
			[
				'tag' => 'nobbc',
				'type' => 'unparsed_content',
				'content' => '$1',
			],
			[
				'tag' => 'ooc',
				'before' => '<blockquote class="bbc_standard_quote bbc_ooc"><cite>' . $txt['out_of_character'] . '</cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
				'disallow_children' => ['ooc'],
			],
			[
				'tag' => 'pre',
				'before' => '<pre>',
				'after' => '</pre>',
			],
			[
				'tag' => 'quote',
				'before' => '<blockquote><cite>' . $txt['quote'] . '</cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
			],
			[
				'tag' => 'quote',
				'parameters' => [
					'author' => ['match' => '(.{1,192}?)', 'quoted' => true],
				],
				'before' => '<blockquote><cite>' . $txt['quote_from'] . ': {author}</cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
			],
			[
				'tag' => 'quote',
				'type' => 'parsed_equals',
				'before' => '<blockquote><cite>' . $txt['quote_from'] . ': $1</cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'quoted' => 'optional',
				// Don't allow everything to be embedded with the author name.
				'parsed_tags_allowed' => ['url', 'iurl', 'ftp'],
				'block_level' => true,
			],
			[
				'tag' => 'quote',
				'parameters' => [
					'author' => ['match' => '([^<>]{1,192}?)'],
					'link' => ['match' => '(?:board=\d+;)?((?:topic)=[\dmsg#\./]{1,40}(?:;start=[\dmsg#\./]{1,40})?|msg=\d+?|action=profile;u=\d+)'],
					'date' => ['match' => '(\d+)', 'validate' => 'timeformat'],
				],
				'before' => '<blockquote><cite><a href="' . $scripturl . '?{link}">' . $txt['quote_from'] . ': {author} ' . $txt['search_on'] . ' {date}</a></cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
			],
			[
				'tag' => 'quote',
				'parameters' => [
					'author' => ['match' => '(.{1,192}?)'],
				],
				'before' => '<blockquote><cite>' . $txt['quote_from'] . ': {author}</cite>',
				'after' => '</blockquote>',
				'trim' => 'both',
				'block_level' => true,
			],
			[
				'tag' => 'right',
				'before' => '<div style="text-align: right;">',
				'after' => '</div>',
				'block_level' => true,
			],
			[
				'tag' => 'rtl',
				'before' => '<bdo dir="rtl">',
				'after' => '</bdo>',
				'block_level' => true,
			],
			[
				'tag' => 's',
				'before' => '<s>',
				'after' => '</s>',
			],
			[
				'tag' => 'size',
				'type' => 'unparsed_equals',
				'test' => '([1-9][\d]?p[xt]|small(?:er)?|large[r]?|x[x]?-(?:small|large)|medium|(0\.[1-9]|[1-9](\.[\d][\d]?)?)?em)\]',
				'before' => '<span style="font-size: $1;" class="bbc_size">',
				'after' => '</span>',
			],
			[
				'tag' => 'size',
				'type' => 'unparsed_equals',
				'test' => '[1-7]\]',
				'before' => '<span style="font-size: $1;" class="bbc_size">',
				'after' => '</span>',
				'validate' => function (&$tag, &$data, $disabled)
				{
					$sizes = [1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95];
					$data = $sizes[$data] . 'em';
				},
			],
			[
				'tag' => 'spoiler',
				'before' => '<details class="bbc_spoiler"><summary>' . $txt['spoiler_open'] . '</summary>',
				'after' => '</details>',
				'trim' => 'both',
				'disallow_children' => ['spoiler', 'mature'],
			],
			[
				'tag' => 'spoiler',
				'type' => 'parsed_equals',
				'before' => '<details class="bbc_spoiler"><summary>$1</summary>',
				'after' => '</details>',
				'trim' => 'both',
				'quoted' => 'optional',
				'disallow_children' => ['spoiler', 'mature'],
			],
			[
				'tag' => 'sub',
				'before' => '<sub>',
				'after' => '</sub>',
			],
			[
				'tag' => 'sup',
				'before' => '<sup>',
				'after' => '</sup>',
			],
			[
				'tag' => 'table',
				'before' => '<table class="bbc_table">',
				'after' => '</table>',
				'trim' => 'inside',
				'require_children' => ['tr'],
				'block_level' => true,
			],
			[
				'tag' => 'td',
				'before' => '<td>',
				'after' => '</td>',
				'require_parents' => ['tr'],
				'trim' => 'outside',
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '',
			],
			[
				'tag' => 'tr',
				'before' => '<tr>',
				'after' => '</tr>',
				'require_parents' => ['table'],
				'require_children' => ['td'],
				'trim' => 'both',
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '',
			],
			[
				'tag' => 'u',
				'before' => '<u>',
				'after' => '</u>',
			],
			[
				'tag' => 'url',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">$1</a>',
				'autolink' => false,
				'validate' => function (&$tag, &$data, $disabled)
				{
					$data = strtr($data, ['<br>' => '']);
					$scheme = parse_url($data, PHP_URL_SCHEME);
					if (empty($scheme))
						$data = '//' . ltrim($data, ':/');
				},
			],
			[
				'tag' => 'url',
				'type' => 'unparsed_equals',
				'quoted' => 'optional',
				'before' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">',
				'after' => '</a>',
				'autolink' => false,
				'validate' => function (&$tag, &$data, $disabled)
				{
					$scheme = parse_url($data, PHP_URL_SCHEME);
					if (empty($scheme))
						$data = '//' . ltrim($data, ':/');
				},
				'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
				'disabled_after' => ' ($1)',
			],
		];

		$no_autolink_tags = [];
		foreach ($codes as $bbcode => $code)
		{
			if (isset($code['autolink']) && !$code['autolink'])
			{
				$no_autolink_tags[$bbcode] = true;
			}
		}
		$no_autolink_tags = array_keys($no_autolink_tags);

		(new Mutatable\BBCode\Listing($codes, $no_autolink_tags))->execute();

		return [$codes, $no_autolink_tags];
	}

	/**
	 * Return an array of bbcode names, e.g. for the admin panel.
	 *
	 * @return array An array of strings representing known bbcodes, in alphabetic order.
	 */
	public static function get_all_bbcodes(): array
	{
		[$codes] = static::bbcode_definitions();
		$simple_list = [];

		foreach ($codes as $code)
		{
			$simple_list[$code['tag']] = true;
		}

		$simple_list = array_keys($simple_list);

		usort($simple_list, function ($a, $b) {
			return strcmp($a, $b);
		});
		return $simple_list;
	}
}
