<?php

namespace App\Http\Controllers\Landlord;

use App\Enums\Status;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Landlord\TenantService;
use App\Http\Requests\landlord\TenantRequest;

class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService) {
        $this->tenantService = $tenantService;
    }

    public function save(TenantRequest $request) {

        try {
            $validatedData = $request->validated();

            $tenant = $this->tenantService->create($validatedData);

            return $this->successResponse(
                $tenant,
                'Tenant created successfully',
                Status::CREATED->value
            );

        } catch (\Throwable $th) {
            return $this->errorResponse(
                $th->getMessage(),
                Status::UNPROCESSABLE_ENTITY->value
            );
        }
    }
}
