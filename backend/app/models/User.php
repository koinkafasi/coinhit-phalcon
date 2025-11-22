<?php

namespace Tahmin\Models;

use Phalcon\Mvc\Model\Behavior\Timestampable;
use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\Validator\Email;
use Phalcon\Filter\Validation\Validator\Uniqueness;

class User extends BaseModel
{
    public int $id;
    public string $email;
    public ?string $full_name = null;
    public string $password;
    public string $role = 'user';
    public string $membership_tier = 'free';
    public ?\DateTime $membership_expires_at = null;
    public bool $is_active = true;
    public bool $is_staff = false;
    public bool $is_verified = false;
    public ?\DateTime $created_at = null;
    public ?\DateTime $updated_at = null;
    public ?\DateTime $last_login_at = null;

    const ROLE_ADMIN = 'admin';
    const ROLE_MODERATOR = 'moderator';
    const ROLE_PREMIUM = 'premium';
    const ROLE_USER = 'user';

    const TIER_FREE = 'free';
    const TIER_PRO = 'pro';
    const TIER_PREMIUM = 'premium';

    public function initialize()
    {
        parent::initialize();

        $this->setSource('users');

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

        $this->hasMany('id', UserActivity::class, 'user_id', [
            'alias' => 'activities',
            'foreignKey' => [
                'action' => self::ACTION_CASCADE
            ]
        ]);

        $this->hasMany('id', 'Tahmin\Models\Prediction\UserPrediction', 'user_id', [
            'alias' => 'predictions'
        ]);

        $this->hasMany('id', 'Tahmin\Models\Coupon\Coupon', 'user_id', [
            'alias' => 'coupons'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add(
            'email',
            new Email([
                'message' => 'Invalid email address'
            ])
        );

        $validator->add(
            'email',
            new Uniqueness([
                'message' => 'Email already exists'
            ])
        );

        return $this->validate($validator);
    }

    public function beforeSave()
    {
        // Hash password if it's being set
        if ($this->hasChanged('password')) {
            $this->password = $this->getDI()->getSecurity()->hash($this->password);
        }
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return $this->getDI()->getSecurity()->checkHash($password, $this->password);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->is_staff;
    }

    /**
     * Check if user is moderator or higher
     */
    public function isModerator(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MODERATOR]) || $this->is_staff;
    }

    /**
     * Check if user has premium subscription
     */
    public function isPremiumUser(): bool
    {
        if (in_array($this->membership_tier, [self::TIER_PRO, self::TIER_PREMIUM])) {
            if ($this->membership_expires_at && $this->membership_expires_at > new \DateTime()) {
                return true;
            }
        }
        return $this->isModerator();
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $permissions = [
            'view_dashboard' => [self::ROLE_ADMIN, self::ROLE_MODERATOR],
            'manage_users' => [self::ROLE_ADMIN],
            'manage_matches' => [self::ROLE_ADMIN, self::ROLE_MODERATOR],
            'manage_predictions' => [self::ROLE_ADMIN, self::ROLE_MODERATOR],
            'view_analytics' => [self::ROLE_ADMIN, self::ROLE_MODERATOR],
            'trigger_collector' => [self::ROLE_ADMIN],
            'manage_subscriptions' => [self::ROLE_ADMIN],
            'moderate_content' => [self::ROLE_ADMIN, self::ROLE_MODERATOR],
        ];

        $allowedRoles = $permissions[$permission] ?? [];
        return in_array($this->role, $allowedRoles);
    }

    /**
     * Hide password from JSON
     */
    public function toArray($columns = null): array
    {
        $data = parent::toArray($columns);
        unset($data['password']);
        return $data;
    }
}
