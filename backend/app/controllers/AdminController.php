<?php

namespace Tahmin\Controllers;

use Tahmin\Models\User;
use Tahmin\Models\Match\Match;
use Tahmin\Models\Match\League;
use Tahmin\Models\Match\Team;
use Tahmin\Models\Prediction\Prediction;
use Tahmin\Models\Coupon\Coupon;

class AdminController extends BaseController
{
    public function initialize()
    {
        parent::initialize();

        // Check admin permission
        if (!$this->currentUser || !$this->currentUser->hasPermission('view_dashboard')) {
            $this->response->setStatusCode(403);
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'Admin access required'
            ]);
            $this->response->send();
            exit;
        }
    }

    /**
     * Dashboard statistics
     */
    public function dashboardAction()
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'active' => User::count(['is_active = true']),
                'premium' => User::count(['membership_tier IN (:tiers:)', 'bind' => ['tiers' => ['pro', 'premium']]]),
                'new_today' => User::count(['created_at >= :today:', 'bind' => ['today' => date('Y-m-d 00:00:00')]]),
            ],
            'matches' => [
                'total' => Match::count(),
                'upcoming' => Match::count(['status = :status: AND match_date > :now:', 'bind' => [
                    'status' => Match::STATUS_SCHEDULED,
                    'now' => date('Y-m-d H:i:s')
                ]]),
                'live' => Match::count(['status IN (:live:, :halftime:)', 'bind' => [
                    'live' => Match::STATUS_LIVE,
                    'halftime' => Match::STATUS_HALFTIME
                ]]),
                'finished_today' => Match::count(['status = :status: AND match_date >= :today:', 'bind' => [
                    'status' => Match::STATUS_FINISHED,
                    'today' => date('Y-m-d 00:00:00')
                ]]),
            ],
            'predictions' => [
                'total' => Prediction::count(),
                'pending' => Prediction::count(['status = :status:', 'bind' => ['status' => Prediction::STATUS_PENDING]]),
                'won' => Prediction::count(['status = :status:', 'bind' => ['status' => Prediction::STATUS_WON]]),
                'lost' => Prediction::count(['status = :status:', 'bind' => ['status' => Prediction::STATUS_LOST]]),
                'accuracy' => $this->calculatePredictionAccuracy(),
            ],
            'coupons' => [
                'total' => Coupon::count(),
                'pending' => Coupon::count(['status = :status:', 'bind' => ['status' => Coupon::STATUS_PENDING]]),
                'won' => Coupon::count(['status = :status:', 'bind' => ['status' => Coupon::STATUS_WON]]),
                'total_stake' => $this->getTotalStake(),
                'total_profit' => $this->getTotalProfit(),
            ],
            'leagues' => League::count(['is_active = true']),
            'teams' => Team::count(),
        ];

        return $this->sendSuccess(['stats' => $stats]);
    }

    /**
     * User management
     */
    public function usersAction()
    {
        $page = (int)$this->request->getQuery('page', 'int', 1);
        $limit = (int)$this->request->getQuery('limit', 'int', 20);
        $role = $this->request->getQuery('role');
        $membership = $this->request->getQuery('membership');
        $search = $this->request->getQuery('search');

        $builder = (new \Phalcon\Mvc\Model\Query\Builder())
            ->from(User::class)
            ->orderBy('created_at DESC');

        if ($role) {
            $builder->andWhere('role = :role:', ['role' => $role]);
        }

        if ($membership) {
            $builder->andWhere('membership_tier = :tier:', ['tier' => $membership]);
        }

        if ($search) {
            $builder->andWhere('email LIKE :search: OR full_name LIKE :search:', [
                'search' => "%{$search}%"
            ]);
        }

        $result = $this->paginate($builder, $page, $limit);

        return $this->sendSuccess($result);
    }

    /**
     * Update user
     */
    public function updateUserAction(int $id)
    {
        if (!$this->currentUser->hasPermission('manage_users')) {
            return $this->sendError('Permission denied', 403);
        }

        $user = User::findFirst($id);
        if (!$user) {
            return $this->sendError('User not found', 404);
        }

        $data = $this->request->getJsonRawBody(true);

        if (isset($data['role'])) {
            $user->role = $data['role'];
        }
        if (isset($data['membership_tier'])) {
            $user->membership_tier = $data['membership_tier'];
        }
        if (isset($data['membership_expires_at'])) {
            $user->membership_expires_at = new \DateTime($data['membership_expires_at']);
        }
        if (isset($data['is_active'])) {
            $user->is_active = $data['is_active'];
        }
        if (isset($data['is_verified'])) {
            $user->is_verified = $data['is_verified'];
        }

        if (!$user->save()) {
            return $this->sendError('Failed to update user', 400);
        }

        return $this->sendSuccess(['user' => $user->toArray()], 'User updated successfully');
    }

    /**
     * Match management
     */
    public function matchesAction()
    {
        $page = (int)$this->request->getQuery('page', 'int', 1);
        $limit = (int)$this->request->getQuery('limit', 'int', 50);
        $status = $this->request->getQuery('status');
        $leagueId = $this->request->getQuery('league_id', 'int');

        $builder = (new \Phalcon\Mvc\Model\Query\Builder())
            ->from(Match::class)
            ->orderBy('match_date DESC');

        if ($status) {
            $builder->andWhere('status = :status:', ['status' => $status]);
        }

        if ($leagueId) {
            $builder->andWhere('league_id = :league_id:', ['league_id' => $leagueId]);
        }

        $result = $this->paginate($builder, $page, $limit);

        return $this->sendSuccess($result);
    }

    /**
     * Update match
     */
    public function updateMatchAction(int $id)
    {
        if (!$this->currentUser->hasPermission('manage_matches')) {
            return $this->sendError('Permission denied', 403);
        }

        $match = Match::findFirst($id);
        if (!$match) {
            return $this->sendError('Match not found', 404);
        }

        $data = $this->request->getJsonRawBody(true);

        if (isset($data['status'])) {
            $match->status = $data['status'];
        }
        if (isset($data['home_score'])) {
            $match->home_score = $data['home_score'];
        }
        if (isset($data['away_score'])) {
            $match->away_score = $data['away_score'];
        }
        if (isset($data['is_featured'])) {
            $match->is_featured = $data['is_featured'];
        }

        if (!$match->save()) {
            return $this->sendError('Failed to update match', 400);
        }

        // Check predictions if match finished
        if ($match->status === Match::STATUS_FINISHED) {
            $this->checkMatchPredictions($match);
        }

        return $this->sendSuccess(['match' => $match->toArray()], 'Match updated successfully');
    }

    /**
     * Prediction management
     */
    public function predictionsAction()
    {
        $page = (int)$this->request->getQuery('page', 'int', 1);
        $limit = (int)$this->request->getQuery('limit', 'int', 50);
        $status = $this->request->getQuery('status');
        $isPremium = $this->request->getQuery('is_premium');

        $builder = (new \Phalcon\Mvc\Model\Query\Builder())
            ->from(Prediction::class)
            ->orderBy('created_at DESC');

        if ($status) {
            $builder->andWhere('status = :status:', ['status' => $status]);
        }

        if ($isPremium !== null) {
            $builder->andWhere('is_premium = :premium:', ['premium' => (bool)$isPremium]);
        }

        $result = $this->paginate($builder, $page, $limit);

        return $this->sendSuccess($result);
    }

    /**
     * Create prediction
     */
    public function createPredictionAction()
    {
        if (!$this->currentUser->hasPermission('manage_predictions')) {
            return $this->sendError('Permission denied', 403);
        }

        $data = $this->request->getJsonRawBody(true);

        $prediction = new Prediction();
        $prediction->match_id = $data['match_id'];
        $prediction->prediction_type = $data['prediction_type'];
        $prediction->predicted_result = $data['predicted_result'];
        $prediction->confidence_score = $data['confidence_score'];
        $prediction->model_version = $data['model_version'] ?? 'v1.0';
        $prediction->features_used = $data['features_used'] ?? [];
        $prediction->recommended_odds = $data['recommended_odds'] ?? null;
        $prediction->is_premium = $data['is_premium'] ?? false;
        $prediction->is_featured = $data['is_featured'] ?? false;

        if (!$prediction->save()) {
            $messages = [];
            foreach ($prediction->getMessages() as $message) {
                $messages[] = $message->getMessage();
            }
            return $this->sendError('Failed to create prediction', 400, $messages);
        }

        return $this->sendSuccess(['prediction' => $prediction->toArray()], 'Prediction created', 201);
    }

    /**
     * Trigger data collector
     */
    public function collectDataAction()
    {
        if (!$this->currentUser->hasPermission('trigger_collector')) {
            return $this->sendError('Permission denied', 403);
        }

        $type = $this->request->getPost('type', 'string', 'matches');

        // TODO: Implement actual data collection
        // This would call API-Football or Football-Data.org

        return $this->sendSuccess([
            'message' => 'Data collection started',
            'type' => $type
        ]);
    }

    /**
     * Analytics
     */
    public function analyticsAction()
    {
        $period = $this->request->getQuery('period', 'string', 'week'); // day, week, month, year

        $startDate = $this->getStartDate($period);

        $analytics = [
            'user_growth' => $this->getUserGrowth($startDate),
            'prediction_performance' => $this->getPredictionPerformance($startDate),
            'revenue' => $this->getRevenue($startDate),
            'popular_leagues' => $this->getPopularLeagues($startDate),
            'top_predictions' => $this->getTopPredictions($startDate),
        ];

        return $this->sendSuccess(['analytics' => $analytics]);
    }

    // Helper methods
    private function calculatePredictionAccuracy(): float
    {
        $total = Prediction::count(['status IN (:won:, :lost:)', 'bind' => [
            'won' => Prediction::STATUS_WON,
            'lost' => Prediction::STATUS_LOST
        ]]);

        if ($total == 0) return 0;

        $won = Prediction::count(['status = :status:', 'bind' => ['status' => Prediction::STATUS_WON]]);

        return round(($won / $total) * 100, 2);
    }

    private function getTotalStake(): float
    {
        $result = $this->db->query('SELECT SUM(stake) as total FROM coupons');
        $row = $result->fetch();
        return (float)($row['total'] ?? 0);
    }

    private function getTotalProfit(): float
    {
        $result = $this->db->query('SELECT SUM(profit_loss) as total FROM coupons WHERE status = :status', [
            'status' => Coupon::STATUS_WON
        ]);
        $row = $result->fetch();
        return (float)($row['total'] ?? 0);
    }

    private function checkMatchPredictions(Match $match): void
    {
        $predictions = $match->getPredictions();
        foreach ($predictions as $prediction) {
            $prediction->checkResult();
        }
    }

    private function getStartDate(string $period): string
    {
        switch ($period) {
            case 'day':
                return date('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'year':
                return date('Y-m-d 00:00:00', strtotime('-365 days'));
            default:
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
        }
    }

    private function getUserGrowth(string $startDate): array
    {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count
                FROM users
                WHERE created_at >= :start
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        $result = $this->db->query($sql, ['start' => $startDate]);
        return $result->fetchAll();
    }

    private function getPredictionPerformance(string $startDate): array
    {
        $sql = "SELECT
                    prediction_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost,
                    AVG(confidence_score) as avg_confidence
                FROM predictions
                WHERE created_at >= :start
                GROUP BY prediction_type";

        $result = $this->db->query($sql, ['start' => $startDate]);
        return $result->fetchAll();
    }

    private function getRevenue(string $startDate): array
    {
        $sql = "SELECT
                    DATE(created_at) as date,
                    SUM(stake) as total_stake,
                    SUM(CASE WHEN status = 'won' THEN actual_win ELSE 0 END) as total_payout,
                    SUM(profit_loss) as net_profit
                FROM coupons
                WHERE created_at >= :start
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        $result = $this->db->query($sql, ['start' => $startDate]);
        return $result->fetchAll();
    }

    private function getPopularLeagues(string $startDate): array
    {
        $sql = "SELECT
                    l.name,
                    l.country,
                    COUNT(DISTINCT m.id) as match_count,
                    COUNT(DISTINCT p.id) as prediction_count
                FROM leagues l
                LEFT JOIN matches m ON l.id = m.league_id AND m.match_date >= :start
                LEFT JOIN predictions p ON m.id = p.match_id
                WHERE l.is_active = true
                GROUP BY l.id, l.name, l.country
                ORDER BY prediction_count DESC
                LIMIT 10";

        $result = $this->db->query($sql, ['start' => $startDate]);
        return $result->fetchAll();
    }

    private function getTopPredictions(string $startDate): array
    {
        $sql = "SELECT
                    p.*,
                    m.home_team_id,
                    m.away_team_id
                FROM predictions p
                JOIN matches m ON p.match_id = m.id
                WHERE p.created_at >= :start
                AND p.status = 'won'
                ORDER BY p.confidence_score DESC
                LIMIT 20";

        $result = $this->db->query($sql, ['start' => $startDate]);
        return $result->fetchAll();
    }
}
