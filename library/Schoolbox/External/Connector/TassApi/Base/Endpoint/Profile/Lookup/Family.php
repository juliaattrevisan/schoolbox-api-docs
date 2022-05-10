<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\IApi;

/**
 * Class Family
 *
 * Builds a $lookup for family relations, indexed by a student's id, so you can find a student's
 * family easily via $index which contain family records, then iterate through its members.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup
 */
class Family
{

    /**
     * The node corresponding to a father's data in a Family record.
     *
     * I have a fear that this will be different per tass implementation somewhere but, for now,
     * presume that 'person2' is (and always is) the papa
     *
     * + Assumption: person2 represents the primary parent (typically Father)
     */
    public const FATHER_NODE = 'person2';
    public const MOTHER_NODE = 'person1';


    /**
     * Default spouse prefix
     *
     * @var string
     */
    private const DEFAULT_SPOUSE_PREFIX = 'S';


    /**
     * Parents records are actually Family records; Family records contain exactly two nodes,
     * one for each parent, plus some miscellaneous stuff.
     *
     * These two nodes have the following keys and we use them to identify father and mother details.
     */
    private const PARENT_PERSON_NODES = ['person2', 'person1'];


    /**
     * $api uses the credentials for DSNCodec::MODULE_IDENTITY_MANAGEMENT
     *
     * @var IApi
     */
    private IApi $api;


    /**
     * Set in Admin. This var deconflicts shared usernames by putting a letter in front,
     * because tass doesn't treat parents independently of families.
     *
     * @var string
     */
    private string $spousePrefix;


    /**
     * Set in admin, corresponds to external_tass_addnum_father
     *
     * @var int
     */
    private int $addnumFather;


    /**
     * Set in admin, corresponds to external_tass_addnum_mother
     *
     * @var int
     */
    private int $addnumMother;


    /**
     * TASS communication rules type codes (in comma separated format) for all guardian relationships
     * that can be associated with students
     * @var string|null
     */
    private ?string $communicationTypes;


    /**
     * An array of phone numbers indexed by family code
     *
     * @var array
     */
    private array $phones;


    /**
     * A list of (studentExternalId, parentExternalId) tuples
     *
     * @var array
     */
    private array $lookup = [];


    /**
     * Family constructor.
     *
     * $api uses the credentials for DSNCodec::MODULE_IDENTITY_MANAGEMENT
     *
     * @param IApi   $api
     * @param string $spousePrefix
     * @param int    $addnumFather
     * @param int    $addnumMother
     * @param string|null $communicationTypes
     * @param array  $phones
     */
    public function __construct(
        IApi $api,
        string $spousePrefix,
        int $addnumFather,
        int $addnumMother,
        ?string $communicationTypes,
        array $phones
    ) {
        $this->api = $api;

        /* Calculate a spouse prefix if one was set; otherwise use a default
         * Shanked from that Tass Helper class
         */
        $this->spousePrefix = '' !== $spousePrefix ? $spousePrefix : self::DEFAULT_SPOUSE_PREFIX;

        /* Some admin vars we'll use in this place
         */
        $this->addnumFather = $addnumFather;
        $this->addnumMother = $addnumMother;

        $this->communicationTypes = $communicationTypes;

        /* Phone lookup; data from another endpoint
         */
        $this->phones = $phones;
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

        /* If this was a split family, chuck a $sfaNum into the mix
         *
         * After 99,999 students pass through a tass system, this scheme will stop working
         * due to collisions between old ids and new ones with prefix
         *
         * P100001 (parent of student 10000 + split family 1) == P100001 (parent of student 100001)
         */
        if ('' !== $sfaNum) {
            $externalId .= $sfaNum ;
        }

        return $externalId;
    }


