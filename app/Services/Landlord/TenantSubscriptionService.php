<?php

namespace App\Services\Landlord;

use Exception;
use App\GateWays\Paystack;
use App\Models\Landlord\Transaction;
use Illuminate\Support\Str;
use App\Payment\Enums\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantSubscriptionService
{
    private $switchGateway = 'paystack';
    private $gateway;

    public function __construct()
    {
        switch ($this->switchGateway) {
            case 'paystack':
                $this->gateway = app(Paystack::class);
                break;
            default:
                $this->gateway = app(Paystack::class);
                break;
        }
    }

    public function process(array $subscriptionData): array {
        try {
            return DB::transaction(function ()  use ($subscriptionData) {
                $data = [
                    'amount' => $subscriptionData['amount'],
                    'email' => $subscriptionData['email'],
                    'currency' => $subscriptionData['currency'] ?? Currency::NGN->value,
                    'reference' => $this->generateReference(),
                    'meta_data' => [
                        'plan_id' => $subscriptionData['plan_id'],
                        'tenant_id' => $subscriptionData['tenant_id'],
                    ]
                ];

                $response = $this->gateway->initialize($data);
                if(!$response['status']) {
                    Log::error("Error processing subscription", [
                        'response' => $response
                    ]);
                    throw new Exception("Error processing subscription");
                }

                $data['gateway'] = $this->switchGateway;
                $data['processed_at'] = now();

                Transaction::create($data);

               return [
                'message' => "Teanant " . $response['message'] ?? null,
                'data' => $response['data'] ?? null,
               ];
            });

        } catch (\Throwable $th) {
            Log::error("Error initiating Tenant Subscription", [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            throw new Exception("Error initiating Tenant Subscription {$th->getMessage()}", 0, $th);
        }
    }

    protected function generateReference(): string {
        return  "REF_" . Str::random(10);
    }
}
