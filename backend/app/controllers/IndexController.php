<?php

namespace Tahmin\Controllers;

class IndexController extends BaseController
{
    /**
     * Index action
     */
    public function indexAction()
    {
        return $this->sendSuccess([
            'name' => 'Tahmin1x2 API',
            'version' => '1.0.0',
            'framework' => 'Phalcon',
            'description' => 'AI-powered Football Prediction Platform'
        ]);
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
