<?php

namespace App\Contracts\Services;

interface TenantSubscriptionServiceInterface
{
    public function process(array $subscriptionData): array;

    public function verify(string $subscriptionReference): array;

    public function handleWebhook(array $payload, string $signature);
}
