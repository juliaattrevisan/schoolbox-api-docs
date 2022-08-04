<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\Manage\ArrayToModel;

use Schoolbox\API\Translator\ArrayToModel\Base;
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
            $model = (new UserAccessModel())
                ->setFavourite(true)
                ->setService(UserAccessModel::SERVICE_HOMEPAGE)
                ->setAccessLevel(UserAccessModel::ACCESS_READ);
        }

        $accessLevel = 0;
        if (isset($array['accessType'])) {
            $accessLevel = UserAccessModel::ACCESS_NAME_TO_LEVEL[$array['accessType']];
        }
        $model->setAccessLevel($accessLevel);
        return $model;
    }
}
