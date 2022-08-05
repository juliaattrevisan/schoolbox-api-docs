<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer;

use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;

/**
 * For a givent set of student externalIds,
 * find all the tass commrules that are parents
 *
 * Parents' commrules have a number in the address_person_posn field
 * I don't really know what that means, but apparently tass thinks
 * this is a definitive way to deduce parents of a student
 *
 * This also dodges around the mind-boggling crusade about Split Families
 * that we struggled against for a ******* **** **** !#*!$
 *
 * I'll never get back those two weeks of my life.
 * Disgusting.
 */
class ParentCommRules
{
    /**
     * A set of (product, api) tuples for the entire tass suite
     *
        $api = [
            Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION),
            Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS),
            Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT)
        ];
     *
     * @var array
     */
    private array $api;


    /**
     * @param array $api
     */
    public function __construct(array $api)
    {
        $this->api = $api;
    }


    /**
     * Find the parents of $externalIds provided in a 1D stack
     * via their address_person_posn value in a set of commrules
     *
     * @param array $externalIds
     * @return void
     * @throws \DebugBar\DebugBarException
     */
    public function findParents(array $externalIds): void
    {
        foreach ($externalIds as $externalId) {
            $student = $this->api[DSN::MODULE_IDENTITY_MANAGEMENT]->retrieveDocument('getStudents', [
                'user_code' => $externalId
            ]);

            $human = $student['students'];

            $commRules = $this->api[DSN::MODULE_STUDENT_DETAILS]->retrieveDocument('getCommunicationRulesDetails', [
                'code' => $externalId,
                'currentstatus' => 'current',
                'commtype' => 'all'
            ]);

            $stack = [];

            foreach ($commRules['commrules'][0]['addresses'] as $current) {
                if ('' !== trim((string)$current['address_person_posn'])) {
                    /* Because tass fields in a random haphazard order
                     */
                    $sorted = $current;
                    ksort($sorted);

                    $stack[] = $sorted;
                }
            }

            new \Alaress_Probe($human, $stack);
            new \Alaress_Probe('<hr />');
        }
    }
}
