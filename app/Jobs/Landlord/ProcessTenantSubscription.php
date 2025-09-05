<?php

namespace App\Jobs\Landlord;

use App\GateWays\Paystack;
use App\Repositories\Landlord\TransactionRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProcessTenantSubscription implements ShouldQueue, NotTenantAware
{
     use Dispatchable, InteractsWithQueue, Queueable,SerializesModels;

    protected $subscriptionData;
    protected $switchGateway = 'paystack';
    protected $gateway;

    public $tries = 3;
    public $maxException = 3;
    public $timeout = 300;
    public $backoff = [60, 120, 300];

    public function __construct(array $subscriptionData)
    {
        $this->subscriptionData = $subscriptionData;

        switch ($this->switchGateway) {
            case 'paystack':
                $this->gateway = app(Paystack::class);
                break;
            default:
                $this->gateway = app(Paystack::class);
                break;
        }
    }

    public function handle(): void
    {
        try {
            Log::info("Processing tenant subscription for: {$this->subscriptionData['tenant_id']}");

            $transactionRepo = app(TransactionRepository::class);

            $response =  $this->gateway->initialize($this->subscriptionData);

            if(isset($response['status']) && !$response['status'])
            {
                Log::error("Error processing subscription", [
                    'response' => $response
                ]);

                $this->subscriptionData['gateway'] = $this->switchGateway;
                $this->subscriptionData['processed_at'] = now();
                $this->subscriptionData['tenant_id'] = $this->subscriptionData['tenant_id'];

                $transactionRepo->create($this->subscriptionData);
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
