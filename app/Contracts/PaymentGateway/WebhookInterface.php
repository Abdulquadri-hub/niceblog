<?php

namespace App\Contracts\PaymentGateWay;

interface WebhookInterface
{
    public function verifySignature(array $payload, string $signature): array;

    public function extractEventData(array $payload): array;
}
