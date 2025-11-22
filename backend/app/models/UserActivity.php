<?php

namespace Tahmin\Models;

use Phalcon\Mvc\Model\Behavior\Timestampable;

class UserActivity extends BaseModel
{
    public int $id;
    public int $user_id;
    public string $action;
    public ?string $description = null;
    public ?string $ip_address = null;
    public ?string $user_agent = null;
    public ?array $metadata = null;
    public ?\DateTime $created_at = null;

    public function initialize()
    {
        parent::initialize();

        $this->setSource('user_activities');

        $this->addBehavior(
            new Timestampable([
                'beforeCreate' => [
                    'field' => 'created_at',
                    'format' => 'Y-m-d H:i:s'
                ]
            ])
        );

        $this->belongsTo('user_id', User::class, 'id', [
            'alias' => 'user',
            'reusable' => true,
            'foreignKey' => [
                'action' => self::ACTION_CASCADE
            ]
        ]);
    }

    public function beforeSave()
    {
        if (is_array($this->metadata)) {
            $this->metadata = json_encode($this->metadata);
        }
    }

    public function afterFetch()
    {
        if (is_string($this->metadata)) {
            $this->metadata = json_decode($this->metadata, true);
        }
    }

    public function __toString(): string
    {
        $user = $this->getUser();
        return "{$user->email} - {$this->action}";
    }
}
