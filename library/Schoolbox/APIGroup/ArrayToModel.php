<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Translator for translating an array representation of user access into a model.
 */
class ArrayToModel extends Base
{
    /**
     * Translate the array representation of the group access into a model.
     */
    public function __invoke(array $array, ?object $model = null): object
    {
        if (!isset($model)) {
            $model = new UserAccess();
        }

        $fields = [
            'membership'        => [
                'type'   => 'enum',
                'values' => [
                    GroupsHelper::MEMBERSHIP_ADMIN,
                    GroupsHelper::MEMBERSHIP_WRITE,
                    GroupsHelper::MEMBERSHIP_READ,
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
            if (!isset($array[$name])) {
                $errors[] = "{$name} is not set.";
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
            throw new \InvalidArgumentException(implode(',', $errors));
        }

        // combining enrolementStatus and membership
        $accessLevel = 0;
        if ($array['enrolmentStatus'] === 'enroled') {
            $accessLevel = GroupsHelper::getMembershipInt($array['membership']);
        }
        $model->setAccessLevel($accessLevel);
        $model->setFavourite((bool)$array['isFavourite']);
        $model->setFollowing((bool)$array['isNotificationsOn']);
        return $model;
    }
}
