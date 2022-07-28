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
 * Translator for translating an array representation of UserAccess into a model.
 */
class UserAccess extends Base
{
    /**
     * Translate the array representation of the group access into a model.
     */
    public function __invoke(array $array, ?object $model = null): object
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

        $errors = [];
        foreach ($fields as $name => $attributes) {
            $postedValue = $array[$name] ?? null;
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
        if (isset($array['accessType'])) {
            $accessLevel = \Schoolbox\Permission\Access\UserAccess::ACCESS_NAME_TO_LEVEL[$array['accessType']];
        }
        $model->setAccessLevel($accessLevel);
        return $model;
    }
}
