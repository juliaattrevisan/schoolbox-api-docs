<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\Manage\ArrayToModel;

use Schoolbox\API\Translator\ArrayToModel\Base;

/**
 * Translator for translating an array representation of group into a model.
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

        $notificationEnabled = $array['settings']['isNotificationsEnabled'] ?? false;
        $forceFollow         = $array['settings']['isForceFollow'] ?? false;
        $type                =
            \Schoolbox\Resource\Group::TYPE_NAME_TO_VAL[$array['settings']['access'] ?? '']
            ?? \Schoolbox\Resource\Group::PRIVATE_GROUP;

        $model->setNotificationDisabled(!$notificationEnabled)
              ->setForceFollow($forceFollow && $notificationEnabled)
              ->setType($type);
        return $model;
    }
}
