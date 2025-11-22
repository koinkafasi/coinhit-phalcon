<?php

namespace Tahmin\Controllers;

use Tahmin\Models\User;
use Tahmin\Models\UserActivity;
use Tahmin\Models\Coupon\Coupon;

class UserController extends BaseController
{
    /**
     * User dashboard
     */
    public function dashboardAction()
    {
        if (!$this->session->has('auth')) {
            $this->flashSession->error('Lütfen giriş yapın');
            return $this->response->redirect('/auth/login');
        }

        $userId = $this->session->get('auth')['id'];
        $user = User::findFirst($userId);

        if (!$user) {
            $this->session->remove('auth');
            return $this->response->redirect('/auth/login');
        }

        // Get user statistics
        $totalCoupons = Coupon::count(['user_id = ?0', 'bind' => [$userId]]);
        $wonCoupons = Coupon::count([
            'conditions' => 'user_id = ?0 AND status = ?1',
            'bind' => [$userId, 'won']
        ]);
        $lostCoupons = Coupon::count([
            'conditions' => 'user_id = ?0 AND status = ?1',
            'bind' => [$userId, 'lost']
        ]);

        $winRate = $totalCoupons > 0 ? round(($wonCoupons / $totalCoupons) * 100, 2) : 0;

        $stats = (object)[
            'total_coupons' => $totalCoupons,
            'won_coupons' => $wonCoupons,
            'lost_coupons' => $lostCoupons,
            'win_rate' => $winRate
        ];

        // Get recent activities
        $activities = UserActivity::find([
            'conditions' => 'user_id = ?0',
            'bind' => [$userId],
            'order' => 'created_at DESC',
            'limit' => 10
        ]);

        // Get recent coupons
        $recentCoupons = Coupon::find([
            'conditions' => 'user_id = ?0',
            'bind' => [$userId],
            'order' => 'created_at DESC',
            'limit' => 5
        ]);

        $this->view->user = $user;
        $this->view->stats = $stats;
        $this->view->activities = $activities;
        $this->view->recentCoupons = $recentCoupons;
        $this->view->setMainView('layouts/main');
    }

    /**
     * User profile
     */
    public function profileAction()
    {
        if (!$this->session->has('auth')) {
            $this->flashSession->error('Lütfen giriş yapın');
            return $this->response->redirect('/auth/login');
        }

        $userId = $this->session->get('auth')['id'];
        $user = User::findFirst($userId);

        $this->view->user = $user;
        $this->view->setMainView('layouts/main');
    }
}
