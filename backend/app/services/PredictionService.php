<?php

namespace Tahmin\Services;

use Tahmin\Models\Match\Match;
use Tahmin\Models\Match\Team;
use Tahmin\Models\Prediction\Prediction;

class PredictionService
{
    private $logger;
    private $redis;

    public function __construct()
    {
        $di = \Phalcon\Di\Di::getDefault();
        $this->logger = $di->get('logger');
        $this->redis = $di->get('redis');
    }

    /**
     * Generate predictions for a match
     */
    public function generatePredictions(Match $match): array
    {
        $this->logger->info("Generating predictions for match: {$match->id}");

        $features = $this->extractFeatures($match);
        $predictions = [];

        // 1X2 Prediction
        $predictions[] = $this->predict1X2($match, $features);

        // BTTS Prediction
        $predictions[] = $this->predictBTTS($match, $features);

        // Over/Under 2.5
        $predictions[] = $this->predictOverUnder($match, $features);

        // Double Chance
        $predictions[] = $this->predictDoubleChance($match, $features);

        return $predictions;
    }

    /**
     * Extract features from match data
     */
    private function extractFeatures(Match $match): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $features = [
            // Home team stats
            'home_position' => $homeTeam->position ?? 10,
            'home_points' => $homeTeam->points,
            'home_goals_for' => $homeTeam->goals_for,
            'home_goals_against' => $homeTeam->goals_against,
            'home_goal_diff' => $homeTeam->goal_difference,
            'home_form' => $this->calculateFormScore($homeTeam),

            // Away team stats
            'away_position' => $awayTeam->position ?? 10,
            'away_points' => $awayTeam->points,
            'away_goals_for' => $awayTeam->goals_for,
            'away_goals_against' => $awayTeam->goals_against,
            'away_goal_diff' => $awayTeam->goal_difference,
            'away_form' => $this->calculateFormScore($awayTeam),

            // Head to head
            'h2h_home_wins' => $this->getH2HWins($homeTeam, $awayTeam, true),
            'h2h_draws' => $this->getH2HDraws($homeTeam, $awayTeam),
            'h2h_away_wins' => $this->getH2HWins($awayTeam, $homeTeam, false),

            // League averages
            'league_avg_goals' => $this->getLeagueAvgGoals($match->league_id),
        ];

