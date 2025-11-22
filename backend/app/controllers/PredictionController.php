<?php

namespace Tahmin\Controllers;

use Tahmin\Models\Prediction\Prediction;
use Phalcon\Mvc\Model\Query\Builder;

class PredictionController extends BaseController
{
    /**
     * Get all predictions
     */
    public function indexAction()
    {
        $page = (int)$this->request->getQuery('page', 'int', 1);
        $limit = (int)$this->request->getQuery('limit', 'int', 20);
        $matchId = $this->request->getQuery('match_id', 'int');
        $type = $this->request->getQuery('type');
        $minConfidence = $this->request->getQuery('min_confidence', 'float');

        $builder = (new Builder())
            ->from(Prediction::class)
            ->orderBy('created_at DESC');

        if ($matchId) {
            $builder->andWhere('match_id = :match_id:', ['match_id' => $matchId]);
        }

        if ($type) {
            $builder->andWhere('prediction_type = :type:', ['type' => $type]);
        }

        if ($minConfidence) {
            $builder->andWhere('confidence_score >= :min_confidence:', ['min_confidence' => $minConfidence]);
        }

        // Premium filter - only show premium predictions to premium users
        if (!$this->currentUser || !$this->currentUser->isPremiumUser()) {
            $builder->andWhere('is_premium = false');
        }

        $result = $this->paginate($builder, $page, $limit);

        // Load match relationship
        foreach ($result['data'] as &$prediction) {
            $predObj = Prediction::findFirst($prediction['id']);
            $match = $predObj->getMatch();
            $prediction['match'] = [
                'id' => $match->id,
                'home_team' => $match->getHomeTeam()->name,
                'away_team' => $match->getAwayTeam()->name,
                'match_date' => $match->match_date->format('c'),
                'status' => $match->status,
            ];
        }

        return $this->sendSuccess($result);
    }

    /**
     * Get single prediction
     */
    public function showAction(int $id)
    {
        $prediction = Prediction::findFirst($id);

        if (!$prediction) {
            return $this->sendError('Prediction not found', 404);
        }

        // Check if premium prediction and user has access
        if ($prediction->is_premium && (!$this->currentUser || !$this->currentUser->isPremiumUser())) {
            return $this->sendError('Premium subscription required', 403);
        }

        $data = $prediction->toArray();
        $match = $prediction->getMatch();
        $data['match'] = $match->toArray();
        $data['match']['home_team'] = $match->getHomeTeam()->toArray();
        $data['match']['away_team'] = $match->getAwayTeam()->toArray();

        return $this->sendSuccess(['prediction' => $data]);
    }

    /**
     * Get featured predictions
     */
    public function featuredAction()
    {
        $limit = (int)$this->request->getQuery('limit', 'int', 10);

        $conditions = 'is_featured = true AND status = :status:';
        $bind = ['status' => Prediction::STATUS_PENDING];

        // Filter premium for non-premium users
        if (!$this->currentUser || !$this->currentUser->isPremiumUser()) {
            $conditions .= ' AND is_premium = false';
        }

        $predictions = Prediction::find([
            'conditions' => $conditions,
            'bind' => $bind,
            'order' => 'confidence_score DESC',
            'limit' => $limit
        ]);

        $data = [];
        foreach ($predictions as $prediction) {
            $predData = $prediction->toArray();
            $match = $prediction->getMatch();
            $predData['match'] = [
                'id' => $match->id,
                'home_team' => $match->getHomeTeam()->name,
                'away_team' => $match->getAwayTeam()->name,
                'match_date' => $match->match_date->format('c'),
            ];
            $data[] = $predData;
        }

        return $this->sendSuccess(['predictions' => $data]);
    }

    /**
     * Get high confidence predictions
     */
    public function highConfidenceAction()
    {
        $limit = (int)$this->request->getQuery('limit', 'int', 20);
        $minConfidence = (float)$this->request->getQuery('min_confidence', 'float', 75);

        $conditions = 'confidence_score >= :min_confidence: AND status = :status:';
        $bind = [
            'min_confidence' => $minConfidence,
            'status' => Prediction::STATUS_PENDING
        ];

        if (!$this->currentUser || !$this->currentUser->isPremiumUser()) {
            $conditions .= ' AND is_premium = false';
        }

        $predictions = Prediction::find([
            'conditions' => $conditions,
            'bind' => $bind,
            'order' => 'confidence_score DESC',
            'limit' => $limit
        ]);

        $data = [];
        foreach ($predictions as $prediction) {
            $predData = $prediction->toArray();
            $match = $prediction->getMatch();
            $predData['match'] = [
                'home_team' => $match->getHomeTeam()->name,
                'away_team' => $match->getAwayTeam()->name,
                'match_date' => $match->match_date->format('c'),
            ];
            $data[] = $predData;
        }

        return $this->sendSuccess(['predictions' => $data]);
    }
}

    /**
     * Predictions list page
     */
    public function listPageAction()
    {
        $page = $this->request->getQuery('page', 'int', 1);
        $filter = $this->request->getQuery('filter', 'string', 'all');

        $conditions = [];
        $bind = [];

        switch ($filter) {
            case 'today':
                $conditions[] = 'DATE(created_at) = CURDATE()';
                break;
            case 'high':
                $conditions[] = 'confidence_level >= 80';
                break;
            case 'my':
                if ($this->session->has('auth')) {
                    $conditions[] = 'user_id = :userId:';
                    $bind['userId'] = $this->session->get('auth')['id'];
                }
                break;
        }

        $queryParams = [
            'order' => 'created_at DESC',
            'limit' => 20,
            'offset' => ($page - 1) * 20
        ];

        if (!empty($conditions)) {
            $queryParams['conditions'] = implode(' AND ', $conditions);
            $queryParams['bind'] = $bind;
        }

        $this->view->predictions = \Tahmin\Models\Prediction\Prediction::find($queryParams);
        $this->view->page = (object)[
            'current' => $page,
            'total_pages' => ceil(\Tahmin\Models\Prediction\Prediction::count() / 20)
        ];
        $this->view->setMainView('layouts/main');
    }

    /**
     * Prediction detail page
     */
    public function viewPageAction($id)
    {
        $prediction = \Tahmin\Models\Prediction\Prediction::findFirst($id);
        if (!$prediction) {
            $this->flashSession->error('Tahmin bulunamadı');
            return $this->response->redirect('/predictions');
        }

        $this->view->prediction = $prediction;
        $this->view->setMainView('layouts/main');
    }
