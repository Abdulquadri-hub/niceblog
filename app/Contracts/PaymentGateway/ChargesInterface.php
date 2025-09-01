<?php

namespace App\Contracts\PaymentGateWay;

interface ChargesInterface
{
    public function create(array $data): array;

    public function submitPin(string $pin, string $gateWayreference): array;

    public function submiteOTP(string $otp,  string $gateWayreference);

    public function submitAddress(string $address, string $gate);

    public function submitBirthday(string $birthday, string $gateWayreference);

    public function submitPhone(string $phone, string $gateWayreference);
}

;
