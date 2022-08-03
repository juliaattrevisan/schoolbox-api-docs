<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;


/**
 * For a given student externalId, figure out who the family members are
 * and put their information in a convenient struct
 */
class CommRuleFamily
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
     * These are prefixes in array keys when you get a commrule payload
     *
        [address_description] => Parent/Guardian 1
        [address_person_gender] => F
        [address_person_gender_desc] => Female
        [address_person_label] => Parent/Guardian 1
        [address_person_posn] => 1
        ...
        [m_description] => Parent/Guardian 1
        [m_first_name] =>
        [m_initials] =>
        [m_other_name] =>
        [m_preferred_name] =>
        ...
        [f_description] => Parent/Guardian 2
        [f_first_name] =>
        [f_initials] =>
        [f_other_name] =>
        [f_preferred_name] =>
     *
     * Each commrule array is a Family representation and you have to take a guess
     * who's who by using these fields
     */
    private const FATHER_NODE = 'f';
    private const MOTHER_NODE = 'm';


    /**
     * Default spouse prefix
     *
     * @var string
     */
    private const DEFAULT_SPOUSE_PREFIX = 'S';


    /**
     * Constructor
     *
     * @param array  $api
     * @param string $spousePrefix
     */
    public function __construct(array $api, string $spousePrefix)
    {
        $this->api = $api;

        /* Calculate a spouse prefix if one was set; otherwise use a default
         * Shanked from that Tass Helper class
         */
        $this->spousePrefix = '' !== $spousePrefix ? $spousePrefix : self::DEFAULT_SPOUSE_PREFIX;
    }


    /**
     * Find the parents of $externalId
     *
     * @param string $externalId
     * @return array
     */
    public function findFamily(string $externalId): array
    {
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

                /* Compare the names - it's the only way to know whose phone numbers
                 * and email are contained in this struct
                 *
                 * Take a guess with the f_ prefixes first - if it matches the
                 * addressee, then this is the Father and we use the P prefix
                 * for their external id
                 *
                 * Otherwise, it's the mother, and we use the Mother's spouse prefix
                 * for their external id
                 */
            }
        }
    }


    /**
     * Apply parent prefix based on their spouseness and suffix based on their sfa_num value
     * I guess sfa_num stands for Split Family Access number or something
     *
     * @param string $node
     * @param string $identifier
     * @param string $sfaNum
     * @return string
     */
    private function deriveExternalId(string $node, string $identifier, string $sfaNum): string
    {
        /* Do some funny prefix stuff for parents
         */
        if (self::FATHER_NODE === $node) {
            $externalId = "P{$identifier}";
        } else {
            $externalId = "{$this->spousePrefix}{$identifier}";
        }

        /* TODO sfa_num suffix
         *  We lose sfa_num here though, unless we api them in getParents to find sfa_num specifically
         *  But what does that mean for a school, if we don't use it anymore?
         */
        return $externalId;
    }
}