<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;

/**
 * Build an index of all parents, because we need their sfa num
 * Instead of making a billion little api requests on demand for each parent,
 * we can get the whole list in one request and index the parts we need,
 * saving us the http request time
 */
class Parents
{
    /**
     * A set of api classes for each of tass ' products
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
     * An index of all parents and their sfa_num value, returned in set
     * of key-value tuples. There's just one data point in use - sfa_num -
     * but you might need more than that later
     */
    public function generate(): array
    {
        $families = [];

        /* Get everyone in one big request
         */
        $parents = $this->api[DSN::MODULE_IDENTITY_MANAGEMENT]->retrieveDocument('getParents', [
            'user_code' => 'ALL',
        ]);

        /* No parents? No trabajo
         */
        if (!isset($parents['parents'])) {
            return $families;
        }

        /* Otherwise, stack up all the parts you need for each identifier
         */
        foreach ($parents['parents'] as $parent) {
            foreach ($parent as $externalId => $data) {
                /* ffs
                 *
                 * If this sfa_num exists here, this isn't a split family
                 * It's a nukular one, and we don't need to append anything
                 *
                 * Otherwise it appears as a set underneath $identifier
                 * Disgusting.
                 *
                    Array (nukular)
                    (
                        [__tassversion] => 01.057.6.001
                        [parents] => Array
                            (
                                [0] => Array
                                    (
                                        [146865] => Array
                                            (
                                                [sfa_num] =>

                    Array (split)
                    (
                        [__tassversion] => 01.057.6.001
                        [parents] => Array
                            (
                                [0] => Array
                                    (
                                        [172328] => Array
                                            (
                                                [0] => Array
                                                    (
                                                        [sfa_num] => 1
                 *
                 */
                if (!isset($data['sfa_num'])) {
                    /* Might be more than one element, but I haven't seen it
                     * do that anywhere yet
                     */
                    $sfaNum = trim((string)$data[0]['sfa_num']);

                    $families[$externalId] = $sfaNum;
                } else {
                    $families[$externalId] = [
                        'sfa_num' => $data['sfa_num']
                    ];
                }
            }
        }

        return $families;
    }
}
