<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ProcessExpiredTrials extends Command
{
    protected $signature = 'tenants:process-expired-trials';
    protected $description = 'Suspend tenants with expired trials and no active subscription';

    public function handle(): int
    {
        $count = Tenant::where('status', TenantStatus::Trial)
            ->where('trial_ends_at', '<', now())
            ->whereDoesntHave('subscriptions', fn($q) =>
                $q->whereIn('stripe_status', ['active', 'trialing'])
            )
            ->update(['status' => TenantStatus::Suspended->value]);

        $this->info("Suspended {$count} expired trial tenant(s).");

        return 0;
    }
}
