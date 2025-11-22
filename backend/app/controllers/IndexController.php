<?php

namespace Tahmin\Controllers;

use Tahmin\Models\Match\Match;
use Tahmin\Models\Prediction\Prediction;

class IndexController extends BaseController
{
    /**
     * Index action - Homepage
     */
    public function indexAction()
    {
        // Get today's matches
        $todayMatches = Match::find([
            'conditions' => 'match_date >= :today: AND match_date < :tomorrow:',
            'bind' => [
                'today' => date('Y-m-d 00:00:00'),
                'tomorrow' => date('Y-m-d 23:59:59')
            ],
            'limit' => 6
        ]);

        // Get top predictions
        $topPredictions = Prediction::find([
            'conditions' => 'confidence_level >= 80',
            'order' => 'confidence_level DESC, created_at DESC',
            'limit' => 6
        ]);

        $this->view->todayMatches = $todayMatches;
        $this->view->topPredictions = $topPredictions;
        $this->view->setMainView('layouts/main');
    }

    /**
     * Health check
     */
    public function healthAction()
    {
        try {
            // Check database connection
            $db = $this->getDI()->get('db');
            $db->query('SELECT 1');
            $dbStatus = 'healthy';
        } catch (\Exception $e) {
            $dbStatus = 'unhealthy';
        }

        try {
            // Check Redis connection
            $redis = $this->getDI()->get('redis');
            $redis->ping();
            $redisStatus = 'healthy';
        } catch (\Exception $e) {
            $redisStatus = 'unhealthy';
        }

        $status = ($dbStatus === 'healthy' && $redisStatus === 'healthy') ? 200 : 503;

        $this->response->setStatusCode($status);
        return $this->sendSuccess([
            'status' => $status === 200 ? 'healthy' : 'unhealthy',
            'database' => $dbStatus,
            'redis' => $redisStatus,
            'timestamp' => date('c')
        ]);
    }

    /**
     * Handle OPTIONS requests
     */
    public function optionsAction()
    {
        $this->response->setStatusCode(204);
        return $this->response->send();
    }

    /**
     * 404 Not Found
     */
    public function notFoundAction()
    {
        return $this->sendError('Endpoint not found', 404);
    }
}
