<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

use Schoolbox\Collection;

/**
 * Translator for translating a user model into an array, ready for JSON
 * encoding.
 *
 * The information returned by this translator may contain private information,
 * such as the user's email address:
 * see `\Schoolbox\User\Translator\Author\ModelToArray` for a variant which is
 * guaranteed to return only public information.
 */
class ModelToArray
{
    const MEMBERSHIP_NONE = 'none';
    const MEMBERSHIP_READ = 'read';
    const MEMBERSHIP_WRITE = 'write';
    const MEMBERSHIP_ADMIN = 'admin';

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
            'enrollmentStatus'   => $group['enrolmentStatus'],
            'membership'        => $membership,
            '_links'            => [
                'homepage'      => sprintf('/homepage/%d', $group['id'])
            ]
        ];
    }
}