    /**
     * Preload parents into a lookup
     *
     * This is probably going to be faster if we build a hash lookup of parents
     */
    public function generate(): array
    {

        /* Grab all the families
         */
        $families = $this->api->retrieveDocument('getParents', [
            'user_code' => 'all'
        ]);

        $unknown = [];

        /* Tass generally adheres to $parent data structure
         *
                Array
                (
                    [web_access] =>
                    [user_code] => 10620
                    [person2] => Array
                    [person1] => Array
                    ...

         *
         * ...EXCEPT when they've joined onto an email address table somewhere, at which point
         * their own api structure mutates slightly into something nonstandard,
         * where $parent becomes a 0-indexed array instead of the usual key-value tuple content.
         *
         * no joke. I can't make this stuff up even on a bad day.
         *
         * The "malformed" data occurs with split families so they end up with separate records
         * that contain similar, thought no always identical, data; but with the same parent_code
         *

                Array
                (
                    [0] => Array
                        (
                            [web_access] => 1
                            [user_code] => 10119
                            [person2] => Array
                            [address] => Array
                                (
                                    [4] => Array
                                        (
                                            [email2] =>
                                            [email1] => jones@nowhere.com
                                        )
                                )
                            [person1] => Array
                        )
                    [1] => Array
                        (
                            [web_access] => 1
                            [user_code] => 10119
                            [person2] => Array
                            [address] => Array
                                (
                                    [3] => Array
                                        (
                                            [email2] =>
                                            [email1] =>
                                        )
                                    [5] => Array
                                        (
                                            [email2] =>
                                            [email1] => jones@nowhere.com
                                        )
                                )
                            [person1] => Array
                        )
                )

         *
         * Due to the indexing one can surmise that the father is in a particular address[] element
         * and the mother is in another particular address[] element. There are tass configs
         * which indicate where father and mother nodes exist:
         *      external_tass_addnum_mother, external_tass_addnum_father
         */

        /* Tass families come in one of two flavours: nuclear and split
         * Nuclear families arrive as one node under a parent_code node
         * Split families arrive as multiple nodes under a parent_code node
         *
         * Schoolbox treats parents as individual entities so we have to divide
         * families' data and complete it as best we can
         */
        $nuclear = 0;
        $split = 0;

        foreach ($families['parents'] as $row) {
            foreach ($row as $parentId => $family) {
                if (isset($family['students'])) {
                    /* Nuclear family
                     * person1 and person2 need to be each given an externalId
                     */
                    foreach (self::PARENT_PERSON_NODES as $node) {
                        $personExternalId = $this->deriveExternalId($node, (string)$family['user_code'], '');

                        $this->processParentNode($node, $personExternalId, $family);
                    }

                    $nuclear++;
                } else {
                    /* Split family
                     */

                    /* Each record in $family is actually a sub-family; due to tass forcing
                     * each person come with their spouse. However, each sub-family contains
                     * address nodes. Fathers and mothers use different address[n] indexes.
                     *
                     * + Assumption: Detect spouse based on addnum_* indexes
                     * In that way you can determine which one is the primary parent, which is
                     * usually the father, using external_tass_addnum_father and checking whether
                     * address[external_tass_addnum_father] is set.
                     *
                     * Then you can detect which one is the secondary parent (spouse), which is
                     * usually the mother, using external_tass_addnum_mother and checking whether
                     * address[external_tass_addnum_mother] is set.
                     *
                     * Each split parent comes with their own students[] node, so index that
                     * in particular for each student in $lookup
                     */
                    foreach ($family as $subfamily) {
                        /* Note that some schools try to switch primary parents to mother
                         * and secondary parents to father. We aren't doing that here.
                         * Subclass this lookup if you want to do crazy things like that.
                         */

                        /* Unknown parent type; skip this for now since we can't tell
                         * whether this is a father or a mother
                         */
                        if (!isset($subfamily['address'][$this->addnumFather]) && !isset($subfamily['address'][$this->addnumMother])) {
                            $unknown[] = $subfamily['user_code'];

                            continue;
                        }

                        /* Once you identify fathers, construct a parent using their node
                         * which is apparently person2.
                         */
                        if (isset($subfamily['address'][$this->addnumFather])) {
                            $personExternalId = $this->deriveExternalId(self::FATHER_NODE, (string)$subfamily['user_code'], (string)$subfamily['sfa_num']);

                            $this->processSplitParentNode(
                                self::FATHER_NODE,
                                $personExternalId,
                                $subfamily,
                                $this->addnumFather
                            );
                        }

                        /* Once you identify mothers, construct a parent using their node
                         * which is apparently person1.
                         */
                        if (isset($subfamily['address'][$this->addnumMother])) {
                            $personExternalId = $this->deriveExternalId(self::MOTHER_NODE, (string)$subfamily['user_code'], (string)$subfamily['sfa_num']);

                            $this->processSplitParentNode(
                                self::MOTHER_NODE,
                                $personExternalId,
                                $subfamily,
                                $this->addnumMother
                            );
                        }
                    }

                    $split++;
                }
            }
        }

        /* Return a payload containing lookups, index and some diagnostic data
         */
        return [
            'lookup' => $this->lookup,
            'unknown' => $unknown,
            'count' => [
                'nuclear' => $nuclear,
                'split' => $split,
                'unknown' => \count($unknown),
            ],
        ];
    }


