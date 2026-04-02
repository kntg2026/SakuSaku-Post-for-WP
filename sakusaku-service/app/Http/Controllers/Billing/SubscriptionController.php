<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function status(): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        $subscription = $tenant->subscriptions()->where('type', 'default')->latest()->first();

        return response()->json([
            'tenant_status' => $tenant->status->value,
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'trial_days_remaining' => $tenant->trial_ends_at?->diffInDays(now(), false),
            'has_subscription' => !!$subscription,
            'subscription' => $subscription ? [
                'stripe_status' => $subscription->stripe_status,
                'stripe_price' => $subscription->stripe_price,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();

        // In production, this would create a Stripe Checkout Session.
        // For now, return the expected structure.
        // Requires STRIPE_KEY, STRIPE_SECRET, and a Stripe Price ID to be configured.

        $priceId = config('sakusaku.stripe_price_id');

        if (!$priceId || !config('services.stripe.secret')) {
            return response()->json([
                'error' => 'Stripe not configured',
                'message' => 'Set STRIPE_KEY, STRIPE_SECRET, and SAKUSAKU_STRIPE_PRICE_ID in .env',
            ], 503);
        }

        // Create Stripe Checkout Session via Cashier
        // $checkout = $tenant->newSubscription('default', $priceId)
        //     ->trialDays($tenant->isOnTrial() ? $tenant->trial_ends_at->diffInDays(now()) : 0)
        //     ->checkout([
        //         'success_url' => config('app.url') . '/admin/billing?success=1',
        //         'cancel_url' => config('app.url') . '/admin/billing?cancelled=1',
        //     ]);
        // return response()->json(['checkout_url' => $checkout->url]);

        return response()->json([
            'message' => 'Stripe checkout would be initiated here',
            'price_id' => $priceId,
        ]);
    }

    public function portal(): JsonResponse
    {
        $tenant = $this->tenantContext->get();

        if (!$tenant->stripe_customer_id) {
            return response()->json(['error' => 'No Stripe customer'], 404);
        }

        // In production:
        // $url = $tenant->billingPortalUrl(config('app.url') . '/admin/billing');
        // return response()->json(['portal_url' => $url]);

        return response()->json([
            'message' => 'Stripe portal would open here',
        ]);
    }
}
