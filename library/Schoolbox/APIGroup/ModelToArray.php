<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

use Schoolbox\Groups\GroupsHelper;

/**
 * Translator for translating a group model into an array, ready for JSON
 * encoding.
 *
 */
class ModelToArray
{
    /**
     * @param array $group
     * @return array
     */
    public function __invoke(array $group): array
    {
        $membership = GroupsHelper::MEMBERSHIP_NONE;
        if ($group['isAccessRead']) {
            $membership = GroupsHelper::MEMBERSHIP_READ;
        } elseif ($group['isAccessWrite']) {
            $membership = GroupsHelper::MEMBERSHIP_WRITE;
        } elseif ($group['isAccessAdmin']) {
            $membership = GroupsHelper::MEMBERSHIP_ADMIN;
        }
        $array = [
            'id'                => $group['id'],
            'name'              => $group['name'],
            'type'              => $group['type'],
            'isForceFollow'     => $group['isForceFollow'],
            'isFavourite'       => $group['isFavourite'],
            'isNotificationsOn' => $group['isNotificationsOn'],
            'enrolmentStatus'   => $group['enrolmentStatus'],
            'membership'        => $membership,
            '_links'            => null,

        ];
        if ($group['hasHomepage']) {
            $array['_links'] = [
                'homepage'      => sprintf('/homepage/%d', $group['id'])
            ];
        }
        return $array;
    }
}
