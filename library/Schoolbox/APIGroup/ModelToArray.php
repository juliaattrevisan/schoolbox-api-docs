<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

/**
 * Translator for translating a group model into an array, ready for JSON
 * encoding.
 *
 */
class ModelToArray
{
    private const MEMBERSHIP_NONE = 'none';
    private const MEMBERSHIP_READ = 'read';
    private const MEMBERSHIP_WRITE = 'write';
    private const MEMBERSHIP_ADMIN = 'admin';

    /**
     * @param array $group
     * @return array
     */
    public function __invoke(array $group): array
    {
        $membership = self::MEMBERSHIP_NONE;
        if ($group['isAccessRead']) {
            $membership = self::MEMBERSHIP_READ;
        } elseif ($group['isAccessWrite']) {
            $membership = self::MEMBERSHIP_WRITE;
        } elseif ($group['isAccessAdmin']) {
            $membership = self::MEMBERSHIP_ADMIN;
        }
        return [
            'id'                => $group['id'],
            'name'              => $group['name'],
            'type'              => $group['type'],
            'isForceFollow'     => $group['isForceFollow'],
            'isFavourite'       => $group['isFavourite'],
            'isNotificationsOn' => $group['isNotificationsOn'],
            'enrolmentStatus'   => $group['enrolmentStatus'],
            'membership'        => $membership,
            '_links'            => [
                'homepage'      => sprintf('/homepage/%d', $group['id'])
            ]
        ];
    }
}
