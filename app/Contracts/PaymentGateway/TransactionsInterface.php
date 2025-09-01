<?php

namespace App\Contracts\PaymentGateway;

interface TransactionsInterface
{
    public function initialize(array $gateWaydata): array ;

    public function verify(string $gateWayreference): array;

    public function fetch(int $gateWayid): array;

    public function chargeAuthorization(array $gateWaydata): array;

    public function getSupportedCurrencies(): array;

    public function getPaymentChannels(): array;
}
