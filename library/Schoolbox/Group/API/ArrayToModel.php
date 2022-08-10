<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\API;

use Schoolbox\Group\GroupsHelper;
use Schoolbox\Group\UserGroupAccess;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Translator for translating an array representation of user access into a model.
 */
class ArrayToModel
{
    /**
     * Translate the array representation of the group access into a model.
     */
    public function __invoke(array $array, ?object $model, bool $strict): object
    {
        if (!isset($model)) {
            $model = new UserGroupAccess();
        }

        $fields = [
            'membership'        => [
                'type'   => 'enum',
                'values' => [
                    UserAccess::MEMBERSHIP_ADMIN,
                    UserAccess::MEMBERSHIP_WRITE,
                    UserAccess::MEMBERSHIP_READ,
                ],
            ],
            'enrolmentStatus'   => [
                'type'   => 'enum',
                'values' => [
                    GroupsHelper::ENROLED,
                    GroupsHelper::PENDING,
                ],
            ],
            'isNotificationsOn' => [
                'type' => 'boolean',
            ],
            'isFavourite'       => [
                'type' => 'boolean',
            ],
        ];

        foreach ($fields as $name => $attributes) {
            if ($strict && !isset($array[$name])) {
                $errors[] = "{$name} is not set";
                continue;
            }
            switch ($attributes['type']) {
                case 'enum':
                    if (!in_array($array[$name], $attributes['values'])) {
                        $errors[] = "{$name} must be one of " . implode(', ', $attributes['values']);
                        break;
                    }
                    break;
                case 'boolean':
                    break;
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }

        // combining enrolementStatus and membership
        if (isset($array['enrolmentStatus']) && $array['enrolmentStatus'] == GroupsHelper::PENDING) {
            $model->setAccessLevel(UserAccess::ACCESS_AWAITING_AUTHORISATION);
        } elseif (isset($array['membership'])) {
            $model->setAccessLevel(UserAccess::ACCESS_NAME_TO_LEVEL[$array['membership']]);
        }
        if (isset($array['isFavourite'])) {
            $model->setFavourite((bool)$array['isFavourite']);
        }
        if (isset($array['isNotificationsOn'])) {
            $model->setFollowing((bool)$array['isNotificationsOn']);
        }
        return $model;
    }
}
