<?php

namespace App\Http\Controllers\Billing;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    public function handleWebhook(Request $request): Response
    {
        // Stripe署名検証
        $sigHeader = $request->header('stripe-signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if ($webhookSecret && $sigHeader) {
            try {
                \Stripe\Webhook::constructEvent(
                    $request->getContent(),
                    $sigHeader,
                    $webhookSecret
                );
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());
                return response('Invalid signature', 400);
            }
        } elseif ($webhookSecret && !$sigHeader) {
            Log::warning('Stripe webhook received without signature header');
            return response('Missing signature', 400);
        }
        // webhookSecretが未設定の場合はローカル開発環境として検証をスキップ

        $payload = $request->all();
        $type = $payload['type'] ?? '';

        Log::info("Stripe webhook: {$type}");

        return match ($type) {
            'customer.subscription.created' => $this->handleSubscriptionCreated($payload),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($payload),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($payload),
            'invoice.payment_failed' => $this->handlePaymentFailed($payload),
            default => response('', 200),
        };
    }

    private function handleSubscriptionCreated(array $payload): Response
    {
        $data = $payload['data']['object'] ?? [];
        $customerId = $data['customer'] ?? '';

        $tenant = Tenant::where('stripe_customer_id', $customerId)->first();
        if ($tenant) {
            $tenant->update([
                'status' => TenantStatus::Active,
                'stripe_subscription_id' => $data['id'] ?? null,
            ]);
        }

        return response('', 200);
    }

    private function handleSubscriptionUpdated(array $payload): Response
    {
        $data = $payload['data']['object'] ?? [];
        $customerId = $data['customer'] ?? '';
        $stripeStatus = $data['status'] ?? '';

        $tenant = Tenant::where('stripe_customer_id', $customerId)->first();
        if ($tenant) {
            $status = match ($stripeStatus) {
                'active', 'trialing' => TenantStatus::Active,
                'past_due' => TenantStatus::Suspended,
                'canceled', 'unpaid' => TenantStatus::Cancelled,
                default => $tenant->status,
            };
            $tenant->update(['status' => $status]);
        }

        return response('', 200);
    }

    private function handleSubscriptionDeleted(array $payload): Response
    {
        $data = $payload['data']['object'] ?? [];
        $customerId = $data['customer'] ?? '';

        $tenant = Tenant::where('stripe_customer_id', $customerId)->first();
        if ($tenant) {
            $tenant->update(['status' => TenantStatus::Cancelled]);
        }

        return response('', 200);
    }

    private function handlePaymentFailed(array $payload): Response
    {
        $data = $payload['data']['object'] ?? [];
        $customerId = $data['customer'] ?? '';

        $tenant = Tenant::where('stripe_customer_id', $customerId)->first();
        if ($tenant) {
            $tenant->update(['status' => TenantStatus::Suspended]);
            Log::warning("Payment failed for tenant {$tenant->id} ({$tenant->name})");
        }

        return response('', 200);
    }
}
