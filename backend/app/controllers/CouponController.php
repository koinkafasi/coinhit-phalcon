<?php

namespace Tahmin\Controllers;

use Tahmin\Models\Coupon\Coupon;
use Tahmin\Models\Coupon\CouponPick;
use Tahmin\Models\Prediction\Prediction;
use Phalcon\Mvc\Model\Query\Builder;

class CouponController extends BaseController
{
    /**
     * Get user's coupons
     */
    public function indexAction()
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $page = (int)$this->request->getQuery('page', 'int', 1);
        $limit = (int)$this->request->getQuery('limit', 'int', 20);

        $builder = (new Builder())
            ->from(Coupon::class)
            ->where('user_id = :user_id:', ['user_id' => $this->currentUser->id])
            ->orderBy('created_at DESC');

        $result = $this->paginate($builder, $page, $limit);

        // Load picks for each coupon
        foreach ($result['data'] as &$coupon) {
            $couponObj = Coupon::findFirst($coupon['id']);
            $coupon['picks'] = array_map(function($pick) {
                return $pick->toArray();
            }, iterator_to_array($couponObj->getPicks()));
        }

        return $this->sendSuccess($result);
    }

    /**
     * Get single coupon
     */
    public function showAction(string $id)
    {
        $coupon = Coupon::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $id]
        ]);

        if (!$coupon) {
            return $this->sendError('Coupon not found', 404);
        }

        // Check ownership or if shared
        if (!$coupon->is_shared && (!$this->currentUser || $coupon->user_id !== $this->currentUser->id)) {
            return $this->sendError('Access denied', 403);
        }

        $data = $coupon->toArray();

        // Load picks with predictions and matches
        $picks = [];
        foreach ($coupon->getPicks() as $pick) {
            $pickData = $pick->toArray();
            $prediction = $pick->getPrediction();
            $pickData['prediction'] = $prediction->toArray();
            $match = $prediction->getMatch();
            $pickData['match'] = [
                'home_team' => $match->getHomeTeam()->name,
                'away_team' => $match->getAwayTeam()->name,
                'match_date' => $match->match_date->format('c'),
                'status' => $match->status,
            ];
            $picks[] = $pickData;
        }
        $data['picks'] = $picks;

        return $this->sendSuccess(['coupon' => $data]);
    }

    /**
     * Create new coupon
     */
    public function createAction()
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $data = $this->request->getJsonRawBody(true);

        // Validate required fields
        if (empty($data['coupon_type']) || empty($data['picks'])) {
            return $this->sendError('Coupon type and picks are required', 400);
        }

        if (!is_array($data['picks']) || count($data['picks']) === 0) {
            return $this->sendError('At least one pick is required', 400);
        }

        // Create coupon
        $coupon = new Coupon();
        $coupon->user_id = $this->currentUser->id;
        $coupon->name = $data['name'] ?? null;
        $coupon->coupon_type = $data['coupon_type'];
        $coupon->stake = $data['stake'] ?? 0;
        $coupon->system_min_wins = $data['system_min_wins'] ?? null;
        $coupon->system_total_picks = $data['system_total_picks'] ?? null;

        if (!$coupon->save()) {
            return $this->sendError('Failed to create coupon', 400);
        }

        // Add picks
        foreach ($data['picks'] as $pickData) {
            if (empty($pickData['prediction_id']) || empty($pickData['odds'])) {
                continue;
            }

            $prediction = Prediction::findFirst($pickData['prediction_id']);
            if (!$prediction) {
                continue;
            }

            // Check premium access
            if ($prediction->is_premium && !$this->currentUser->isPremiumUser()) {
                continue;
            }

            $pick = new CouponPick();
            $pick->coupon_id = $coupon->id;
            $pick->prediction_id = $prediction->id;
            $pick->odds = $pickData['odds'];
            $pick->is_banker = $pickData['is_banker'] ?? false;
            $pick->save();
        }

        // Calculate total odds
        $coupon->calculateTotalOdds();

        return $this->sendSuccess([
            'coupon' => $coupon->toArray()
        ], 'Coupon created successfully', 201);
    }

    /**
     * Update coupon
     */
    public function updateAction(string $id)
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $coupon = Coupon::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $id]
        ]);

        if (!$coupon) {
            return $this->sendError('Coupon not found', 404);
        }

        if ($coupon->user_id !== $this->currentUser->id) {
            return $this->sendError('Access denied', 403);
        }

        if ($coupon->status !== Coupon::STATUS_PENDING) {
            return $this->sendError('Cannot update completed coupon', 400);
        }

        $data = $this->request->getJsonRawBody(true);

        if (isset($data['name'])) {
            $coupon->name = $data['name'];
        }
        if (isset($data['stake'])) {
            $coupon->stake = $data['stake'];
            $coupon->calculateTotalOdds();
        }

        $coupon->save();

        return $this->sendSuccess(['coupon' => $coupon->toArray()], 'Coupon updated successfully');
    }

    /**
     * Delete coupon
     */
    public function deleteAction(string $id)
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $coupon = Coupon::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $id]
        ]);

        if (!$coupon) {
            return $this->sendError('Coupon not found', 404);
        }

        if ($coupon->user_id !== $this->currentUser->id) {
            return $this->sendError('Access denied', 403);
        }

        if ($coupon->status !== Coupon::STATUS_PENDING) {
            return $this->sendError('Cannot delete completed coupon', 400);
        }

        $coupon->delete();

        return $this->sendSuccess(null, 'Coupon deleted successfully');
    }

    /**
     * Share coupon
     */
    public function shareAction(string $id)
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $coupon = Coupon::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $id]
        ]);

        if (!$coupon) {
            return $this->sendError('Coupon not found', 404);
        }

        if ($coupon->user_id !== $this->currentUser->id) {
            return $this->sendError('Access denied', 403);
        }

        $coupon->is_shared = true;
        $coupon->share_code = substr(md5($coupon->id . time()), 0, 10);
        $coupon->save();

        return $this->sendSuccess([
            'share_code' => $coupon->share_code,
            'share_url' => "https://tahmin1x2.com/coupon/shared/{$coupon->share_code}"
        ], 'Coupon shared successfully');
    }
}
