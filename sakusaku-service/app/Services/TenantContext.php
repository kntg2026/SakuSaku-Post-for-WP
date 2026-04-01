<?php

namespace App\Services;

use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): Tenant
    {
        if (!$this->tenant) {
            throw new \RuntimeException('Tenant not resolved');
        }
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function isResolved(): bool
    {
        return $this->tenant !== null;
    }
}
