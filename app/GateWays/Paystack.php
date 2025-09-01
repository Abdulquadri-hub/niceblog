<?php

namespace App\GateWays;

use App\Contracts\PaymentGateway\{
    TransactionsInterface,
    ChargesInterface,
    WebhookInterface
};
use App\Enums\Payments\Channels;
use App\Enums\Payments\Currencies;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Paystack implements TransactionsInterface {

    private $client;
    private $secretKey;
    private $baseUrl;

    public function __construct()
    {
        $this->secretKey = Config::get('services.paystack.secretKey');
        $this->baseUrl = Config::get('services.paystack.baseUrl', 'https://api.paystack.co');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    public function initialize(array $gateWaydata): array
    {
        return $this->request('POST', "/transaction/initialize", $gateWaydata);
    }

    public function verify(string $gateWayreference): array
    {
        return $this->request('GET', "/transaction/verify/{$gateWayreference}");
    }

    public function fetch(int $gateWayId): array
    {
        return $this->request("GET", "/transaction/{$gateWayId}");
    }

    public function chargeAuthorization(array $gateWaydata): array
    {
        return $this->request('POST', "/transaction/charge_authorization", $gateWaydata);
    }

    public function getSupportedCurrencies(): array
    {
        return [
            Currencies::Nigeria_NAIRA->value,
            Currencies::GHANAIAN_CEDI->value,
            Currencies::US_DOLLAR->value,
            Currencies::SOUTH_AFRICAN_RAND->value,
            Currencies::WEST_AFRICAN_CFA_FRANC->value
        ];
    }

    public function getPaymentChannels(): array
    {
        return [
            Channels::APPLE_PAY->value,
            Channels::CARD->value,
            Channels::BANK->value,
            Channels::BANK_TRANSFER->value,
            Channels::EFT->value,
            Channels::QR->value,
            Channels::MOBILE_MONEY->value,
            Channels::USSD->value,
        ];
    }

    protected function request(string $method, string $uri = '', array $options = []): array  {
        try {
            $response =  $this->client->request($method, $uri, ['json' => $options]);
            if(!$response) {
                throw new RuntimeException("Empty response from paystack");
            }

            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $th) {
            Log::error("Error Processing Paystack Request", [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            throw new RuntimeException("Error Processing Paystack Request {$th->getMessage()}", 0, $th);
        }
    }
}
