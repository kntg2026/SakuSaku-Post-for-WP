<?php

namespace App\Models;

use App\Enums\DocsRetrievalMethod;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'wp_site_url', 'wp_api_key', 'wp_api_endpoint',
        'docs_retrieval_method', 'gcp_credentials', 'status', 'trial_ends_at',
        'stripe_customer_id', 'stripe_subscription_id',
        'notification_google_chat_webhook', 'notification_teams_webhook',
        'notification_events', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'docs_retrieval_method' => DocsRetrievalMethod::class,
            'gcp_credentials' => 'encrypted:json',
            'notification_events' => 'json',
            'settings' => 'json',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isOnTrial(): bool
    {
        return $this->status === TenantStatus::Trial
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active || $this->isOnTrial();
    }
}
