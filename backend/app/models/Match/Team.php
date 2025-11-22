<?php

namespace Tahmin\Models\Match;

use Tahmin\Models\BaseModel;
use Phalcon\Mvc\Model\Behavior\Timestampable;

class Team extends BaseModel
{
    public int $id;
    public int $api_id;
    public ?int $external_id = null;
    public string $name;
    public ?string $short_name = null;
    public ?string $tla = null;
    public ?string $logo = null;
    public ?string $crest_url = null;
    public ?string $country = null;
    public ?int $founded = null;
    public ?string $venue_name = null;

    // Standings data
    public ?int $position = null;
    public int $played_games = 0;
    public int $won = 0;
    public int $draw = 0;
    public int $lost = 0;
    public int $points = 0;
    public int $goals_for = 0;
    public int $goals_against = 0;
    public int $goal_difference = 0;

    public ?\DateTime $created_at = null;
    public ?\DateTime $updated_at = null;

    public function initialize()
    {
        parent::initialize();

        $this->setSource('teams');

        $this->addBehavior(
            new Timestampable([
                'beforeCreate' => [
                    'field' => 'created_at',
                    'format' => 'Y-m-d H:i:s'
                ],
                'beforeUpdate' => [
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                ]
            ])
        );

        $this->hasMany('id', Match::class, 'home_team_id', [
            'alias' => 'homeMatches'
        ]);

        $this->hasMany('id', Match::class, 'away_team_id', [
            'alias' => 'awayMatches'
        ]);

        $this->hasMany('id', TeamStatistics::class, 'team_id', [
            'alias' => 'statistics'
        ]);
    }

    /**
     * Calculate team's form as percentage
     */
    public function getFormPercentage(): float
    {
        if ($this->played_games == 0) {
            return 0;
        }
        return ($this->points / ($this->played_games * 3)) * 100;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
