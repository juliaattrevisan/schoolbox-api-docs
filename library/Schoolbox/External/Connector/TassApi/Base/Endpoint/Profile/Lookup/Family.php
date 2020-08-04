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

                    /* Maintain a list of broken records that we skipped
                     */
                    $malformed[] = $parentId;
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