        return $features;
    }

    /**
     * Predict 1X2 outcome
     */
    private function predict1X2(Match $match, array $features): array
    {
        // Simple model based on statistics
        $homeAdvantage = 0.15; // 15% home advantage

        $homeStrength = $this->calculateTeamStrength($features, 'home');
        $awayStrength = $this->calculateTeamStrength($features, 'away');

        $homeStrength += $homeAdvantage;

        $total = $homeStrength + $awayStrength;
        $homeProb = $homeStrength / $total;
        $awayProb = $awayStrength / $total;
        $drawProb = 1 - ($homeProb + $awayProb) * 0.7; // Adjust for draw probability

        // Normalize
        $sum = $homeProb + $drawProb + $awayProb;
        $homeProb /= $sum;
        $drawProb /= $sum;
        $awayProb /= $sum;

        // Determine prediction
        if ($homeProb > $drawProb && $homeProb > $awayProb) {
            $result = '1';
            $confidence = $homeProb * 100;
        } elseif ($awayProb > $homeProb && $awayProb > $drawProb) {
            $result = '2';
            $confidence = $awayProb * 100;
        } else {
            $result = 'X';
            $confidence = $drawProb * 100;
        }

        return [
            'match_id' => $match->id,
            'type' => Prediction::TYPE_1X2,
            'result' => $result,
            'confidence' => round($confidence, 2),
            'probabilities' => [
                '1' => round($homeProb * 100, 2),
                'X' => round($drawProb * 100, 2),
                '2' => round($awayProb * 100, 2),
            ],
            'features' => $features,
        ];
    }

    /**
     * Predict Both Teams To Score
     */
    private function predictBTTS(Match $match, array $features): array
    {
        $homeGoalsFor = $features['home_goals_for'];
        $awayGoalsFor = $features['away_goals_for'];
        $homeGoalsAgainst = $features['home_goals_against'];
        $awayGoalsAgainst = $features['away_goals_against'];

        $homeScoreProb = min($homeGoalsFor / 38, 1) * min($awayGoalsAgainst / 38, 1);
        $awayScoreProb = min($awayGoalsFor / 38, 1) * min($homeGoalsAgainst / 38, 1);

        $bttsProb = $homeScoreProb * $awayScoreProb * 2; // Both score
        $confidence = $bttsProb > 0.5 ? $bttsProb * 100 : (1 - $bttsProb) * 100;

        return [
            'match_id' => $match->id,
            'type' => Prediction::TYPE_BTTS,
            'result' => $bttsProb > 0.5 ? 'yes' : 'no',
            'confidence' => round($confidence, 2),
            'probabilities' => [
                'yes' => round($bttsProb * 100, 2),
                'no' => round((1 - $bttsProb) * 100, 2),
            ],
            'features' => $features,
        ];
    }

    /**
     * Predict Over/Under 2.5 goals
     */
    private function predictOverUnder(Match $match, array $features): array
    {
        $avgHomeGoals = $features['home_goals_for'] / max($features['home_position'], 1);
        $avgAwayGoals = $features['away_goals_for'] / max($features['away_position'], 1);

        $expectedGoals = $avgHomeGoals + $avgAwayGoals + $features['league_avg_goals'];
        $expectedGoals /= 2;

        $overProb = $expectedGoals > 2.5 ? min($expectedGoals / 4, 0.85) : max(0.15, $expectedGoals / 4);
        $confidence = $overProb > 0.5 ? $overProb * 100 : (1 - $overProb) * 100;

        return [
            'match_id' => $match->id,
            'type' => Prediction::TYPE_OVER_UNDER,
            'result' => $overProb > 0.5 ? 'over' : 'under',
            'confidence' => round($confidence, 2),
            'probabilities' => [
                'over' => round($overProb * 100, 2),
                'under' => round((1 - $overProb) * 100, 2),
            ],
            'expected_goals' => round($expectedGoals, 2),
            'features' => $features,
        ];
    }

    /**
     * Predict Double Chance
     */
    private function predictDoubleChance(Match $match, array $features): array
    {
        // Get 1X2 probabilities first
        $prediction1X2 = $this->predict1X2($match, $features);
        $probs = $prediction1X2['probabilities'];

        // Calculate double chance probabilities
        $prob1X = $probs['1'] + $probs['X'];
        $prob12 = $probs['1'] + $probs['2'];
        $probX2 = $probs['X'] + $probs['2'];

        // Find best double chance
        $bestProb = max($prob1X, $prob12, $probX2);

        if ($bestProb === $prob1X) {
            $result = '1X';
        } elseif ($bestProb === $prob12) {
            $result = '12';
        } else {
            $result = 'X2';
        }

        return [
            'match_id' => $match->id,
            'type' => Prediction::TYPE_DOUBLE_CHANCE,
            'result' => $result,
            'confidence' => round($bestProb, 2),
            'probabilities' => [
                '1X' => round($prob1X, 2),
                '12' => round($prob12, 2),
                'X2' => round($probX2, 2),
            ],
            'features' => $features,
        ];
    }

    /**
     * Calculate team strength score
     */
    private function calculateTeamStrength(array $features, string $side): float
    {
        $prefix = $side;

        $positionScore = (20 - min($features["{$prefix}_position"], 20)) / 20;
        $pointsScore = $features["{$prefix}_points"] / 100;
        $goalDiffScore = ($features["{$prefix}_goal_diff"] + 50) / 100;
        $formScore = $features["{$prefix}_form"] / 100;

        return ($positionScore * 0.3) + ($pointsScore * 0.3) + ($goalDiffScore * 0.2) + ($formScore * 0.2);
    }

    /**
     * Calculate form score from recent matches
     */
    private function calculateFormScore(Team $team): float
    {
        // Simple calculation based on wins/draws/losses
        $totalMatches = $team->played_games;
        if ($totalMatches == 0) return 50;

        $formScore = (($team->won * 3) + $team->draw) / ($totalMatches * 3) * 100;

        return $formScore;
    }

    /**
     * Get head-to-head wins
     */
    private function getH2HWins(Team $team1, Team $team2, bool $isHome): int
    {
        // TODO: Implement actual H2H lookup
        // For now return mock data
        return rand(0, 5);
    }

    /**
     * Get head-to-head draws
     */
    private function getH2HDraws(Team $team1, Team $team2): int
    {
        // TODO: Implement actual H2H lookup
        return rand(0, 3);
    }

    /**
     * Get league average goals
     */
    private function getLeagueAvgGoals(?int $leagueId): float
    {
        if (!$leagueId) return 2.5;

        // TODO: Calculate actual league average
        // For now return typical average
        return 2.7;
    }

    /**
     * Save prediction to database
     */
    public function savePrediction(array $predictionData): Prediction
    {
        $prediction = new Prediction();
        $prediction->match_id = $predictionData['match_id'];
        $prediction->prediction_type = $predictionData['type'];
        $prediction->predicted_result = $predictionData['result'];
        $prediction->confidence_score = $predictionData['confidence'];
        $prediction->model_version = 'v1.0';
        $prediction->features_used = $predictionData['features'];
        $prediction->is_premium = $predictionData['confidence'] >= 75;
        $prediction->is_featured = $predictionData['confidence'] >= 85;

        $prediction->save();

        return $prediction;
    }

    /**
     * Batch generate predictions for upcoming matches
     */
    public function batchGeneratePredictions(int $days = 7): array
    {
        $startDate = date('Y-m-d 00:00:00');
        $endDate = date('Y-m-d 23:59:59', strtotime("+{$days} days"));

        $matches = Match::find([
            'conditions' => 'status = :status: AND match_date BETWEEN :start: AND :end:',
            'bind' => [
                'status' => Match::STATUS_SCHEDULED,
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);

        $generated = 0;

        foreach ($matches as $match) {
            // Check if predictions already exist
            $existing = Prediction::count(['match_id = ?0', 'bind' => [$match->id]]);
            if ($existing > 0) continue;

            $predictions = $this->generatePredictions($match);

            foreach ($predictions as $predData) {
                $this->savePrediction($predData);
                $generated++;
            }
        }

        return [
            'success' => true,
            'matches_processed' => count($matches),
            'predictions_generated' => $generated,
        ];
    }
}
