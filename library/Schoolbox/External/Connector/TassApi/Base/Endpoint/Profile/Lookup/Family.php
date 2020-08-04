<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\TassApi\Base\Endpoint\DSNCodec;
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
     * Default spouse prefix
     *
     * @var string
     */
    private const DEFAULT_SPOUSE_PREFIX = 'S';


    /**
     * Parent nodes inside a family data struct
     */
    private const PARENT_PERSON_NODES = ['person2', 'person1'];


    /**
     * $api uses the credentials for DSNCodec::MODULE_IDENTITY_MANAGEMENT
     *
     * @var IApi
     */
    private $api;


    /**
     * Set in Admin. This var deconflicts shared usernames by putting a letter in front,
     * because tass doesn't treat parents independently of families.
     *
     * @var string
     */
    private $spousePrefix;


    /**
     * Set in admin, corresponds to external_tass_addnum_father
     *
     * @var int
     */
    private $addnumFather;


    /**
     * Set in admin, corresponds to external_tass_addnum_mother
     *
     * @var int
     */
    private $addnumMother;


    /**
     * The node corresponding to a father's data in a Family record.
     * I have a fear that this will be different per tass implementation somewhere but, for now,
     * presume that 'person2' is (and always is) the papa
     *
     * @var string
     */
    private $fatherNode = 'person2';


    /**
     * An array of phone numbers indexed by family code
     *
     * @var array
     */
    private $phones;


    /**
     * Family constructor.
     *
     * $api uses the credentials for DSNCodec::MODULE_IDENTITY_MANAGEMENT
     *
     * @param IApi   $api
     * @param string $spousePrefix
     * @param int    $addnumFather
     * @param int    $addnumMother
     * @param array  $phones
     */
    public function __construct(
        IApi $api,
        string $spousePrefix,
        int $addnumFather,
        int $addnumMother,
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
        if ($this->fatherNode === $node) {
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
            $externalId .= $sfaNum;
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

        $lookup = [];
        $index = [];
        $malformed = [];

        /* Tass generally adheres to $parent['students'] data structure
         *
                Array
                (
                    [web_access] =>
                    [user_code] => 10620
                    [person2] => Array
                    [person1] => Array
                    ...

         * ...EXCEPT when they've joined onto an email address table somewhere, at which point
         * their own api structure mutates slightly into something nonstandard,
         * where $parent becomes a 0-indexed array instead of the usual key-value tuple content.
         *
         * no joke. I can't make this stuff up even on a bad day.
         *
         * TODO Fiddle with tass config settings
         *  Due to the indexing one can surmise that the father is in a particular address[] element
         *  and the mother is in another particular address[] element. There are tass configs
         *  which indicate where father and mother nodes exist:
         *      external_tass_addnum_mother, external_tass_addnum_father
         *
         * TODO The "malformed" data occurs with split families so they end up with separate records
         *  that contain similar, thought no always identical, data; but with the same parent_code
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

         */

        /* Tass families come in one of two flavours: nuclear and split
         * Nuclear families arrive as one node under a parent_code node
         * Split families arrive as multiple nodes under a parent_code node
         *
         * Schoolbox treats parents as individual entities so we have to divide
         * families' data and complete it as best we can
         */
        foreach ($families['parents'] as $row) {
            foreach ($row as $parentId => $family) {
                if (isset($family['students'])) {

                    /* Nuclear family
                     * person1 and person2 need to be each given an externalId
                     */
                    foreach (self::PARENT_PERSON_NODES as $node) {
                        $person = $family[$node];
                        $person['externalId'] = $this->deriveExternalId($node, (string)$family['user_code'], '');

                        /* Assign studentIds to each of their externalIds, as per the
                         * $family['students'] node
                         */
                        $person['students'] = $family['students'];

                        /* Merge phone numbers from another lookup which extracted data
                         * from some other endpoint
                         */
                        $person['addresses'] = $this->phones[$family['user_code']];

                        /* Index each parent by their externalId
                         */
                        foreach($person['students'] as $student) {
                            $lookup[$student][] = $person['externalId'];
                        }

                        $index[$person['externalId']] = $person;
                    }
                } else {

                    /* Split family
                     */

                    /* Each record in $family is actually a sub-family; due to tass forcing
                     * each person come with their spouse. However, each sub-family contains
                     * address nodes. Fathers and mothers use different address[n] indexes.
                     *
                     * In that way you can determine which one is the primary parent, which is
                     * usually the father, using external_tass_addnum_father and checking whether
                     * address[external_tass_addnum_father] is set.
                     */

                    /* Then you can detect which one is the secondary parent (spouse), which is
                     * usually the mother, using external_tass_addnum_mother and checking whether
                     * address[external_tass_addnum_mother] is set.
                     */

                    /* Note that some schools try to switch primary parents to mother
                     * and secondary parents to father. We aren't doing that here.
                     * Subclass this lookup if you want to do crazy things like that.
                     */

                    /* Anyway, once you identify fathers, construct a parent using their node
                     * which is apparently person2.
                     */

                    /* Merge phone numbers from another lookup which extracted data
                     * from some other endpoint
                     */

                    /* Each split parent comes with their own students[] node, so index that
                     * in particular for each student in $lookup
                     */

                    /* Once you identify mothers, construct a parent using their node
                     * which is apparently person1.
                     */

                    /* Merge phone numbers from another lookup which extracted data
                     * from some other endpoint
                     */

                    /* Each split parent comes with their own students[] node, so index that
                     * in particular for each student in $lookup
                     */
                }
            }
        }




//        foreach ($families['parents'] as $row) {
//            foreach ($row as $parentId => $family) {
//
//
//                if (isset($family['students'])) {
//                    foreach ($family['students'] as $studentId) {
//                        $lookup[$studentId][$parentId] = $parentId;
//                        $index[$parentId] = $family;
//                    }
//                } else {
//
//                    /* Maintain a list of broken records that we skipped
//                     */
//                    $malformed[] = $parentId;
//
//                    /* Wrap around the brokenness like scar tissue
//                     * Keep track of split family parents and their related students
//                     */
//                    foreach ($family as $splitParent) {
//                        foreach ($family['students'] as $studentId) {
//                            $lookup[$studentId][$parentId] = $parentId;
//                            $index[$parentId] = $family;
//                        }
//                    }
//
//
//                    /* We know that all elements are the same, except for the address node
//                     * so we can just use the first element of $family as a basis
//                     */
//                    $newFamily = $family[0];
//
//                    /* Then merge all the address nodes from every other node in $family
//                     */
//                    $mergedAddresses = array_merge(...$family)['addresses'];
//
//                    /* Then take the address nodes and shank it on top of $newFamily
//                     * API "repaired", "Funding secured."
//                     */
//                    $newFamily['addresses'] = $mergedAddresses;
//
//                    /* Index this new version of $family instead of the old
//                     */
//
//                }
//            }
//        }

        return [
            'lookup' => $lookup,
            'index' => $index,
            'malformed' => $malformed
        ];
    }
}