    /**
     * Update $parent details by mining Split Parent format in a particular way to get their email addresses
     *
     * @param string $node
     * @param string $externalId
     * @param array  $family
     * @param int    $addnum
     * @return array
     */
    private function processSplitParentNode(string $node, string $externalId, array $family, int $addnum): array
    {
        /* Prioritise preferred names over official names
         */
        $firstname = (new FirstMeaningfulValue())->find([
            $family[$node]['preferred_name'],
            $family[$node]['first_name']
        ]);

        $person = $this->phones[$family['user_code']][$node] ?? [
            'firstname' => $firstname,
            'surname' => $family[$node]['surname'],
            'mobile' => '',
            'home' => '',
            'work' => '',
            'email' => '',
            'relationship' => $family[$node]['description'],
        ];

        /* Find the first non-empty email address from the two possibilities
         *
         * Not entirely certain if these are two emails that you can attribute to a single person,
         * or whether this is actually a person's email (email1) and their spouse's email (email2)
         * or whether it's a mother's email (email1) and a father's email (email2)
         *
         * Could be anything.
         */
        $person['email'] = (new FirstMeaningfulValue())->find([
            $family['address'][$addnum]['email1'],
            $family['address'][$addnum]['email2'],
        ]);

        /* Assign an externalId to this person
         */
        $person['externalId'] = $externalId;

        /* Assign studentIds to each of their externalIds, as per the
         * $family['students'] node
         */
        $person['students'] = $family['students'];

        /* Index each parent by their externalId
         */
        foreach ($person['students'] as $studentExternalId) {
            $this->lookup[$studentExternalId][$externalId] = $person;
        }

        return $person;
    }


    /**
     * Facade method for compiling some parental details
     *
     * @param string $node
     * @param string $externalId
     * @param array  $family
     * @return array
     */
    private function processParentNode(string $node, string $externalId, array $family): array
    {
        /* Prioritise preferred names over official names
         */
        $firstname = (new FirstMeaningfulValue())->find([
            $family[$node]['preferred_name'],
            $family[$node]['first_name']
        ]);

        $person = $this->phones[$family['user_code']][$node] ?? [
            'firstname' => $firstname,
            'surname' => $family[$node]['surname'],
            'mobile' => '',
            'home' => '',
            'work' => '',
            'email' => '',
            'relationship' => $family[$node]['description'],
        ];

        /* Assign an externalId to this person
         */
        $person['externalId'] = $externalId;

        /* Assign studentIds to each of their externalIds, as per the
         * $family['students'] node
         */
        $person['students'] = $family['students'];

        /* Index each parent by their externalId
         */
        foreach ($person['students'] as $studentExternalId) {
            $this->lookup[$studentExternalId][$externalId] = $person;
        }

        return $person;
    }
}
