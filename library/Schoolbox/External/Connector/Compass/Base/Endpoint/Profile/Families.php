<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Profile;

use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Class Families
 *
 * Maintain a list of Parents indexed by their Compass ID
 * so we can look them up as we process each student
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint\Profile
 */
class Families
{

    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * Families constructor.
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }


    /**
     * Index parent records by their compass guid
     *
     * @param string $lBound
     * @param string $uBound
     * @return array
     */
    public function generate(string $lBound, string $uBound): array
    {
        $parents = $this->api->retrieveDocument('person/v1/GetParents', [
            'lBound' => $lBound,
            'uBound' => $uBound
        ]);

        $stack = [];

        foreach ($parents['d'] as $parent) {
            $stack[$parent['compassPersonId']] = $parent;
        }

        return $stack;
    }
}
