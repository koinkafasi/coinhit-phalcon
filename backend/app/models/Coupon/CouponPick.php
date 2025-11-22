<?php

namespace Tahmin\Models\Coupon;

use Tahmin\Models\BaseModel;
use Tahmin\Models\Prediction\Prediction;
use Phalcon\Mvc\Model\Behavior\Timestampable;

class CouponPick extends BaseModel
{
    public int $id;
    public string $coupon_id;  // UUID
    public int $prediction_id;
    public float $odds;
    public bool $is_banker = false;
    public string $status = 'pending';
    public ?\DateTime $created_at = null;

    public function initialize()
    {
        parent::initialize();

        $this->setSource('coupon_picks');

        $this->addBehavior(
            new Timestampable([
                'beforeCreate' => [
                    'field' => 'created_at',
                    'format' => 'Y-m-d H:i:s'
                ]
            ])
        );

        $this->belongsTo('coupon_id', Coupon::class, 'id', [
            'alias' => 'coupon',
            'reusable' => true
        ]);

        $this->belongsTo('prediction_id', Prediction::class, 'id', [
            'alias' => 'prediction',
            'reusable' => true
        ]);
    }

    /**
     * Update status based on prediction result
     */
    public function checkResult(): void
    {
        $prediction = $this->getPrediction();
        if ($prediction) {
            $prediction->checkResult();
            $this->status = $prediction->status;
            $this->save();
        }
    }
}
