<?php

/**
 * Displays the issue-warning page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Container;

class Subscriptions extends AbstractProfileController
{
	public function display_action()
	{
		global $context;

		$this->load_subscription_information();

		// Simple "done"?
		if (isset($_GET['done']))
		{
			$_GET['sub_id'] = (int) $_GET['sub_id'];

			// Must exist but let's be sure...
			if (isset($context['current'][$_GET['sub_id']]))
			{
				// What are the details like?
				$current_pending = sbb_json_decode($context['current'][$_GET['sub_id']]['pending_details'], true);
				if (!empty($current_pending))
				{
					$current_pending = array_reverse($current_pending);
					foreach ($current_pending as $id => $sub)
					{
						// Just find one and change it.
						if ($sub[0] == $_GET['sub_id'] && $sub[3] == 'prepay')
						{
							$current_pending[$id][3] = 'payback';
							break;
						}
					}

					// Save the details back.
					$pending_details = json_encode($current_pending);

					$smcFunc['db']->query('', '
						UPDATE {db_prefix}log_subscribed
						SET payments_pending = payments_pending + 1, pending_details = {string:pending_details}
						WHERE id_sublog = {int:current_subscription_id}
							AND id_member = {int:selected_member}',
						[
							'current_subscription_id' => $context['current'][$_GET['sub_id']]['id'],
							'selected_member' => $memID,
							'pending_details' => $pending_details,
						]
					);
				}
			}

			$context['sub_template'] = 'subscription_paid_done';
			return;
		}

		// We're at the page for just showing off subscriptions.
		$context['sub_template'] = 'subscription_user_choice';
	}

	public function post_action()
	{
		global $context, $txt, $smcFunc, $modSettings, $scripturl;
		$this->load_subscription_information();

		$memID = $this->params['u'];

		// If this is confirmation then it's simpler...
		if (!isset($_GET['confirm']) || !isset($_POST['sub_id']) || !is_array($_POST['sub_id']))
		{
			redirectexit('action=profile;area=subscriptions;u=' . $memID);
		}

		// Hopefully just one.
		foreach ($_POST['sub_id'] as $k => $v)
			$ID_SUB = (int) $k;

		if (!isset($context['subscriptions'][$ID_SUB]) || $context['subscriptions'][$ID_SUB]['active'] == 0)
			fatal_lang_error('paid_sub_not_active');

		// Simplify...
		$context['sub'] = $context['subscriptions'][$ID_SUB];
		$period = 'xx';
		if ($context['sub']['flexible'])
			$period = isset($_POST['cur'][$ID_SUB]) && isset($context['sub']['costs'][$_POST['cur'][$ID_SUB]]) ? $_POST['cur'][$ID_SUB] : 'xx';

		// Check we have a valid cost.
		if ($context['sub']['flexible'] && $period == 'xx')
			fatal_lang_error('paid_sub_not_active');

		// Sort out the cost/currency.
		$context['currency'] = $modSettings['paid_currency_code'];
		$context['recur'] = $context['sub']['repeatable'];

		if ($context['sub']['flexible'])
		{
			// Real cost...
			$context['value'] = $context['sub']['costs'][$_POST['cur'][$ID_SUB]];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']) . '/' . $txt[$_POST['cur'][$ID_SUB]];
			// The period value for paypal.
			$context['paypal_period'] = strtoupper(substr($_POST['cur'][$ID_SUB], 0, 1));
		}
		else
		{
			// Real cost...
			$context['value'] = $context['sub']['costs']['fixed'];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']);

			// Recur?
			preg_match('~(\d*)(\w)~', $context['sub']['real_length'], $match);
			$context['paypal_unit'] = $match[1];
			$context['paypal_period'] = $match[2];
		}

		// Setup the gateway context.
		$gateways = $this->load_gateways();
		$context['gateways'] = [];
		foreach ($gateways as $id => $gateway)
		{
			$fields = $gateways[$id]->fetchGatewayFields($context['sub']['id'] . '+' . $memID, $context['sub'], $context['value'], $period, $scripturl . '?action=profile;u=' . $memID . ';area=subscriptions;sub_id=' . $context['sub']['id'] . ';done');
			if (!empty($fields['form']))
				$context['gateways'][] = $fields;
		}

		// Bugger?!
		if (empty($context['gateways']))
			fatal_error($txt['paid_admin_not_setup_gateway']);

		// Now we are going to assume they want to take this out ;)
		$new_data = [$context['sub']['id'], $context['value'], $period, 'prepay'];
		if (isset($context['current'][$context['sub']['id']]))
		{
			// What are the details like?
			$current_pending = [];
			if ($context['current'][$context['sub']['id']]['pending_details'] != '')
				$current_pending = sbb_json_decode($context['current'][$context['sub']['id']]['pending_details'], true);
			// Don't get silly.
			if (count($current_pending) > 9)
				$current_pending = [];
			$pending_count = 0;
			// Only record real pending payments as will otherwise confuse the admin!
			foreach ($current_pending as $pending)
				if ($pending[3] == 'payback')
					$pending_count++;

			if (!in_array($new_data, $current_pending))
			{
				$current_pending[] = $new_data;
				$pending_details = json_encode($current_pending);

				$smcFunc['db']->query('', '
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = {int:pending_count}, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_item}
						AND id_member = {int:selected_member}',
					[
						'pending_count' => $pending_count,
						'current_subscription_item' => $context['current'][$context['sub']['id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					]
				);
			}

		}
		// Never had this before, lovely.
		else
		{
			$pending_details = json_encode([$new_data]);
			$smcFunc['db']->insert('',
				'{db_prefix}log_subscribed',
				[
					'id_subscribe' => 'int', 'id_member' => 'int', 'status' => 'int', 'payments_pending' => 'int', 'pending_details' => 'string-65534',
					'start_time' => 'int', 'vendor_ref' => 'string-255',
				],
				[
					$context['sub']['id'], $memID, 0, 0, $pending_details,
					time(), '',
				],
				['id_sublog']
			);
		}

		// Change the template.
		$context['sub_template'] = 'subscription_choose_payment';
	}

	protected function load_subscription_information()
	{
		global $context, $txt, $sourcedir, $modSettings, $smcFunc, $scripturl;

		loadLanguage('ManagePaid');

		// Load all of the subscriptions.
		$memID = $this->params['u'];

		require_once($sourcedir . '/ManagePaid.php');
		loadSubscriptions();
		$context['member']['id'] = $memID;

		// Remove any invalid ones.
		foreach ($context['subscriptions'] as $id => $sub)
		{
			// Work out the costs.
			$costs = sbb_json_decode($sub['real_cost'], true);

			$cost_array = [];
			if ($sub['real_length'] == 'F')
			{
				foreach ($costs as $duration => $cost)
				{
					if ($cost != 0)
						$cost_array[$duration] = $cost;
				}
			}
			else
			{
				$cost_array['fixed'] = $costs['fixed'];
			}

			if (empty($cost_array))
				unset($context['subscriptions'][$id]);
			else
			{
				$context['subscriptions'][$id]['member'] = 0;
				$context['subscriptions'][$id]['subscribed'] = false;
				$context['subscriptions'][$id]['costs'] = $cost_array;
			}
		}

		$gateways = $this->load_gateways();

		// Get the current subscriptions.
		$request = $smcFunc['db']->query('', '
			SELECT id_sublog, id_subscribe, start_time, end_time, status, payments_pending, pending_details
			FROM {db_prefix}log_subscribed
			WHERE id_member = {int:selected_member}',
			[
				'selected_member' => $memID,
			]
		);
		$context['current'] = [];
		$admin_forum = allowedTo('admin_forum');
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// The subscription must exist!
			if (!isset($context['subscriptions'][$row['id_subscribe']]))
				continue;

			$context['current'][$row['id_subscribe']] = [
				'id' => $row['id_sublog'],
				'sub_id' => $row['id_subscribe'],
				'hide' => $row['status'] == 0 && $row['end_time'] == 0 && $row['payments_pending'] == 0,
				'name' => $context['subscriptions'][$row['id_subscribe']]['name'],
				'start' => timeformat($row['start_time'], false),
				'end' => $row['end_time'] == 0 ? $txt['not_applicable'] : timeformat($row['end_time'], false),
				'pending_details' => $row['pending_details'],
				'status' => $row['status'],
				'status_text' => $row['status'] == 0 ? ($row['payments_pending'] ? $txt['paid_pending'] : $txt['paid_finished']) : $txt['paid_active'],
				'can_modify' => $admin_forum,
			];

			if ($row['status'] == 1)
				$context['subscriptions'][$row['id_subscribe']]['subscribed'] = true;

			$context['subscriptions'][$row['id_subscribe']]['sublog'] = $row['id_sublog'];
		}
		$smcFunc['db']->free_result($request);
	}

	protected function load_gateways(): array
	{
		static $gateways = null;

		if ($gateways === null)
		{
			// Work out what gateways are enabled.
			$gateways = loadPaymentGateways();
			foreach ($gateways as $id => $gateway)
			{
				$gateways[$id] = new $gateway['class'];
				if (!$gateways[$id]->gatewayEnabled())
					unset($gateways[$id]);
			}

			// No gateways yet?
			if (empty($gateways))
				fatal_error($txt['paid_admin_not_setup_gateway']);
		}

		return $gateways;
	}
}
