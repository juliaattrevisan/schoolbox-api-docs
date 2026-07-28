<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Zenda;

use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\User;

class PayloadGenerator
{
    /**
     * Zenda expects one of the following to be used; preference TBD
     */
    private const string IDENTIFIER_EMAIL = 'email';
    private const string IDENTIFIER_PHONE = 'phone';


    /**
     * We need the externalUser to hydrate sis data; that's where phone numbers exist
     *
     * @param \External_User $externalUser
     */
    public function __construct(private \External_User $externalUser)
    {
    }


    /**
     * This is an unchanging part of the request payload we send to zenda;
     * the only part that is variable is the parentDetails node which would contain one of, but not both,
     * of the identifying fields: either phone or email
     *
     * TODO Find out from zenda which field takes precedence (they don't know yet)
     */
    private const array DEFAULT_STRUCT = [
        'targetMetadata' => [
            'platform' => 'web',
            'useCase' => 'auth',
            'targetData' => [
                'path' => 'dashboard'
            ]
        ],
        'parentDetails' => [
        ]
    ];


    /**
     * Generate a set of payloads to iteratively try sending to zenda: hopefully one of them succeeds.
     *
     * @param User   $user
     * @return array
     */
    public function generate(\Schoolbox\User $user): array
    {
        /* Ask the sis about the parent's details
         */
        $parent = $this->externalUser->getParent($user->getExternalId());

        /* Pile in all the possibilities here. It's actually fairly cheap considering we just invested in getParent()
         */
        $possibilities = [];

        $data = [
            self::IDENTIFIER_EMAIL => [
                $parent['Email'],
                $parent['OccupEmail'],
            ],
            self::IDENTIFIER_PHONE => [
                $parent['HomePhone'],
                $parent['MobilePhone'],
                $parent['WorkPhone'],
            ]
        ];

        /* Construct the payload using the identifier
         */
        foreach ($data as $type => $vals) {
            foreach ($vals as $val) {
                /* Inspect $val: meaningless ones get skipped
                 */
                if ('' === trim((string)$val)) {
                    continue;
                }

                $params = self::DEFAULT_STRUCT;

                $params['parentDetails'][$type] = trim((string)$val);

                /* Add it to the pile of payloads to Potentially Try
                 */
                $possibilities[$type][] = $params;
            }
        }

        return $possibilities;
    }
}
