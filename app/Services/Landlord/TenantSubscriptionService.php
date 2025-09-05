<?php

namespace App\Services\Landlord;

use Exception;
use App\GateWays\Paystack;
use App\Jobs\Landlord\ProcessTenantSubscription;
use App\Models\Landlord\Transaction;
use Illuminate\Support\Str;
use App\Payment\Enums\Currency;
use App\Repositories\Landlord\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantSubscriptionService
{
    private $defaultGateway = 'paystack';
    private $gateway;
    protected $transactionRepo;

    public function __construct(TransactionRepository $transactionRepo)
    {
        $this->transactionRepo = $transactionRepo;

        switch ($this->defaultGateway) {
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
                $subscriptionData['currency']  = $subscriptionData['currency'] ?? Currency::NGN->value;
                $subscriptionData['reference'] = $this->generateReference();
                $subscriptionData['meta_data'] = [
                    'plan_id' => $subscriptionData['plan_id'],
                ];

                return DB::transaction(function ()  use ($subscriptionData) {

                    $response = $this->gateway->initialize($subscriptionData);
                    if(isset($response['status']) && !$response['status']) {
                        Log::error("Error processing subscription", [
                            'response' => $response
                        ]);
                        throw new Exception("Error processing subscription");
                    }

                    $subscriptionData['gateway'] = $this->defaultGateway;
                    $subscriptionData['processed_at'] = now();

                    $this->transactionRepo->create($subscriptionData);

                    return [
                     'message' => $response['message'] ?? null,
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

    // public funct

    protected function generateReference(): string {
        return  "REF_" . Str::random(10);
    }
}
