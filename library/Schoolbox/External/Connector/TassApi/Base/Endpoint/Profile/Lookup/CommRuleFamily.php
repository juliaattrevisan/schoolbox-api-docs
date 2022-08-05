<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\FirstMeaningfulValue;
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
     * A custom spouse prefix, or the default one if unspecified
     */
    private string $spousePrefix;


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
     * @param string     $externalId
     * @param array|null $commRulesLookup
     * @param array|null $familiesLookup
     * @return array
     */
    public function find(string $externalId, ?array $commRulesLookup, ?array $familiesLookup): array
    {
        if (null === $commRulesLookup) {
            $commRules = $this->api[DSN::MODULE_STUDENT_DETAILS]->retrieveDocument('getCommunicationRulesDetails', [
                'code' => $externalId,
                'currentstatus' => 'current',
                'commtype' => 'all'
            ]);

            $addresses = $commRules['commrules'][0]['addresses'];
        } else {
            $addresses = $commRulesLookup[$externalId] ?? [];
        }

        $stack = [];

        foreach ($addresses as $commRule) {
            /* Not a parent? continue to the next comm rule
             */
            if ('' === trim((string)$commRule['address_person_posn'])) {
                continue;
            }

            /* Because tass fields in a random haphazard order
             */
            $sorted = $commRule;
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
            $possibleNames = [
                sprintf(
                    '%s %s',
                    $commRule['f_preferred_name'],
                    $commRule['f_surname'],
                ) => [
                    'node' => self::FATHER_NODE,
                    'firstname' => $commRule['f_preferred_name'],
                    'surname' => $commRule['f_surname']
                ],
                sprintf(
                    '%s %s',
                    $commRule['f_first_name'],
                    $commRule['f_surname'],
                ) => [
                    'node' => self::FATHER_NODE,
                    'firstname' => $commRule['f_first_name'],
                    'surname' => $commRule['f_surname']
                ],
                sprintf(
                    '%s %s',
                    $commRule['m_preferred_name'],
                    $commRule['m_surname'],
                ) => [
                    'node' => self::MOTHER_NODE,
                    'firstname' => $commRule['m_preferred_name'],
                    'surname' => $commRule['m_surname']
                ],
                sprintf(
                    '%s %s',
                    $commRule['m_first_name'],
                    $commRule['m_surname'],
                ) => [
                    'node' => self::MOTHER_NODE,
                    'firstname' => $commRule['m_first_name'],
                    'surname' => $commRule['m_surname']
                ],
            ];

            /* Take a guess, I guess, and see if it matches up
             */
            foreach ($possibleNames as $possibleName => $fields) {
                /* Not this person? Keep going
                 */
                if ($commRule['addressee'] !== $possibleName) {
                    continue;
                }

                /* Otherwise, close enough - cram on a prefix as per tradition
                 * and pile them into the Family stack.
                 */
                $externalId = $this->deriveExternalId($fields['node'], (string)$commRule['parent_code'], $familiesLookup);

                /* Pile it in to the stack
                 */
                $stack[$externalId] = [
                    'firstname' => $fields['firstname'],
                    'surname' => $fields['surname'],
                    'mobile' => (new FirstMeaningfulValue())->find([
                        $commRule['mobile_phone_1'],
                        $commRule['mobile_phone_2']
                    ]),
                    'home' => $commRule['home_phone'],
                    'work' => $commRule['business_phone'],
                    'email' => (new FirstMeaningfulValue())->find([
                        $commRule['email'],
                        $commRule['email_2']
                    ]),
                    'relationship' => $commRule['relationship'],
                ];
            }
        }

        return $stack;
    }


    /**
     * Apply parent prefix based on their spouseness and suffix based on their sfa_num value
     * I guess sfa_num stands for Split Family Access number or something
     *
     * @param string     $node
     * @param string     $identifier
     * @param array|null $familiesLookup
     * @return string
     */
    private function deriveExternalId(string $node, string $identifier, ?array $familiesLookup): string
    {
        /* Do some funny prefix stuff for parents
         */
        if (self::FATHER_NODE === $node) {
            $externalId = "P{$identifier}";
        } else {
            $externalId = "{$this->spousePrefix}{$identifier}";
        }

//        /* sfa_num suffix
//         * We lose sfa_num here though, unless we api them in getParents to find sfa_num specifically
//         * But what does that mean for a school, if we don't use it anymore?
//         *
//         * TODO But...do we actually need it, or is it some jank from live tass connector...?
//         */
//        if (null === $familiesLookup) {
//            $sfaNum = (new Parents($this->api))->findById($identifier);
//        } else {
//            $sfaNum = $familiesLookup[$externalId]['sfa_num'] ?? '';
//        }
//
//        return "{$externalId}{$sfaNum}";

        return $externalId;
    }
}
