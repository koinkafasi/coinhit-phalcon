<?php

namespace Tahmin\Models\Match;

use Tahmin\Models\BaseModel;
use Phalcon\Mvc\Model\Behavior\Timestampable;

class Match extends BaseModel
{
    public int $id;
    public ?int $api_id = null;
    public ?int $external_id = null;
    public ?int $league_id = null;
    public int $home_team_id;
    public int $away_team_id;
    public \DateTime $match_date;
    public string $status = 'scheduled';
    public ?string $round = null;
    public ?string $competition = null;
    public ?string $competition_code = null;
    public ?int $matchday = null;
    public string $stage = 'REGULAR_SEASON';

    // Scores
    public ?int $home_score = null;
    public ?int $away_score = null;
    public ?int $home_halftime_score = null;
    public ?int $away_halftime_score = null;

    // JSON fields
    public ?array $statistics = null;
    public ?array $events = null;

    // Odds
    public ?float $home_odds = null;
    public ?float $draw_odds = null;
    public ?float $away_odds = null;

    public bool $is_featured = false;
    public ?\DateTime $created_at = null;
    public ?\DateTime $updated_at = null;

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_LIVE = 'live';
    const STATUS_HALFTIME = 'halftime';
    const STATUS_FINISHED = 'finished';
    const STATUS_POSTPONED = 'postponed';
    const STATUS_CANCELLED = 'cancelled';

    public function initialize()
    {
        parent::initialize();

        $this->setSource('matches');

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

        $this->belongsTo('league_id', League::class, 'id', [
            'alias' => 'league',
            'reusable' => true
        ]);

        $this->belongsTo('home_team_id', Team::class, 'id', [
            'alias' => 'homeTeam',
            'reusable' => true
        ]);

        $this->belongsTo('away_team_id', Team::class, 'id', [
            'alias' => 'awayTeam',
            'reusable' => true
        ]);

        $this->hasMany('id', 'Tahmin\Models\Prediction\Prediction', 'match_id', [
            'alias' => 'predictions'
        ]);
    }

    public function beforeSave()
    {
        // Convert arrays to JSON for storage
        if (is_array($this->statistics)) {
            $this->statistics = json_encode($this->statistics);
        }
        if (is_array($this->events)) {
            $this->events = json_encode($this->events);
        }
    }

    public function afterFetch()
    {
        // Decode JSON fields
        if (is_string($this->statistics)) {
            $this->statistics = json_decode($this->statistics, true);
        }
        if (is_string($this->events)) {
            $this->events = json_decode($this->events, true);
        }
    }

    /**
     * Check if match is live
     */
    public function isLive(): bool
    {
        return in_array($this->status, [self::STATUS_LIVE, self::STATUS_HALFTIME]);
    }

    /**
     * Check if match is finished
     */
    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    /**
     * Check if match is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->match_date > new \DateTime();
    }

    /**
     * Get score display
     */
    public function getScoreDisplay(): string
    {
        if ($this->home_score !== null && $this->away_score !== null) {
            return "{$this->home_score} - {$this->away_score}";
        }
        return "vs";
    }

    public function __toString(): string
    {
        $homeTeam = $this->getHomeTeam();
        $awayTeam = $this->getAwayTeam();
        return "{$homeTeam->name} vs {$awayTeam->name} - {$this->match_date->format('Y-m-d H:i')}";
    }
}
