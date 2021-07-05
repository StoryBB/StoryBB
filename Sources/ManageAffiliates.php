<?php

/**
 * Manages affiliates.
 * @todo refactor as controller-model
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;
use StoryBB\Container;

function ManageAffiliates()
{
	global $txt, $context;

	isAllowedTo('admin_forum');

	$subActions = [
		'list_tiers' => 'AffiliateTiers',
		'add_tier' => 'AffiliateTierAdd',
		'edit_tier' => 'AffiliateTierEdit',
		'save_tier' => 'AffiliateTierSave',
		'move_tier' => 'AffiliateTierMove',
		'add_affiliate' => 'AffiliateAdd',
		'edit_affiliate' => 'AffiliateEdit',
		'save_affiliate' => 'AffiliateSave',
		'toggle_enabled' => 'AffiliateToggle',
		'update_tier' => 'AffiliateTierUpdate',
	];

	$context['sub_action'] = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'list_tiers';
	$subActions[$context['sub_action']]();
}

function AffiliateTiers()
{
	global $txt, $context, $smcFunc, $scripturl;

	$context['page_title'] = $txt['affiliates'];

	$context['tiers'] = [];

	$first_tier = true;
	$last_tier = false;

	$request = $smcFunc['db']->query('', '
		SELECT id_tier, tier_name, sort_order, image_width, image_height
		FROM {db_prefix}affiliate_tier
		ORDER BY sort_order');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$row['affiliates'] = [];
		$context['tiers'][$row['id_tier']] = $row;

		addInlineCss('form.affiliate_tier_' . $row['id_tier'] . ' img { max-width: ' . $row['image_width'] . 'px; max-height: ' . $row['image_height'] . 'px }');

		if ($first_tier)
		{
			$first_tier = false;
		}
		else
		{
			$context['tiers'][$row['id_tier']]['move']['up'] = $scripturl . '?action=admin;area=affiliates;sa=move_tier;op=up;tier=' . $row['id_tier'] . ';' . $context['session_var'] . '=' . $context['session_id'];
		}

		$context['tiers'][$row['id_tier']]['move']['down'] = $scripturl . '?action=admin;area=affiliates;sa=move_tier;op=down;tier=' . $row['id_tier'] . ';' . $context['session_var'] . '=' . $context['session_id'];
		$last_tier = $row['id_tier'];
	}
	$smcFunc['db']->free_result($request);

	if ($last_tier)
	{
		unset($context['tiers'][$last_tier]['move']['down']);
	}

	$container = Container::instance();
	$urlgenerator = $container->get('urlgenerator');

	$request = $smcFunc['db']->query('', '
		SELECT a.id_affiliate, a.affiliate_name, a.url, a.image_url, a.id_tier, a.enabled,
			f.timemodified
		FROM {db_prefix}affiliate AS a
			LEFT JOIN {db_prefix}files AS f ON (f.handler = {literal:affiliate} AND f.content_id = a.id_affiliate)
		ORDER BY a.id_tier, a.sort_order');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($context['tiers'][$row['id_tier']]))
		{
			continue;
		}

		// Instead of dealing with numbers, make this boolean for the template.
		$row['enabled'] = !empty($row['enabled']);

		if ($row['image_url'])
		{
			$row['image'] = $row['image_url'];
		}
		elseif (!empty($row['timemodified']))
		{
			$row['image'] = $urlgenerator->generate('affiliate', ['id' => $row['id_affiliate'], 'timestamp' => $row['timemodified']]);
		}
		$context['tiers'][$row['id_tier']]['affiliates'][$row['id_affiliate']] = $row;
	}
	$smcFunc['db']->free_result($request);

	$context['sub_template'] = 'admin_affiliate_tiers';

	loadJavascriptFile('jquery-ui-1.12.1-sortable.min.js', ['default_theme' => true]);
	addInlineJavascript('
	$(\'.sortable\').sortable({
		handle: ".draggable-handle",
		update: function (event, ui) {
			console.log($(this));
			$(this).closest("form").find("button.hiddenelement").removeClass("hiddenelement");
		}
	});', true);
}

function AffiliateTierAdd()
{
	global $txt, $context;

	$context['page_title'] = $txt['add_tier'];

	$context['tier_id'] = 0;
	$context['tier_name'] = $context['tier_name_escaped'] ?? '';
	$context['tier_image_width'] = $context['tier_image_width'] ?? 88;
	$context['tier_image_height'] = $context['tier_image_height'] ?? 31;
	$context['tier_desaturate'] = $context['tier_desaturate'] ?? 0;

	$context['sub_template'] = 'admin_affiliate_edit_tier';
}

function AffiliateTierEdit()
{
	global $txt, $context, $smcFunc;

	try
	{
		$row = load_affiliate_tier((int) $_GET['tier'] ?? 0);
	}
	catch (Exception $e)
	{
		redirectexit('action=admin;area=affiliates');
	}

	$context['page_title'] = $txt['edit_tier'] . ' - ' . $row['tier_name'];

	$context['tier_id'] = $row['id_tier'];
	$context['tier_name'] = $context['tier_name_escaped'] ?? $row['tier_name'];
	$context['tier_image_width'] = $context['tier_image_width'] ?? $row['image_width'];
	$context['tier_image_height'] = $context['tier_image_height'] ?? $row['image_height'];

	$context['tier_desaturate'] = $context['tier_desaturate'] ?? (int) $row['desaturate'];

	$context['sub_template'] = 'admin_affiliate_edit_tier';
}

function AffiliateTierSave()
{
	global $context, $smcFunc, $txt;

	checkSession();

	$context['tier_id'] = isset($_POST['tier_id']) ? (int) $_POST['tier_id'] : 0;
	$context['tier_name_escaped'] = StringLibrary::escape($_POST['tier_name'] ?? '');
	$context['tier_image_width'] = (int) ($_POST['image_width'] ?? 0);
	$context['tier_image_width'] = min(1500, max(0, $context['tier_image_width']));
	$context['tier_image_height'] = (int) ($_POST['image_height'] ?? 0);
	$context['tier_image_height'] = min(1000, max(0, $context['tier_image_height']));
	$context['tier_desaturate'] = !empty($_POST['desaturate']) ? 1 : 0;

	if (!empty($_POST['delete']))
	{
		$affiliates_to_delete = [];
		$request = $smcFunc['db']->query('', '
			SELECT id_affiliate
			FROM {db_prefix}affiliate
			WHERE id_tier = {int:tier}',
			[
				'tier' => $context['tier_id'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$affiliates_to_delete[] = (int) $row['id_affiliate'];
		}
		$smcFunc['db']->free_result($request);

		foreach ($affiliates_to_delete as $affiliate)
		{
			delete_affiliate($affiliate);
		}
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}affiliate_tier
			WHERE id_tier = {int:tier}',
			[
				'tier' => $context['tier_id'],
			]
		);
		redirectexit('action=admin;area=affiliates');
	}

	if (empty($context['tier_name_escaped']) || empty($context['tier_image_width']) || empty($context['tier_image_height']))
	{
		return $context['tier_id'] ? AffiliateTierEdit() : AffiliateTierAdd();
	}

	if ($context['tier_id'])
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}affiliate_tier
			SET
				tier_name = {string:tier_name},
				image_width = {int:image_width},
				image_height = {int:image_height},
				desaturate = {int:desaturate}
			WHERE id_tier = {int:tier}',
			[
				'tier_name' => $context['tier_name_escaped'],
				'image_width' => $context['tier_image_width'],
				'image_height' => $context['tier_image_height'],
				'desaturate' => $context['tier_desaturate'],
				'tier' => $context['tier_id'],
			]
		);
	}
	else
	{
		$request = $smcFunc['db']->query('', '
			SELECT COALESCE(MAX(sort_order), 0) + 1
			FROM {db_prefix}affiliate_tier');
		[$sort_order] = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$smcFunc['db']->insert('insert',
			'{db_prefix}affiliate_tier',
			['tier_name' => 'string', 'image_width' => 'int', 'image_height' => 'int', 'sort_order' => 'int', 'desaturate' => 'int'],
			[$context['tier_name_escaped'], $context['tier_image_width'], $context['tier_image_height'], $sort_order, $context['tier_desaturate']],
			['id_tier']
		);
	}

	session_flash('success', $txt['settings_saved']);
	redirectexit('action=admin;area=affiliates');
}

function AffiliateTierMove()
{
	global $smcFunc;

	checkSession('get');

	$tier = isset($_GET['tier']) ? (int) $_GET['tier'] : 0;

	$operation = $_GET['op'] ?? '';
	if ($operation != 'up' && $operation != 'down')
	{
		redirectexit('action=admin;area=affiliates');
	}

	// Load the current set up.
	$current_order = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_tier
		FROM {db_prefix}affiliate_tier
		ORDER BY sort_order');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$current_order[] = $row['id_tier'];
	}
	$smcFunc['db']->free_result($request);

	$position = array_search($tier, $current_order);
	if ($position === false)
	{
		redirectexit('action=admin;area=affiliates');
	}

	$change = false;

	if ($operation == 'up')
	{
		if ($current_order[0] != $tier) {
			$temp = $current_order[$position - 1];
			$current_order[$position - 1] = $current_order[$position];
			$current_order[$position] = $temp;
			$change = true;
		}
	}
	elseif ($operation == 'down')
	{
		if ($current_order[count($current_order) - 1] != $tier) {
			$temp = $current_order[$position + 1];
			$current_order[$position + 1] = $current_order[$position];
			$current_order[$position] = $temp;
			$change = true;
		}
	}

	if ($change)
	{
		foreach ($current_order as $sort_order => $id_tier)
		{
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}affiliate_tier
				SET sort_order = {int:sort_order}
				WHERE id_tier = {int:id_tier}',
				[
					'sort_order' => $sort_order + 1,
					'id_tier' => $id_tier,
				]
			);
		}
	}

	redirectexit('action=admin;area=affiliates');
}

function delete_affiliate(int $affiliate_id)
{
	global $smcFunc;

	delete_uploaded_affiliate($affiliate_id);

	$request = $smcFunc['db']->query('', '
		SELECT sort_order, id_tier
		FROM {db_prefix}affiliate
		WHERE id_affiliate = {int:affiliate}',
		[
			'affiliate' => $affiliate_id,
		]
	);
	$row = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	if (!empty($row['sort_order']))
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}affiliate
			SET sort_order = sort_order - 1
			WHERE sort_order > {int:sort_order}
				AND id_tier = {int:id_tier}',
			$row
		);
	}

	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}affiliate
		WHERE id_affiliate = {int:affiliate}',
		[
			'affiliate' => $affiliate_id,
		]
	);
}

function AffiliateTierUpdate()
{
	global $smcFunc;

	checkSession();

	if (empty($_POST['affiliate']) || !is_array($_POST['affiliate']))
	{
		redirectexit('action=admin;area=affiliates');
	}

	try
	{
		$tier = load_affiliate_tier((int) $_POST['tier'] ?? 0);
	}
	catch (Exception $e)
	{
		redirectexit('action=admin;area=affiliates');
	}

	$affiliates = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_affiliate
		FROM {db_prefix}affiliate
		WHERE id_tier = {int:tier}
		ORDER BY sort_order',
		[
			'tier' => $tier['id_tier'],
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$affiliates[$row['id_affiliate']] = $row['id_affiliate'];
	}
	$smcFunc['db']->free_result($request);

	$new_affiliates = [];

	// Step through whatever we were given.
	foreach ($_POST['affiliate'] as $affiliate)
	{
		if (!isset($affiliates[$affiliate]))
		{
			continue;
		}

		$new_affiliates[] = $affiliate;
		unset ($affiliates[$affiliate]);
	}

	// In case we were given bad data, also backfill with everything else.
	foreach ($affiliates as $affiliate)
	{
		$new_affiliates[] = $affiliate;
	}

	foreach ($new_affiliates as $position => $affiliate)
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}affiliate
			SET sort_order = {int:new_pos}
			WHERE id_affiliate = {int:affiliate}',
			[
				'new_pos' => $position + 1, // Because arrays are zero based.
				'affiliate' => $affiliate,
			]
		);
	}

	redirectexit('action=admin;area=affiliates');
}

function AffiliateAdd()
{
	global $txt, $context, $smcFunc;

	$context['page_title'] = $txt['add_affiliate'];

	if (!isset($context['tier']))
	{
		try
		{
			$context['tier'] = load_affiliate_tier((int) $_GET['tier'] ?? 0);
		}
		catch (Exception $e)
		{
			redirectexit('action=admin;area=affiliates');
		}
	}

	$context['affiliate'] = [
		'id_affiliate' => 0,
		'name' => $context['affiliate_name_escaped'] ?? '',
		'url' => $context['affiliate_url'] ?? '',
		'enabled' => $context['affiliate_enabled'] ?? 1,
		'current_image' => $txt['current_image_none'],
		'image_url' => $context['affiliate_image_url'] ?? '',
	];

	$context['affiliate_image_hint'] = sprintf($txt['affiliate_image_hint'], $context['tier']['image_width'], $context['tier']['image_height']);

	$context['sub_template'] = 'admin_affiliate_edit_affiliate';

	addInlineCss('.affiliate_tier_' . $context['tier']['id_tier'] . ' img { max-width: ' . $context['tier']['image_width'] . 'px; max-height: ' . $context['tier']['image_height'] . 'px }');
}

function AffiliateEdit()
{
	global $txt, $context, $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT a.id_affiliate, a.affiliate_name AS name, a.url, a.image_url, a.id_tier, a.enabled,
			f.timemodified
		FROM {db_prefix}affiliate AS a
			LEFT JOIN {db_prefix}files AS f ON (f.handler = {literal:affiliate} AND f.content_id = a.id_affiliate)
		WHERE a.id_affiliate = {int:affiliate}',
		[
			'affiliate' => (int) $_GET['affiliate'] ?? 0,
		]
	);
	$context['affiliate'] = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	if (empty($context['affiliate']))
	{
		redirectexit('action=admin;area=affiliateas');
	}

	// If we're coming from AffiliateSave() we probably have other data to overwrite with.
	if (isset($context['affiliate_name_escaped']))
	{
		$context['affiliate']['name'] = $context['affiliate_name_escaped'];
		$context['affiliate']['url'] = $context['affiliate_url'];
		$context['affiliate']['image_url'] = $context['affiliate_image_url'];
		$context['affiliate']['enabled'] = $context['affiliate_enabled'];
	}

	$context['affiliate']['enabled'] = !empty($context['affiliate']['enabled']);

	$context['tier'] = load_affiliate_tier($context['affiliate']['id_tier']);

	$context['affiliate']['current_image'] = $txt['current_image_none'];
	if (!empty($context['affiliate']['timemodified']))
	{
		// We have a previous upload. Add that image.
		$container = Container::instance();
		$urlgenerator = $container->get('urlgenerator');
		$image_url = $urlgenerator->generate('affiliate', ['id' => $context['affiliate']['id_affiliate'], 'timestamp' => $context['affiliate']['timemodified']]);
		$context['affiliate']['current_image'] = '<img src="' . $image_url . '" alt="">';
	}

	if (!empty($context['affiliate']['image_url']))
	{
		$context['affiliate']['current_image'] = '<img src="' . $context['affiliate']['image_url'] . '" alt="">';
	}

	$context['page_title'] = $txt['edit_affiliate'];

	$context['sub_template'] = 'admin_affiliate_edit_affiliate';

	addInlineCss('.affiliate_tier_' . $context['tier']['id_tier'] . ' img { max-width: ' . $context['tier']['image_width'] . 'px; max-height: ' . $context['tier']['image_height'] . 'px }');
}

function AffiliateSave()
{
	global $txt, $context, $smcFunc;

	checkSession();

	if (!empty($_POST['delete']))
	{
		delete_affiliate((int) ($_REQUEST['affiliate_id'] ?? 0));
		redirectexit('action=admin;area=affiliates');
	}

	try
	{
		$context['tier'] = load_affiliate_tier((int) $_REQUEST['tier_id'] ?? 0);
	}
	catch (Exception $e)
	{
		redirectexit('action=admin;area=affiliates');
	}

	$context['affiliate'] = [
		'id_affiliate' => (int) ($_REQUEST['affiliate_id'] ?? 0),
	];
	$context['affiliate_name_escaped'] = StringLibrary::escape($_POST['affiliate_name'] ?? '', ENT_QUOTES);
	$context['affiliate_url'] = validate_url($_POST['affiliate_url'] ?? '') ? $_POST['affiliate_url'] : '';

	$context['affiliate_enabled'] = !empty($_POST['affiliate_enabled']) ? 1 : 0;

	$context['affiliate_image_url'] = validate_url($_POST['affiliate_image_url'] ?? '') ? $_POST['affiliate_image_url'] : '';
	// If there's no image URL and we're uploading a new image... upload a new image.
	$valid_types = [
		IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
		IMAGETYPE_PNG => ['png', 'image/png'],
		IMAGETYPE_GIF => ['gif', 'image/gif'],
	];
	if (empty($context['affiliate_image_url']) && !empty($_FILES['affiliate_image_upload']))
	{
		$imagesize = @getimagesize($_FILES['affiliate_image_upload']['tmp_name']);
		if ($imagesize && isset($valid_types[$imagesize[2]]))
		{
			[$src_width, $src_height] = $imagesize;
			// It's a valid image, let's see if we need to resize it any.
			if ($src_width > $context['tier']['image_width'] || $src_height > $context['tier']['image_height'])
			{
				if (round($imagesize[1] * $context['tier']['image_width'] / $src_width) <= $context['tier']['image_height'])
				{
					// Try to rescale to fit width first.
					$dst_width = $context['tier']['image_width'];
					$dst_height = round($src_height * $context['tier']['image_width'] / $src_width);
				}
				else
				{
					// Rescale to fit height.
					$dst_width = round($src_width * $context['tier']['image_height'] / $src_height);
					$dst_height = $context['tier']['image_height'];
				}

				$dst_img = imagecreatetruecolor($dst_width, $dst_height);

				// Deal nicely with a PNG - because we can.
				imagealphablending($dst_img, false);
				imagesavealpha($dst_img, true);

				// Resize it!
				$src_img = imagecreatefromstring(file_get_contents($_FILES['affiliate_image_upload']['tmp_name']));
				imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);

				imagepng($dst_img, $_FILES['affiliate_image_upload']['tmp_name']);
				imagedestroy($src_img);
				imagedestroy($dst_img);
			}
		}

		$context['affiliate_uploaded'] = $_FILES['affiliate_image_upload']['tmp_name'];
	}

	$context['errors'] = [];
	if (empty($context['affiliate_name_escaped']))
	{
		$context['errors'] = $txt['affiliate_name_missing'];
	}

	if (empty($context['affiliate_url']))
	{
		$context['errors'] = $txt['affiliate_url_missing'];
	}

	if (!empty($context['errors']))
	{
		return !empty($context['affiliate']['id_affiliate']) ? AffiliateAdd() : AffiliateEdit();
	}

	// OK so we're good to save.
	if (!empty($context['affiliate']['id_affiliate']))
	{
		// Updating an existing affiliate.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}affiliate
			SET
				affiliate_name = {string:affiliate_name},
				url = {string:url},
				image_url = {string:image_url},
				enabled = {int:enabled}
			WHERE id_affiliate = {int:id_affiliate}',
			[
				'id_affiliate' => $context['affiliate']['id_affiliate'],
				'affiliate_name' => $context['affiliate_name_escaped'],
				'url' => $context['affiliate_url'],
				'image_url' => $context['affiliate_image_url'],
				'enabled' => $context['affiliate_enabled'] ? 1 : 0,
			]
		);
	}
	else
	{
		$request = $smcFunc['db']->query('', '
			SELECT COALESCE(MAX(sort_order), 0) + 1
			FROM {db_prefix}affiliate
			WHERE id_tier = {int:tier}',
			[
				'tier' => $context['tier']['id_tier'],
			]
		);
		[$sort_order] = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$context['affiliate']['id_affiliate'] = $smcFunc['db']->insert('insert',
			'{db_prefix}affiliate',
			[
				'id_tier' => 'int', 'affiliate_name' => 'string', 'url' => 'string', 'image_url' => 'string',
				'sort_order' => 'int', 'enabled' => 'int', 'timecreated' => 'int', 'added_by' => 'int',
			],
			[
				$context['tier']['id_tier'], $context['affiliate_name_escaped'], $context['affiliate_url'], $context['affiliate_image_url'],
				$sort_order, $context['affiliate_enabled'], time(), $context['user']['id'],
			],
			['id_affiliate'],
			1
		);
	}

	// If we're using an image, delete any preexisting uploads there are.
	$container = Container::instance();
	$filesystem = $container->get('filesystem');

	if ($context['affiliate_image_url'])
	{
		delete_uploaded_affiliate((int) $context['affiliate']['id_affiliate']);
	}

	if (!empty($context['affiliate_uploaded']))
	{
		// Remove any existing one first.
		delete_uploaded_affiliate((int) $context['affiliate']['id_affiliate']);

		$filename = 'affiliate_' . $context['affiliate']['id_affiliate'] . '.' . $valid_types[$imagesize[2]][0];
		$mimetype = $valid_types[$imagesize[2]][1];

		$filesystem->upload_physical_file($context['affiliate_uploaded'], $filename, $mimetype, 'affiliate', $context['affiliate']['id_affiliate']);
	}

	redirectexit('action=admin;area=affiliates');
}

function AffiliateToggle()
{
	global $smcFunc;

	checkSession('get');

	$smcFunc['db']->query('', '
		UPDATE {db_prefix}affiliate
		SET enabled = CASE WHEN enabled = 1 THEN 0 ELSE 1 END
		WHERE id_affiliate = {int:affiliate}
		LIMIT 1',
		[
			'affiliate' => (int) ($_GET['affiliate'] ?? 0),
		]
	);
	redirectexit('action=admin;area=affiliates');
}

function validate_url(string $url): bool
{
	// If it doesn't start with a scheme we care about, it isn't going to be valid.
	if (!preg_match('~^https?://~i', $url))
	{
		return false;
	}

	// General test.
	if (!filter_var($url, FILTER_VALIDATE_URL))
	{
		return false;
	}

	return true;
}

function delete_uploaded_affiliate(int $affiliate_id): bool
{
	$container = Container::instance();
	$filesystem = $container->get('filesystem');

	try
	{
		$filesystem->delete_file('affiliate', $affiliate_id);
	}
	catch (Exception $e)
	{
		return true;
	}

	return false;
}

function load_affiliate_tier(int $tier): array
{
	global $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT id_tier, tier_name, sort_order, image_width, image_height, desaturate
		FROM {db_prefix}affiliate_tier
		WHERE id_tier = {int:tier}',
		[
			'tier' => $tier,
		]
	);
	$row = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);
	if (!$row)
	{
		throw new Exception('Invalid tier');
	}

	return $row;
}
