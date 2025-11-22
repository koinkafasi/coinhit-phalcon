<?php

namespace Tahmin\Models\Coupon;

use Tahmin\Models\BaseModel;
use Tahmin\Models\User;
use Phalcon\Mvc\Model\Behavior\Timestampable;
use Ramsey\Uuid\Uuid;

class Coupon extends BaseModel
{
    public string $id;  // UUID
    public int $user_id;
    public ?string $name = null;
    public string $coupon_type = 'multiple';
    public ?int $system_min_wins = null;
    public ?int $system_total_picks = null;
    public float $stake = 0;
    public float $total_odds = 1.00;
    public float $potential_win = 0;
    public string $status = 'pending';
    public float $actual_win = 0;
    public float $profit_loss = 0;
    public bool $is_shared = false;
    public ?string $share_code = null;
    public ?\DateTime $created_at = null;
    public ?\DateTime $updated_at = null;

    const TYPE_SINGLE = 'single';
    const TYPE_MULTIPLE = 'multiple';
    const TYPE_SYSTEM = 'system';

    const STATUS_PENDING = 'pending';
    const STATUS_WON = 'won';
    const STATUS_LOST = 'lost';
    const STATUS_PARTIALLY_WON = 'partially_won';
    const STATUS_VOID = 'void';

    public function initialize()
    {
        parent::initialize();

        $this->setSource('coupons');

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

        $this->belongsTo('user_id', User::class, 'id', [
            'alias' => 'user',
            'reusable' => true
        ]);

        $this->hasMany('id', CouponPick::class, 'coupon_id', [
            'alias' => 'picks',
            'foreignKey' => [
                'action' => self::ACTION_CASCADE
            ]
        ]);
    }

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = Uuid::uuid4()->toString();
        }
    }

    /**
     * Calculate total odds from all picks
     */
    public function calculateTotalOdds(): float
    {
        $picks = $this->getPicks();

        if ($this->coupon_type === self::TYPE_SINGLE) {
            if ($picks && count($picks) > 0) {
                $this->total_odds = $picks[0]->odds;
            }
        } elseif ($this->coupon_type === self::TYPE_MULTIPLE) {
            $this->total_odds = 1.00;
            foreach ($picks as $pick) {
                $this->total_odds *= $pick->odds;
            }
        } elseif ($this->coupon_type === self::TYPE_SYSTEM) {
            $this->total_odds = $this->calculateSystemOdds();
        }

        $this->potential_win = $this->stake * $this->total_odds;
        $this->save();

        return $this->total_odds;
    }

    /**
     * Calculate odds for system bets
     */
    private function calculateSystemOdds(): float
    {
        $picks = $this->getPicks();
        if (!$picks || count($picks) === 0) {
            return 1.00;
        }

        // Simplified system bet calculation
        $picksArray = iterator_to_array($picks);
        $totalCombinations = $this->binomialCoefficient(count($picksArray), $this->system_min_wins);
        $totalOdds = 0;

        $combinations = $this->combinations($picksArray, $this->system_min_wins);
        foreach ($combinations as $combo) {
            $comboOdds = 1.00;
            foreach ($combo as $pick) {
                $comboOdds *= $pick->odds;
            }
            $totalOdds += $comboOdds;
        }

        return $totalOdds / $totalCombinations;
    }

    /**
     * Check coupon result
     */
    public function checkResult(): void
    {
        $picks = $this->getPicks();
        if (!$picks || count($picks) === 0) {
            return;
        }

        // Check if all matches are finished
        $allFinished = true;
        foreach ($picks as $pick) {
            if (!$pick->getPrediction()->getMatch()->isFinished()) {
                $allFinished = false;
                break;
            }
        }

        if (!$allFinished) {
            return;
        }

        $wonPicks = 0;
        $lostPicks = 0;
        $voidPicks = 0;
        $totalPicks = count($picks);

        foreach ($picks as $pick) {
            if ($pick->status === 'won') $wonPicks++;
            if ($pick->status === 'lost') $lostPicks++;
            if ($pick->status === 'void') $voidPicks++;
        }

        if ($this->coupon_type === self::TYPE_SINGLE) {
            if ($wonPicks === 1) {
                $this->status = self::STATUS_WON;
                $this->actual_win = $this->potential_win;
                $this->profit_loss = $this->actual_win - $this->stake;
            } else {
                $this->status = self::STATUS_LOST;
                $this->profit_loss = -$this->stake;
            }
        } elseif ($this->coupon_type === self::TYPE_MULTIPLE) {
            if ($lostPicks > 0) {
                $this->status = self::STATUS_LOST;
                $this->profit_loss = -$this->stake;
            } elseif ($voidPicks === $totalPicks) {
                $this->status = self::STATUS_VOID;
            } else {
                $this->status = self::STATUS_WON;
                // Recalculate odds without void picks
                $validOdds = 1.00;
                foreach ($picks as $pick) {
                    if ($pick->status !== 'void') {
                        $validOdds *= $pick->odds;
                    }
                }
                $this->actual_win = $this->stake * $validOdds;
                $this->profit_loss = $this->actual_win - $this->stake;
            }
        }

        $this->save();
    }

    // Helper functions
    private function binomialCoefficient($n, $k): int
    {
        if ($k > $n) return 0;
        if ($k == 0 || $k == $n) return 1;

        $k = min($k, $n - $k);
        $c = 1;

        for ($i = 0; $i < $k; $i++) {
            $c = $c * ($n - $i) / ($i + 1);
        }

        return (int)$c;
    }

    private function combinations($array, $length): array
    {
        if ($length == 0) {
            return [[]];
        }
        if (count($array) == 0) {
            return [];
        }

        $head = $array[0];
        $tail = array_slice($array, 1);

        $combsWithHead = [];
        foreach ($this->combinations($tail, $length - 1) as $comb) {
            array_unshift($comb, $head);
            $combsWithHead[] = $comb;
        }

        $combsWithoutHead = $this->combinations($tail, $length);

        return array_merge($combsWithHead, $combsWithoutHead);
    }
}
