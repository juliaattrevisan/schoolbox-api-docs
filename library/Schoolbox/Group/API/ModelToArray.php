<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

use Schoolbox\Group\GroupsHelper;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Translator for translating a group model into an array, ready for JSON
 * encoding.
 *
 */
class ModelToArray
{
    /**
     * @param UserAccess $access
     * @return array
     */
    public function __invoke(UserAccess $access): array
    {
        $group = $access->getFolder()->getGroup();
        if (!$group) {
            return [];
        }

        $array = [
            'id'                => $access->getFolder()->getId(),
            'name'              => $access->getFolder()->getName(),
            'type'              => $group->getType(),
            'isForceFollow'     => $group->getForceFollow(),
            'isFavourite'       => $access->getFavourite(),
            'isNotificationsOn' => $access->getFollowing(),
            'enrolmentStatus'   => GroupsHelper::getEnrollmentText($access),
            'membership'        => UserAccess::ACCESS_LEVEL_TO_NAME[$access->getAccessLevel()],
            '_links'            => null,

        ];
        if ($access->getFolder()->hasHomepage()) {
            $array['_links'] = [
                'homepage' => sprintf('/homepage/%d', $access->getFolder()->getId()),
            ];
        }
        return $array;
    }
}
