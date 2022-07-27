<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\ArrayToModel;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Permission\Access\UserAccess as UserAccessModel;

/**
 * Translator for translating an array representation of learning moment
 * evidence into a model.
 *
 * Used when translating JSON passed in a request body into a model, so that it
 * may be persisted.
 */
class UserAccess extends Base
{
    /**
     * Translate the array representation of the group access into a model.
     */
    public function __invoke(array $json, ?object $model = null): object
    {
        if (!isset($model)) {
            $model = new UserAccessModel();
        }

        $fields = [
            'accessType'        => [
                'type'   => 'enum',
                'values' => ['admin', 'write', 'read'],
            ],
            'enrolmentStatus'   => [
                'type'   => 'enum',
                'values' => ['enroled', 'pending'],
            ],
            'isNotificationsOn' => [
                'type' => 'boolean',
            ],
            'isFavourite'       => [
                'type' => 'boolean',
            ],
        ];

        foreach ($fields as $name => $attributes) {
            $postedValue = $json[$name] ?? null;
            if (!$postedValue) {
                $errors[] = "{$name} is not set.";
                continue;
            }
            switch ($attributes['type']) {
                case 'enum':
                    if (!in_array($postedValue, $attributes['values'])) {
                        $errors[] = "{$name} must be one of " . implode(', ', $attributes['values']);
                        break;
                    }
                    break;
                case 'boolean':
                    break;
            }
        }

//        if (!empty($errors)) {
//            throw new \InvalidArgumentException(implode(',', $errors));
//        }

        // combining enrolementStatus and membership
        $accessLevel = 0;
        if (isset($json['accessType'])) {
            $accessLevel = GroupsHelper::getMembershipInt($json['accessType']);
        }
        $model->setAccessLevel($accessLevel);
        return $model;
    }
}
