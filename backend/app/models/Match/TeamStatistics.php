<?php

namespace Tahmin\Models\Match;

use Tahmin\Models\BaseModel;
use Phalcon\Mvc\Model\Behavior\Timestampable;

class TeamStatistics extends BaseModel
{
    public int $id;
    public int $team_id;
    public int $league_id;
    public int $season;

    // General
    public int $matches_played = 0;
    public int $wins = 0;
    public int $draws = 0;
    public int $losses = 0;

    // Goals
    public int $goals_for = 0;
    public int $goals_against = 0;

    // Form
    public ?string $form = null;
    public int $form_score = 0;

    // Home/Away Split
    public int $home_wins = 0;
    public int $home_draws = 0;
    public int $home_losses = 0;
    public int $away_wins = 0;
    public int $away_draws = 0;
    public int $away_losses = 0;

    public ?\DateTime $updated_at = null;

    public function initialize()
    {
        parent::initialize();

        $this->setSource('team_statistics');

        $this->addBehavior(
            new Timestampable([
                'beforeUpdate' => [
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                ]
            ])
        );

        $this->belongsTo('team_id', Team::class, 'id', [
            'alias' => 'team',
            'reusable' => true
        ]);

        $this->belongsTo('league_id', League::class, 'id', [
            'alias' => 'league',
            'reusable' => true
        ]);
    }

    /**
     * Get total points
     */
    public function getPoints(): int
    {
        return ($this->wins * 3) + $this->draws;
    }

    /**
     * Get goal difference
     */
    public function getGoalDifference(): int
    {
        return $this->goals_for - $this->goals_against;
    }

    /**
     * Get win percentage
     */
    public function getWinPercentage(): float
    {
        if ($this->matches_played == 0) {
            return 0;
        }
        return round(($this->wins / $this->matches_played) * 100, 1);
    }

    public function __toString(): string
    {
        $team = $this->getTeam();
        $league = $this->getLeague();
        return "{$team->name} - {$league->name} {$this->season}";
    }
}
