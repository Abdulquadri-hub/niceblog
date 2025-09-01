<?php

namespace App\Http\TenantFinders;

use Illuminate\Http\Request;
use App\Models\Landlord\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class PathTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        // for local development - extract tenant from path
        if(app()->environment(['local', 'testing'])) {

            return $this->findByPath($request);
        }

        // for production -extract from domain/subdomain
        return $this->findbyDomain($request);
    }


    //find tenant by path parameter
    protected function findByPath(Request $request): ?Tenant {
        $tenantSlug = $request->route('tenant');

        if(!$tenantSlug) {
            return null;
        }

        return $this->findTenant(['slug', $tenantSlug]);

    }

    // find by domain
    protected function findbyDomain(Request $request): ?Tenant {
        $host = $request->getHost();  //apptenant.com

        // by custom domain
        $tenant = $this->findTenant(['domain', $host]);
        if($tenant) {
            return $tenant;
        }

        // by sub domain  where host can be tenant-subdomain.myapp.com
        $baseDomain = config('app.tenant_domain');  //myapp.com
        if($baseDomain  && str_contains($host, $baseDomain)) {
            $subdomain = str_replace('.' . $baseDomain, '', $host);
            return $this->findTenant(['subdomain', $subdomain]);
        }

        return null;
    }

    protected function findTenant(array $criteria): ?Tenant {
        return Tenant::active()->where($criteria)->first();
    }
}
