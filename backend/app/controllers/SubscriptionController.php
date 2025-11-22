<?php

namespace Tahmin\Controllers;

use Tahmin\Models\User;

class SubscriptionController extends BaseController
{
    /**
     * Get subscription plans
     */
    public function plansAction()
    {
        $plans = [
            [
                'id' => 'free',
                'name' => 'Ücretsiz',
                'price' => 0,
                'duration' => 'lifetime',
                'features' => [
                    'Günlük 5 tahmin',
                    'Temel maç istatistikleri',
                    'Kupon oluşturma (maksimum 3 maç)',
                ],
                'limits' => [
                    'daily_predictions' => 5,
                    'max_coupon_picks' => 3,
                ],
            ],
            [
                'id' => 'pro',
                'name' => 'Pro',
                'price' => 99.90,
                'duration' => 'monthly',
                'features' => [
                    'Sınırsız tahmin',
                    'Detaylı maç istatistikleri',
                    'AI tahmin analizi',
                    'Özel kupon şablonları',
                    'Kupon geçmişi ve analizler',
                    'Sınırsız kupon oluşturma',
                ],
                'limits' => [
                    'daily_predictions' => -1, // unlimited
                    'max_coupon_picks' => -1,
                ],
            ],
            [
                'id' => 'premium',
                'name' => 'Premium',
                'price' => 249.90,
                'duration' => 'monthly',
                'features' => [
                    'Pro\'daki tüm özellikler',
                    'Yüksek güvenilirlik tahminleri',
                    'Canlı maç bildirimleri',
                    'Excel/PDF raporları',
                    'Özel formül oluşturma',
                    'WhatsApp destek',
                    'API erişimi',
                ],
                'limits' => [
                    'daily_predictions' => -1,
                    'max_coupon_picks' => -1,
                    'api_access' => true,
                ],
            ],
        ];

        return $this->sendSuccess(['plans' => $plans]);
    }

    /**
     * Get current subscription
     */
    public function currentAction()
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $subscription = [
            'tier' => $this->currentUser->membership_tier,
            'expires_at' => $this->currentUser->membership_expires_at ?
                $this->currentUser->membership_expires_at->format('c') : null,
            'is_active' => $this->currentUser->isPremiumUser(),
            'days_remaining' => $this->getDaysRemaining(),
        ];

        return $this->sendSuccess(['subscription' => $subscription]);
    }

    /**
     * Create subscription (payment intent)
     */
    public function createAction()
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $data = $this->request->getJsonRawBody(true);
        $planId = $data['plan_id'] ?? null;

        if (!in_array($planId, ['pro', 'premium'])) {
            return $this->sendError('Invalid plan', 400);
        }

        // Get plan details
        $plans = [
            'pro' => ['price' => 99.90, 'name' => 'Pro'],
            'premium' => ['price' => 249.90, 'name' => 'Premium'],
        ];

        $plan = $plans[$planId];

        // TODO: Create payment with payment gateway (Iyzico, Stripe, etc.)
        // For now, we'll return a mock payment intent

        $paymentIntent = [
            'id' => 'pi_' . bin2hex(random_bytes(16)),
            'amount' => $plan['price'],
            'currency' => 'TRY',
            'plan_id' => $planId,
            'plan_name' => $plan['name'],
            'status' => 'pending',
            'payment_url' => 'https://tahmin1x2.com/payment/' . bin2hex(random_bytes(8)),
            'created_at' => date('c'),
        ];

        return $this->sendSuccess([
            'payment_intent' => $paymentIntent
        ], 'Payment intent created', 201);
    }

    /**
     * Activate subscription (webhook from payment gateway)
     */
    public function activateAction()
    {
        // This would be called by payment gateway webhook
        $data = $this->request->getJsonRawBody(true);

        $userId = $data['user_id'] ?? null;
        $planId = $data['plan_id'] ?? null;
        $paymentId = $data['payment_id'] ?? null;

        if (!$userId || !$planId) {
            return $this->sendError('Invalid request', 400);
        }

        $user = User::findFirst($userId);
        if (!$user) {
            return $this->sendError('User not found', 404);
        }

        // Activate subscription
        $user->membership_tier = $planId;
        $user->membership_expires_at = new \DateTime('+30 days');

        if (!$user->save()) {
            return $this->sendError('Failed to activate subscription', 400);
        }

        // TODO: Create subscription record in database
        // TODO: Send confirmation email

        return $this->sendSuccess([
            'subscription' => [
                'tier' => $user->membership_tier,
                'expires_at' => $user->membership_expires_at->format('c'),
            ]
        ], 'Subscription activated');
    }

    /**
     * Cancel subscription
     */
    public function cancelAction()
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        // Don't immediately downgrade, let it expire
        // Just mark for cancellation

        return $this->sendSuccess([
            'message' => 'Subscription will not be renewed',
            'expires_at' => $this->currentUser->membership_expires_at ?
                $this->currentUser->membership_expires_at->format('c') : null
        ]);
    }

    private function getDaysRemaining(): ?int
    {
        if (!$this->currentUser->membership_expires_at) {
            return null;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->currentUser->membership_expires_at);

        return $diff->days * ($diff->invert ? -1 : 1);
    }
}
