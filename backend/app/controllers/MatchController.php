<?php

namespace Tahmin\Controllers;

use Tahmin\Models\Match\Match;
use Tahmin\Models\Match\League;
use Tahmin\Models\Match\Team;
use Phalcon\Mvc\Model\Query\Builder;

class MatchController extends BaseController
{
    /**
     * Get all matches with filters
     */
    public function indexAction()
    {
        $page = (int)$this->request->getQuery('page', 'int', 1);
        $limit = (int)$this->request->getQuery('limit', 'int', 20);
        $status = $this->request->getQuery('status');
        $leagueId = $this->request->getQuery('league_id', 'int');
        $featured = $this->request->getQuery('featured');

        $builder = (new Builder())
            ->from(Match::class)
            ->orderBy('match_date DESC');

        if ($status) {
            $builder->andWhere('status = :status:', ['status' => $status]);
        }

        if ($leagueId) {
            $builder->andWhere('league_id = :league_id:', ['league_id' => $leagueId]);
        }

        if ($featured !== null) {
            $builder->andWhere('is_featured = :featured:', ['featured' => (bool)$featured]);
        }

        $result = $this->paginate($builder, $page, $limit);

        // Load relationships
        foreach ($result['data'] as &$match) {
            $matchObj = Match::findFirst($match['id']);
            $match['home_team'] = $matchObj->getHomeTeam()->toArray();
            $match['away_team'] = $matchObj->getAwayTeam()->toArray();
            if ($matchObj->league_id) {
                $match['league'] = $matchObj->getLeague()->toArray();
            }
        }

        return $this->sendSuccess($result);
    }

    /**
     * Get single match
     */
    public function showAction(int $id)
    {
        $match = Match::findFirst($id);

        if (!$match) {
            return $this->sendError('Match not found', 404);
        }

        $data = $match->toArray();
        $data['home_team'] = $match->getHomeTeam()->toArray();
        $data['away_team'] = $match->getAwayTeam()->toArray();
        if ($match->league_id) {
            $data['league'] = $match->getLeague()->toArray();
        }
        $data['predictions'] = array_map(function($p) {
            return $p->toArray();
        }, iterator_to_array($match->getPredictions()));

        return $this->sendSuccess(['match' => $data]);
    }

    /**
     * Get upcoming matches
     */
    public function upcomingAction()
    {
        $limit = (int)$this->request->getQuery('limit', 'int', 20);

        $matches = Match::find([
            'conditions' => 'status = :status: AND match_date > :now:',
            'bind' => [
                'status' => Match::STATUS_SCHEDULED,
                'now' => date('Y-m-d H:i:s')
            ],
            'order' => 'match_date ASC',
            'limit' => $limit
        ]);

        $data = [];
        foreach ($matches as $match) {
            $matchData = $match->toArray();
            $matchData['home_team'] = $match->getHomeTeam()->toArray();
            $matchData['away_team'] = $match->getAwayTeam()->toArray();
            $data[] = $matchData;
        }

        return $this->sendSuccess(['matches' => $data]);
    }

    /**
     * Get live matches
     */
    public function liveAction()
    {
        $matches = Match::find([
            'conditions' => 'status IN (:live:, :halftime:)',
            'bind' => [
                'live' => Match::STATUS_LIVE,
                'halftime' => Match::STATUS_HALFTIME
            ],
            'order' => 'match_date ASC'
        ]);

        $data = [];
        foreach ($matches as $match) {
            $matchData = $match->toArray();
            $matchData['home_team'] = $match->getHomeTeam()->toArray();
            $matchData['away_team'] = $match->getAwayTeam()->toArray();
            $data[] = $matchData;
        }

        return $this->sendSuccess(['matches' => $data]);
    }

    /**
     * Get leagues
     */
    public function leaguesAction()
    {
        $leagues = League::find([
            'conditions' => 'is_active = true',
            'order' => 'country ASC, name ASC'
        ]);

        return $this->sendSuccess(['leagues' => $leagues->toArray()]);
    }
}

    /**
     * Matches list page
     */
    public function listPageAction()
    {
        $page = $this->request->getQuery('page', 'int', 1);
        $sport = $this->request->getQuery('sport', 'string');
        $league = $this->request->getQuery('league', 'int');
        $date = $this->request->getQuery('date', 'string', date('Y-m-d'));

        $conditions = [];
        $bind = [];

        if ($sport) {
            $conditions[] = 'sport = :sport:';
            $bind['sport'] = $sport;
        }

        if ($league) {
            $conditions[] = 'league_id = :league:';
            $bind['league'] = $league;
        }

        if ($date) {
            $conditions[] = 'DATE(match_date) = :date:';
            $bind['date'] = $date;
        }

        $matchQuery = [
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind,
            'order' => 'match_date ASC',
            'limit' => 20,
            'offset' => ($page - 1) * 20
        ];

        $this->view->matches = \Tahmin\Models\Match\Match::find($matchQuery);
        $this->view->leagues = \Tahmin\Models\Match\League::find();
        $this->view->page = (object)[
            'current' => $page,
            'total_pages' => ceil(\Tahmin\Models\Match\Match::count() / 20)
        ];
        $this->view->setMainView('layouts/main');
    }

    /**
     * Match detail page
     */
    public function viewPageAction($id)
    {
        $match = \Tahmin\Models\Match\Match::findFirst($id);
        if (!$match) {
            $this->flashSession->error('Maç bulunamadı');
            return $this->response->redirect('/matches');
        }

        $this->view->match = $match;
        $this->view->predictions = \Tahmin\Models\Prediction\Prediction::find([
            'conditions' => 'match_id = :id:',
            'bind' => ['id' => $id]
        ]);
        $this->view->setMainView('layouts/main');
    }
