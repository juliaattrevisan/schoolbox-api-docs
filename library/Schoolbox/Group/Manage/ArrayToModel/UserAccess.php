<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\Manage\ArrayToModel;

use Schoolbox\Permission\Access\UserAccess as UserAccessModel;

/**
 * Translator for translating an array representation of UserAccess into a model.
 */
class UserAccess
{
    // config of whether only staff can be a group admin
    private bool $groupStaffOnly;

    /**
     * @param bool $groupStaffOnly
     * @return void
     */
    public function __construct(bool $groupStaffOnly)
    {
        $this->groupStaffOnly = $groupStaffOnly;
    }

    /**
     * Translate the array representation of the group access into a model.
     */
    public function __invoke(array $array, ?object $model): object
    {
        if (!$model) {
            $model = (new UserAccessModel())
                ->setFavourite(true)
                ->setService(UserAccessModel::SERVICE_HOMEPAGE)
                ->setAccessLevel(UserAccessModel::ACCESS_READ);
        }

        // Only staff can be admin, if $groupStaffOnly is true
        if (isset($array['accessType'])) {
            if (
                $this->groupStaffOnly
                && $array['accessType'] === UserAccessModel::MEMBERSHIP_ADMIN
                && !$model->getUser()->getRole()->isStaff()
            ) {
                throw new \InvalidArgumentException('Only staff can be a group admin');
            }
            $model->setAccessLevel(UserAccessModel::ACCESS_NAME_TO_LEVEL[$array['accessType']]);
        }
        return $model;
    }
}
