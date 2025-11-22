<?php

namespace Tahmin\Models\Prediction;

use Tahmin\Models\BaseModel;
use Tahmin\Models\Match\Match;
use Phalcon\Mvc\Model\Behavior\Timestampable;

class Prediction extends BaseModel
{
    public int $id;
    public int $match_id;
    public string $prediction_type;
    public string $predicted_result;
    public float $confidence_score;
    public string $model_version = 'v1.0';
    public ?array $features_used = null;
    public ?float $recommended_odds = null;
    public ?float $actual_odds = null;
    public string $status = 'pending';
    public ?string $actual_result = null;
    public bool $is_premium = false;
    public bool $is_featured = false;
    public ?\DateTime $created_at = null;
    public ?\DateTime $updated_at = null;

    const TYPE_1X2 = '1x2';
    const TYPE_DOUBLE_CHANCE = 'double_chance';
    const TYPE_BTTS = 'btts';
    const TYPE_OVER_UNDER = 'over_under';
    const TYPE_HOME_OVER_UNDER = 'home_over_under';
    const TYPE_AWAY_OVER_UNDER = 'away_over_under';
    const TYPE_CORRECT_SCORE = 'correct_score';

    const STATUS_PENDING = 'pending';
    const STATUS_WON = 'won';
    const STATUS_LOST = 'lost';
    const STATUS_VOID = 'void';

    public function initialize()
    {
        parent::initialize();

        $this->setSource('predictions');

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

        $this->belongsTo('match_id', Match::class, 'id', [
            'alias' => 'match',
            'reusable' => true
        ]);

        $this->hasMany('id', 'Tahmin\Models\Coupon\CouponPick', 'prediction_id', [
            'alias' => 'couponPicks'
        ]);
    }

    public function beforeSave()
    {
        if (is_array($this->features_used)) {
            $this->features_used = json_encode($this->features_used);
        }
    }

    public function afterFetch()
    {
        if (is_string($this->features_used)) {
            $this->features_used = json_decode($this->features_used, true);
        }
    }

    /**
     * Get confidence as percentage
     */
    public function getConfidencePercentage(): string
    {
        return $this->confidence_score . '%';
    }

    /**
     * Check if high confidence
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence_score >= 75;
    }

    /**
     * Check result after match finishes
     */
    public function checkResult(): void
    {
        $match = $this->getMatch();
        if (!$match || !$match->isFinished()) {
            return;
        }

        $homeScore = $match->home_score;
        $awayScore = $match->away_score;

        if ($this->prediction_type === self::TYPE_1X2) {
            if ($homeScore > $awayScore) {
                $actual = '1';
            } elseif ($homeScore === $awayScore) {
                $actual = 'X';
            } else {
                $actual = '2';
            }
            $this->actual_result = $actual;
            $this->status = ($actual === $this->predicted_result) ? self::STATUS_WON : self::STATUS_LOST;
        } elseif ($this->prediction_type === self::TYPE_BTTS) {
            $actual = ($homeScore > 0 && $awayScore > 0) ? 'yes' : 'no';
            $this->actual_result = $actual;
            $this->status = ($actual === $this->predicted_result) ? self::STATUS_WON : self::STATUS_LOST;
        } elseif ($this->prediction_type === self::TYPE_OVER_UNDER) {
            $totalGoals = $homeScore + $awayScore;
            $actual = ($totalGoals > 2.5) ? 'over' : 'under';
            $this->actual_result = $actual;
            $this->status = ($actual === $this->predicted_result) ? self::STATUS_WON : self::STATUS_LOST;
        }

        $this->save();
    }
}
