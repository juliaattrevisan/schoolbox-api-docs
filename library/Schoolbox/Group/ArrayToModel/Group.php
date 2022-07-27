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
class Group extends Base
{
    /**
     * Translate the array representation of the group access into a model.
     */
    public function __invoke(array $array, ?object $model = null): object
    {
        if (!isset($model)) {
            $model = new \Schoolbox\Resource\Group();
        }

        $fields = [
            'access'        => [
                'type'   => 'enum',
                'values' => ['admin', 'write', 'read'],
            ],
            'isNotificationsEnabled'   => [
                'type' => 'boolean',
            ],
            'isForceFollow' => [
                'type' => 'boolean',
            ],
        ];

        $model->setType();
        return $model;

    }
}
