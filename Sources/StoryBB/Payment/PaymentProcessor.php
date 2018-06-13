<?php

/**
 * This interface defines what a payment processor must implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Payment;

interface PaymentProcessor
{
	public function getShortTitle(): string;

	public function getDisplayTitle(): string;

	public function getGatewaySettings(): array;

	public function gatewayEnabled(): bool;

	public function fetchGatewayFields(string $unique_id, array $sub_data, $value, string $period, string $return_url): array;

	public function isValid(): bool;

	public function precheck(): array;

	public function isRefund(): bool;

	public function isSubscription(): bool;

	public function isPayment(): bool;

	public function isCancellation(): bool;

	public function performCancel($subscription_id, $member_id, $subscription_info);

	public function getCost();

	public function close();
}
