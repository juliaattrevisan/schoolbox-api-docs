<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Zenda;

use Schoolbox\External\Connector\FirstMeaningfulValue;

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
     * Generate a payload
     *
     * @param \Schoolbox\User $user
     * @return array
     */
    public function generate(\Schoolbox\User $user): array
    {
        /* Ask the sis about the parent's details
         */
        $parent = $this->externalUser->getParent($user->getExternalId());

        /* First check emails
         */
        $identifier = self::IDENTIFIER_EMAIL;

        $val = (new FirstMeaningfulValue())->find([
            $parent['Email'],
            $parent['OccupEmail'],
        ]);

        /* No emails? Try phone numbers
         */
        if (null === $val) {
            $identifier = self::IDENTIFIER_PHONE;

            $val = (new FirstMeaningfulValue())->find([
                $parent['HomePhone'],
                $parent['MobilePhone'],
                $parent['WorkPhone'],
            ]);

            /* Couldn't find either: I guess this is a misconfiguration somewhere in the sis, but we can't continue
             */
            if (null === $val) {
                throw new \RuntimeException('Indeterminate zenda identifier');
            }
        }

        /* Construct the payload using the identifier
         */
        $params = self::DEFAULT_STRUCT;

        $params['parentDetails'][$identifier] = $val;

        return $params;
    }
}
