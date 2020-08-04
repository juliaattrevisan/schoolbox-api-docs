<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

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
     * $api uses the credentials for DSNCodec::MODULE_IDENTITY_MANAGEMENT
     *
     * @var IApi
     */
    private $api;


    /**
     * Family constructor.
     *
     * $api uses the credentials for DSNCodec::MODULE_IDENTITY_MANAGEMENT
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }


    /**
     * Preload parents into a lookup
     *
     * This is probably going to be faster if we build a hash lookup of parents
     */
    public function generate(): array
    {
        $families = $this->api->retrieveDocument('getParents', [
            'user_code' => 'all'
        ]);
print_r($families);die();
        $lookup = [];
        $index = [];
        $malformed = [];

        /* TODO: Might have to shank parents' mobile numbers from another api endpoint
         */

        foreach ($families['parents'] as $row) {
            foreach ($row as $parentId => $family) {

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
                if (isset($family['students'])) {
                    foreach ($family['students'] as $studentId) {
                        $lookup[$studentId][$parentId] = $parentId;
                        $index[$parentId] = $family;
                    }
                } else {

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

                }
            }
        }

        return [
            'lookup' => $lookup,
            'index' => $index,
            'malformed' => $malformed
        ];
    }
}
