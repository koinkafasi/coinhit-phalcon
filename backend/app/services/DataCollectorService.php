<?php

namespace Tahmin\Services;

use Tahmin\Models\Match\League;
use Tahmin\Models\Match\Team;
use Tahmin\Models\Match\Match;
use GuzzleHttp\Client;

class DataCollectorService
{
    private $httpClient;
    private $config;
    private $logger;

    public function __construct()
    {
        $di = \Phalcon\Di\Di::getDefault();
        $this->config = $di->get('config');
        $this->logger = $di->get('logger');

        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    /**
     * Collect fixtures from API-Football
     */
    public function collectFixtures(string $date = null): array
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $this->logger->info("Collecting fixtures for date: {$date}");

        try {
            $response = $this->httpClient->get($this->config->api->football->base_url . '/fixtures', [
                'headers' => [
                    'x-rapidapi-key' => $this->config->api->football->api_key,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ],
                'query' => [
                    'date' => $date,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['response'])) {
                throw new \Exception('Invalid API response');
            }

            $imported = 0;
            $updated = 0;

            foreach ($data['response'] as $fixture) {
                $result = $this->importFixture($fixture);
                if ($result === 'imported') $imported++;
                if ($result === 'updated') $updated++;
            }

            $this->logger->info("Fixtures collected: {$imported} imported, {$updated} updated");

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'total' => count($data['response']),
            ];

        } catch (\Exception $e) {
            $this->logger->error("Failed to collect fixtures: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Import single fixture
     */
    private function importFixture(array $fixture): string
    {
        $apiId = $fixture['fixture']['id'];

        // Check if match already exists
        $match = Match::findFirst(['api_id = ?0', 'bind' => [$apiId]]);

        if (!$match) {
            $match = new Match();
            $match->api_id = $apiId;
            $isNew = true;
        } else {
            $isNew = false;
        }

        // Get or create teams
        $homeTeam = $this->getOrCreateTeam($fixture['teams']['home']);
        $awayTeam = $this->getOrCreateTeam($fixture['teams']['away']);

        // Get or create league
        $league = $this->getOrCreateLeague($fixture['league']);

        // Update match details
        $match->league_id = $league->id;
        $match->home_team_id = $homeTeam->id;
        $match->away_team_id = $awayTeam->id;
        $match->match_date = new \DateTime($fixture['fixture']['date']);
        $match->status = $this->mapStatus($fixture['fixture']['status']['short']);
        $match->round = $fixture['league']['round'] ?? null;

        // Scores
        if (isset($fixture['goals'])) {
            $match->home_score = $fixture['goals']['home'];
            $match->away_score = $fixture['goals']['away'];
        }

        // Statistics
        if (isset($fixture['statistics'])) {
            $match->statistics = $fixture['statistics'];
        }

        // Events
        if (isset($fixture['events'])) {
            $match->events = $fixture['events'];
        }

        $match->save();

        return $isNew ? 'imported' : 'updated';
    }

    /**
     * Get or create team
     */
    private function getOrCreateTeam(array $teamData): Team
    {
        $apiId = $teamData['id'];

        $team = Team::findFirst(['api_id = ?0', 'bind' => [$apiId]]);

        if (!$team) {
            $team = new Team();
            $team->api_id = $apiId;
        }

        $team->name = $teamData['name'];
        $team->logo = $teamData['logo'] ?? null;
        $team->save();

        return $team;
    }

    /**
     * Get or create league
     */
    private function getOrCreateLeague(array $leagueData): League
    {
        $apiId = $leagueData['id'];

        $league = League::findFirst(['api_id = ?0', 'bind' => [$apiId]]);

        if (!$league) {
            $league = new League();
            $league->api_id = $apiId;
        }

        $league->name = $leagueData['name'];
        $league->country = $leagueData['country'] ?? '';
        $league->logo = $leagueData['logo'] ?? null;
        $league->season = $leagueData['season'] ?? date('Y');
        $league->save();

        return $league;
    }

    /**
     * Map API status to internal status
     */
    private function mapStatus(string $apiStatus): string
    {
        $statusMap = [
            'TBD' => Match::STATUS_SCHEDULED,
            'NS' => Match::STATUS_SCHEDULED,
            '1H' => Match::STATUS_LIVE,
            'HT' => Match::STATUS_HALFTIME,
            '2H' => Match::STATUS_LIVE,
            'ET' => Match::STATUS_LIVE,
            'P' => Match::STATUS_LIVE,
            'FT' => Match::STATUS_FINISHED,
            'AET' => Match::STATUS_FINISHED,
            'PEN' => Match::STATUS_FINISHED,
            'PST' => Match::STATUS_POSTPONED,
            'CANC' => Match::STATUS_CANCELLED,
            'ABD' => Match::STATUS_CANCELLED,
            'AWD' => Match::STATUS_FINISHED,
            'WO' => Match::STATUS_FINISHED,
        ];

        return $statusMap[$apiStatus] ?? Match::STATUS_SCHEDULED;
    }

    /**
     * Collect team statistics
     */
    public function collectTeamStatistics(int $leagueId, int $season): array
    {
        try {
            $league = League::findFirst($leagueId);
            if (!$league) {
                throw new \Exception('League not found');
            }

            $response = $this->httpClient->get($this->config->api->football->base_url . '/standings', [
                'headers' => [
                    'x-rapidapi-key' => $this->config->api->football->api_key,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ],
                'query' => [
                    'league' => $league->api_id,
                    'season' => $season,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['response'][0]['league']['standings'][0])) {
                throw new \Exception('No standings data');
            }

            $standings = $data['response'][0]['league']['standings'][0];
            $updated = 0;

            foreach ($standings as $standing) {
                $team = Team::findFirst(['api_id = ?0', 'bind' => [$standing['team']['id']]]);
                if (!$team) continue;

                // Update team statistics
                $team->position = $standing['rank'];
                $team->played_games = $standing['all']['played'];
                $team->won = $standing['all']['win'];
                $team->draw = $standing['all']['draw'];
                $team->lost = $standing['all']['lose'];
                $team->points = $standing['points'];
                $team->goals_for = $standing['all']['goals']['for'];
                $team->goals_against = $standing['all']['goals']['against'];
                $team->goal_difference = $standing['goalsDiff'];
                $team->save();

                $updated++;
            }

            return [
                'success' => true,
                'updated' => $updated,
            ];

        } catch (\Exception $e) {
            $this->logger->error("Failed to collect team statistics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update live scores
     */
    public function updateLiveScores(): array
    {
        try {
            $response = $this->httpClient->get($this->config->api->football->base_url . '/fixtures', [
                'headers' => [
                    'x-rapidapi-key' => $this->config->api->football->api_key,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ],
                'query' => [
                    'live' => 'all',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $updated = 0;

            foreach ($data['response'] as $fixture) {
                $match = Match::findFirst(['api_id = ?0', 'bind' => [$fixture['fixture']['id']]]);
                if (!$match) continue;

                $match->status = $this->mapStatus($fixture['fixture']['status']['short']);
                $match->home_score = $fixture['goals']['home'];
                $match->away_score = $fixture['goals']['away'];

                if (isset($fixture['score']['halftime'])) {
                    $match->home_halftime_score = $fixture['score']['halftime']['home'];
                    $match->away_halftime_score = $fixture['score']['halftime']['away'];
                }

                $match->save();
                $updated++;
            }

            return [
                'success' => true,
                'updated' => $updated,
            ];

        } catch (\Exception $e) {
            $this->logger->error("Failed to update live scores: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
