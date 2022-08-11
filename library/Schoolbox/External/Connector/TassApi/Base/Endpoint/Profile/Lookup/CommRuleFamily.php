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
     * This is used to identify a father/mother in a joint record sigh
     * Confusingly, the inverse of FATHER_ and MOTHER_NODE
     */
    private const GENDER_MALE = 'M';


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
     * A set of tass communication rule identifiers
     *
     * @var string
     */
    private string $communicationTypes;


    /**
     * Constructor
     *
     * @param array  $api
     * @param string $spousePrefix
     * @param string $communicationTypes
     */
    public function __construct(array $api, string $spousePrefix, string $communicationTypes)
    {
        $this->api = $api;

        /* Calculate a spouse prefix if one was set; otherwise use a default
         * Shanked from that Tass Helper class
         */
        $this->spousePrefix = '' !== $spousePrefix ? $spousePrefix : self::DEFAULT_SPOUSE_PREFIX;

        $this->communicationTypes = $communicationTypes;
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
        /* We didn't collect all the commrules earlier in a big request
         * so start getting them for the $externalId specified
         *
         * Otherwise, if we collecetd all the commrules earlier, just
         * use that instead - if it doesn't exist, presume no rules
         * and exit without deciding any parents (is that possible...?)
         */
        if (null === $commRulesLookup) {
            $commRules = $this->api[DSN::MODULE_STUDENT_DETAILS]->retrieveDocument('getCommunicationRulesDetails', [
                'code' => $externalId,
                'currentstatus' => 'current',
                'commtype' => $this->communicationTypes
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

            /* Use the address_person_gender field to decide whether this
             * joint data is for the f_ or the m_ person - ultra-disgusting.
             */
            if (strtoupper($sorted['address_person_gender']) === self::GENDER_MALE) {
                $nodePrefix = self::FATHER_NODE;
            } else {
                $nodePrefix = self::MOTHER_NODE;
            }

            /* Cram on a prefix as per tradition
             * and pile them into the Family stack.
             */
            $externalId = $this->deriveExternalId($nodePrefix, (string)$commRule['parent_code'], $familiesLookup);

            $stack[$externalId] = [
                'firstname' => (new FirstMeaningfulValue())->find([
                    $commRule["{$nodePrefix}_preferred_name"],
                    $commRule["{$nodePrefix}_first_name"]
                ]),
                'surname' =>  $commRule["{$nodePrefix}_surname"],
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
