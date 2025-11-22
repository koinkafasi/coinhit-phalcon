<?php

namespace Tahmin\Models;

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Behavior\Timestampable;

class BaseModel extends Model
{
    public function initialize()
    {
        $this->setReadConnectionService('db');
        $this->setWriteConnectionService('db');
    }

    /**
     * Convert model to array
     */
    public function toArray($columns = null): array
    {
        $data = parent::toArray($columns);

        // Convert timestamps to ISO format
        if (isset($data['created_at']) && $data['created_at'] instanceof \DateTime) {
            $data['created_at'] = $data['created_at']->format('c');
        }
        if (isset($data['updated_at']) && $data['updated_at'] instanceof \DateTime) {
            $data['updated_at'] = $data['updated_at']->format('c');
        }

        return $data;
    }

    /**
     * JSON serialize
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
