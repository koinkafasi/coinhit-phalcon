<?php

namespace Tahmin\Models\Match;

use Tahmin\Models\BaseModel;
use Phalcon\Mvc\Model\Behavior\Timestampable;

class League extends BaseModel
{
    public int $id;
    public int $api_id;
    public string $name;
    public string $country;
    public ?string $logo = null;
    public int $season;
    public bool $is_active = true;
    public ?\DateTime $created_at = null;
    public ?\DateTime $updated_at = null;

    public function initialize()
    {
        parent::initialize();

        $this->setSource('leagues');

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

        $this->hasMany('id', Match::class, 'league_id', [
            'alias' => 'matches'
        ]);
    }

    public function __toString(): string
    {
        return "{$this->name} ({$this->country})";
    }
}
