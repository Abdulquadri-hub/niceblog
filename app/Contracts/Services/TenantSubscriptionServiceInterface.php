<?php

namespace App\Contracts\Services;

interface TenantSubscriptionServiceInterface
{
    public function subscribe(array $subscriptionData): array;

    public function upgrade(string $subscriptionReference): array;

    public function handleWebhook(array $payload, string $signature);
}
