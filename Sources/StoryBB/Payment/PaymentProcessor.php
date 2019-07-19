<?php

/**
 * This interface defines what a payment processor must implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Payment;

/**
 * This interface defines what a payment processor must implement.
 */
interface PaymentProcessor
{
	/**
	 * Returns a short title for display.
	 *
	 * @return string The short title of the payment processor.
	 */
	public function getShortTitle(): string;

	/**
	 * Returns the display title.
	 *
	 * @return string The display title of the payment processor.
	 */
	public function getDisplayTitle(): string;

	/**
	 * Return the admin settings for this gateway
	 *
	 * @return array An array of settings data
	 */
	public function getGatewaySettings(): array;

	/**
	 * Is this enabled for new payments?
	 *
	 * @return boolean Whether this gateway is enabled
	 */
	public function gatewayEnabled(): bool;

	/**
	 * Provide the fields to build a payment transaction form
	 *
	 * Called from Profile-Actions.php to return a unique set of fields for the given gateway
	 * plus all the standard ones for the subscription form
	 *
	 * @param string $unique_id The unique ID of this gateway
	 * @param array $sub_data Subscription data
	 * @param int|float $value The amount of the subscription
	 * @param string $period
	 * @param string $return_url The URL to return the user to after processing the payment
	 * @return array An array of data for the form
	 */
	public function fetchGatewayFields(string $unique_id, array $sub_data, $value, string $period, string $return_url): array;

	/**
	 * This function returns true/false for whether this gateway thinks the data is intended for it.
	 *
	 * @return boolean Whether this gateway thinks the data is valid
	 */
	public function isValid(): bool;

	/**
	 * Perform prechecks of subscription data with payment provider (e.g. with PayPal, receive an IPN and re-post
	 * back to the provider to verify the data came from PayPal)
	 *
	 * @return string A string containing the subscription ID and member ID, separated by a +
	 */
	public function precheck(): array;

	/**
	 * Is this a refund?
	 *
	 * @return boolean Whether this is a refund
	 */
	public function isRefund(): bool;

	/**
	 * Is this a subscription?
	 *
	 * @return boolean Whether this is a subscription
	 */
	public function isSubscription(): bool;

	/**
	 * Is this a normal payment?
	 *
	 * @return boolean Whether this is a normal payment
	 */
	public function isPayment(): bool;

	/**
	 * Is this a cancellation?
	 *
	 * @return boolean Whether this is a cancellation
	 */
	public function isCancellation(): bool;

	/**
	 * Things to do in the event of a cancellation
	 *
	 * @param string $subscription_id
	 * @param int $member_id
	 * @param array $subscription_info
	 */
	public function performCancel($subscription_id, $member_id, $subscription_info);

	/**
	 * How much was paid?
	 *
	 * @return float The amount paid
	 */
	public function getCost();

	/**
	 * Record the transaction reference and exit
	 */
	public function close();
}
