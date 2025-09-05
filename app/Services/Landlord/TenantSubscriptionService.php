<?php

namespace App\Services\Landlord;

use App\Contracts\Services\TenantSubscriptionInterface;
use App\Enums\Payments\Currencies;
use Exception;
use App\GateWays\Paystack;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantSubscription;
use App\Models\Landlord\Transaction;
use App\Repositories\Landlord\TenantRepository;
use App\Repositories\Landlord\TenantSubscriptionRepository;
use Illuminate\Support\Str;
use App\Repositories\Landlord\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantSubscriptionService implements TenantSubscriptionInterface
{
    private $defaultGateway = 'paystack';
    private $gateway;

    public function __construct(
        protected TransactionRepository $transactionRepo,
        protected TenantSubscriptionRepository $subscriptionRepo,
        protected TenantRepository $tenantRepo,
    )
    {
        switch ($this->defaultGateway) {
            case 'paystack':
                $this->gateway = app(Paystack::class);
                break;
            default:
                $this->gateway = app(Paystack::class);
                break;
        }
    }

    public function subscribe(array $subscriptionData): array {
        try {

            $subscriptionData['currency']  = $subscriptionData['currency'] ??Currencies::Nigeria_NAIRA->value;
            $subscriptionData['reference'] = $this->generateReference();
            $subscriptionData['meta_data'] = [
                'plan_id' => $subscriptionData['plan_id'],
            ];

            return DB::connection('landlord')->transaction(function () use ($subscriptionData) {

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

    public function upgrade(int $tenantId, array $subscriptionData): array
    { 
        try {

            $tenant = $this->tenantRepo->fetchTenant('id', $tenantId);
            if(!$tenant) {
                throw new Exception("Tenant not found");
            }

            $plan = SubscriptionPlan::active()->Where('id', $subscriptionData['plan_id'])->get();
            if(!$plan) {
                throw new Exception("Plan not found");
            }

            $subscriptionData['currency']  = $subscriptionData['currency'] ??Currencies::Nigeria_NAIRA->value;
            $subscriptionData['reference'] = $this->generateReference();
            $subscriptionData['tenant_id'] = $tenant->id;
            $subscriptionData['meta_data'] = [
                'plan_id' => $subscriptionData['plan_id'],
            ];

            $currentSubscription = $this->subscriptionRepo->getCurrentSubscription($tenant);
            if(!$currentSubscription) {
                return $this->subscribe($subscriptionData);
            }

            return DB::connection('landlord')->transaction( function () use ($subscriptionData) {

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
            Log::error("Error upgrading tenant subscription", [
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);

            throw new Exception("Error upgrading tenant subscription: {$th->getMessage()}");
        }
    }

    public function cancel(Tenant $tenant): bool
    {
        return true;
    }

    public function verify(array $payload, string $signature)
    {
        //
    }

    protected function generateReference(): string {
        return  "REF_" . Str::random(10);
    }
}
