<?php

namespace App\Repositories\Landlord;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionRepository
{
    public function create(array $transactionData): ?Transaction {
        return Transaction::create($transactionData);
    }

    public function updateTransactionStatus(Tenant $tenant, string $reference, string $status): bool {
        return $tenant->transactions->firstWhere('reference', $reference)->update([
            'status' => $status
        ]);
    }
}
