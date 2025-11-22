<?php

namespace Tahmin\Models;

use Tahmin\Models\BaseModel;
use Phalcon\Mvc\Model\Behavior\Timestampable;

class Formula extends BaseModel
{
    public int $id;
    public int $user_id;
    public string $name;
    public ?string $description = null;
    public array $rules;
    public array $filters;
    public bool $is_active = true;
    public bool $is_public = false;
    public int $success_count = 0;
    public int $fail_count = 0;
    public float $success_rate = 0;
    public ?\DateTime $created_at = null;
    public ?\DateTime $updated_at = null;

    public function initialize()
    {
        parent::initialize();

        $this->setSource('formulas');

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
    }

    public function beforeSave()
    {
        if (is_array($this->rules)) {
            $this->rules = json_encode($this->rules);
        }
        if (is_array($this->filters)) {
            $this->filters = json_encode($this->filters);
        }
    }

    public function afterFetch()
    {
        if (is_string($this->rules)) {
            $this->rules = json_decode($this->rules, true);
        }
        if (is_string($this->filters)) {
            $this->filters = json_decode($this->filters, true);
        }
    }

    /**
     * Calculate success rate
     */
    public function calculateSuccessRate(): float
    {
        $total = $this->success_count + $this->fail_count;
        if ($total == 0) return 0;

        $this->success_rate = ($this->success_count / $total) * 100;
        $this->save();

        return $this->success_rate;
    }
}
